<?php

namespace NextDeveloper\Marketplace\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Models\AccountUsers;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Services\AccountUsersService;
use NextDeveloper\IAM\Services\UsersService;
use NextDeveloper\Marketplace\Database\Models\CustomerMappings;
use NextDeveloper\Marketplace\Database\Models\Providers;

/**
 * Resolves a normalized Shopify customer to an iam_users row through
 * marketplace_customer_mappings, creating the user only as a last resort.
 *
 * The dedupe ladder (plan §11), in order:
 *
 *   1. Mapping row exists for (provider, customer GID)   → done.
 *   2. lower(trim(email)) match WITHIN the merchant account → link.
 *   3. E.164 phone match WITHIN the merchant account        → link.
 *   4. Create the iam_user under the merchant account.
 *
 * Steps 2–3 are deliberately scoped to the connecting account's own users: a
 * global email match could attach one tenant's order history to an unrelated
 * PlusClouds user who happens to share the address.
 *
 * Created users are platform identities that must never authenticate: no
 * password, no login mechanism, is_registered=false, tagged so they are
 * distinguishable from real signups. Guests (no email) get no user at all.
 *
 * Linked pre-existing users are never mutated — only the mapping row is
 * written. A merchant's Shopify data must not overwrite a real person's
 * profile.
 */
class ShopifyCustomerResolver
{
    public const CUSTOMER_TAG = 'marketplace-shopify-customer';

    /**
     * @param  array<string, mixed>  $customer  Output of ShopifyAdapter::normalizeCustomerData()
     */
    public static function resolve(Providers $provider, array $customer, bool $dryRun = false): ?CustomerMappings
    {
        $externalId = $customer['external_id'] ?? null;

        if (! $externalId) {
            return null;
        }

        $mapping = CustomerMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id)
            ->where('external_customer_id', $externalId)
            ->first();

        if ($mapping) {
            if ($dryRun) {
                return $mapping;
            }

            if ($mapping->sync_hash !== ($customer['sync_hash'] ?? null)
                && ! self::mappingIsNewer($mapping, $customer)) {
                $mapping->fill([
                    'sync_hash' => $customer['sync_hash'] ?? null,
                    'external_updated_at' => $customer['external_updated_at'] ?? null,
                ]);
            }

            $mapping->last_synced_at = Carbon::now();
            $mapping->saveQuietly();

            return $mapping;
        }

        $accountId = (int) $provider->iam_account_id;
        $email = $customer['email_normalized'] ?? null;

        // Guests and customers with no email are not identities we can safely
        // dedupe or ever contact — creating identity-less users would be worse
        // than the gap. The order keeps its customer_data jsonb.
        if (! $email) {
            return null;
        }

        $userId = self::matchByEmail($accountId, $email)
            ?? self::matchByPhone($accountId, $customer['phone'] ?? null, $customer['country_code'] ?? null);

        if ($dryRun) {
            return null;
        }

        if (! $userId) {
            /*
             * UsersService::createWithoutAccount() reuses ANY existing user with
             * this email, account membership notwithstanding — which is exactly
             * the cross-tenant link the dedupe ladder exists to prevent (it
             * would also quietly attach that person to the merchant account).
             * A shopper whose email belongs to a user of another account gets
             * no identity here; the order keeps its customer_data jsonb.
             */
            $collision = Users::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->where('email', $customer['email'])
                ->exists();

            if ($collision) {
                Log::warning(__METHOD__.' - shopper email belongs to a user outside the merchant account; not linking', [
                    'provider_id' => $provider->id,
                    'external_customer_id' => $externalId,
                ]);

                return null;
            }

            $user = UsersService::createWithoutAccount([
                'email' => $customer['email'],
                'name' => (string) ($customer['first_name'] ?? ''),
                'surname' => (string) ($customer['last_name'] ?? ''),
                'phone_number' => self::e164($customer['phone'] ?? null, $customer['country_code'] ?? null),
                'is_registered' => false,
                'tags' => [self::CUSTOMER_TAG],
            ]);

            $userId = $user->id;

            Log::info(__METHOD__.' - created shopper identity', [
                'iam_user_id' => $userId,
                'provider_id' => $provider->id,
            ]);
        }

        self::attachToAccount($userId, $accountId);

        $mapping = new CustomerMappings;
        $mapping->fill([
            'iam_user_id' => $userId,
            'marketplace_provider_id' => $provider->id,
            'marketplace_market_id' => $provider->marketplace_market_id,
            'external_customer_id' => (string) $externalId,
            'sync_hash' => $customer['sync_hash'] ?? null,
            'external_updated_at' => $customer['external_updated_at'] ?? null,
            'last_synced_at' => Carbon::now(),
            'iam_account_id' => $accountId,
        ]);
        $mapping->saveQuietly();

        return $mapping;
    }

    private static function mappingIsNewer(CustomerMappings $mapping, array $customer): bool
    {
        return $mapping->external_updated_at
            && ! empty($customer['external_updated_at'])
            && Carbon::parse($mapping->external_updated_at)->greaterThan($customer['external_updated_at']);
    }

    private static function matchByEmail(int $accountId, string $email): ?int
    {
        return Users::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->join('iam_account_user', 'iam_account_user.iam_user_id', '=', 'iam_users.id')
            ->where('iam_account_user.iam_account_id', $accountId)
            ->whereRaw('lower(trim(iam_users.email)) = ?', [$email])
            ->value('iam_users.id');
    }

    private static function matchByPhone(int $accountId, ?string $phone, ?string $countryCode): ?int
    {
        $normalized = self::e164($phone, $countryCode);

        if (! $normalized) {
            return null;
        }

        return Users::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->join('iam_account_user', 'iam_account_user.iam_user_id', '=', 'iam_users.id')
            ->where('iam_account_user.iam_account_id', $accountId)
            ->where('iam_users.phone_number', $normalized)
            ->value('iam_users.id');
    }

    private static function attachToAccount(int $userId, int $accountId): void
    {
        $exists = AccountUsers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('iam_account_id', $accountId)
            ->where('iam_user_id', $userId)
            ->exists();

        if (! $exists) {
            AccountUsersService::create([
                'iam_account_id' => $accountId,
                'iam_user_id' => $userId,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Shopify stores customer phones in E.164 already; anything else is parsed
     * with the country as a hint when the library is available, and dropped
     * otherwise — a raw national string must never be compared or persisted.
     */
    private static function e164(?string $phone, ?string $countryCode): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', preg_replace('/[\s().-]/', '', $phone) ?? '')) {
            return preg_replace('/[\s().-]/', '', $phone);
        }

        if (class_exists(PhoneNumberUtil::class) && $countryCode) {
            try {
                $util = PhoneNumberUtil::getInstance();
                $parsed = $util->parse($phone, strtoupper($countryCode));

                if ($util->isValidNumber($parsed)) {
                    return $util->format($parsed, PhoneNumberFormat::E164);
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return null;
    }
}
