<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;

/**
 * Picks the connected provider of one adapter (ebay, shopify) that a product is linked through.
 *
 * "No connected shop found" for every possible cause left users guessing, so each cause gets its own
 * message: no provider of that adapter in the product's account, the one asked for is not there,
 * the connection is paused, or it never got (or lost) its access token. Requests are refused with
 * the reason in the message, which this app returns to the client as the `helper` field.
 */
class ExternalProviderResolver
{
    /**
     * @param  string  $adapter  'ebay' or 'shopify'
     * @param  string  $label  How the adapter is called in messages, e.g. 'eBay'
     *
     * @throws NotFoundException When there is nothing to link against.
     * @throws NotAllowedException When the choice is ambiguous or the connection is unusable.
     */
    public static function resolve(Products $product, string $adapter, string $label, ?string $providerRef = null): Providers
    {
        $all = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->whereRaw('lower(trim(adapter)) = ?', [$adapter])
            ->where('iam_account_id', $product->iam_account_id)
            ->get();

        if ($all->isEmpty()) {
            throw new NotFoundException("No {$label} account is set up in this product's account. Connect {$label} first.");
        }

        $candidates = $providerRef ? $all->where('uuid', $providerRef)->values() : $all;

        if ($candidates->isEmpty()) {
            throw new NotFoundException("The {$label} provider that was selected does not belong to this product's account.");
        }

        $active = $candidates->where('is_active', true)->values();

        if ($active->isEmpty()) {
            throw new NotAllowedException("The {$label} connection is paused (switched off, possibly after repeated sync failures). Activate it in the {$label} card first.");
        }

        // A connection that never finished consent, or whose stored token cannot be read, has nothing to work with.
        $connected = $active->filter(fn (Providers $p) => (string) $p->getDecryptedAccessToken() !== '')->values();

        if ($connected->isEmpty()) {
            throw new NotAllowedException("The {$label} connection has no access token. Reconnect {$label} and try again.");
        }

        if ($connected->count() > 1) {
            throw new NotAllowedException("Several {$label} accounts are connected. Say which one with provider_id.");
        }

        return $connected->first();
    }

    /**
     * A message for a failed call to the external API, for the user instead of a 500.
     *
     * The clients throw a plain RuntimeException that carries "HTTP <status>" in its text. A 401/403
     * means the stored token is no longer accepted, which is the common cause and needs the shop to be
     * reconnected, so it gets its own wording.
     */
    public static function describeFailure(\Throwable $e, string $label): string
    {
        $message = $e->getMessage();

        if (preg_match('/HTTP (401|403)\b/', $message, $m)) {
            return "{$label} rejected the stored access token (HTTP {$m[1]}). The connection is no longer valid: reconnect {$label} with a fresh access token, then activate it.";
        }

        if (preg_match('/HTTP 429\b/', $message)) {
            return "{$label} is rate limiting requests. Try again in a minute.";
        }

        if (preg_match('/HTTP (5\d\d)\b/', $message, $m)) {
            return "{$label} had a server error (HTTP {$m[1]}). Try again later.";
        }

        return "The {$label} request failed: ".mb_substr($message, 0, 200);
    }
}
