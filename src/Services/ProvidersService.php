<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProvidersService;

/**
 * This class is responsible from managing the data for Providers
 *
 * Class ProvidersService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class ProvidersService extends AbstractProvidersService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Added: the generated abstract service does not convert marketplace_market_id (public UUID)
     * to the internal bigint id, which made the insert fail with SQLSTATE[22P02]. We convert it
     * here and delegate to the generated create().
     */
    public static function create(array $data)
    {
        $data = self::resolveMarketId($data);

        return parent::create($data);
    }

    /**
     * Added: same UUID -> id conversion for marketplace_market_id on update.
     */
    public static function update($id, array $data)
    {
        $data = self::resolveMarketId($data);

        return parent::update($id, $data);
    }

    /**
     * Converts marketplace_market_id from UUID to the internal id (null stays null).
     */
    private static function resolveMarketId(array $data): array
    {
        if (!empty($data['marketplace_market_id'])) {
            $data['marketplace_market_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Marketplace\Database\Models\Markets',
                $data['marketplace_market_id']
            );
        }

        return $data;
    }

    /**
     * Added: stores the OAuth access token (encrypted by the model mutator) and the updated
     * api_config (refresh token + expiry) on a provider. Used by the Ideasoft OAuth flow, which
     * runs from an unauthenticated callback, so it writes by the already-resolved model instead
     * of going through the AuthorizationScope-filtered update() lookup.
     */
    public static function storeOAuthTokens(Providers $provider, string $accessToken, array $apiConfig): Providers
    {
        // The OAuth callback is unauthenticated (Ideasoft redirects the browser), so the observer's
        // role check on save() has no user and refused the write. Authorization already happened
        // when the logged-in user started the flow through connect() (the one-time state maps back
        // to a provider they could access), so the role check is bypassed for this write only.
        \NextDeveloper\IAM\Helpers\UserHelper::withRolesCheckBypassed(function () use ($provider, $accessToken, $apiConfig) {
            $provider->access_token_enc = $accessToken;
            $provider->api_config = $apiConfig;
            $provider->save();
        });

        // Drop the cached transformed provider (keyed by UUID) so the API reflects the new state.
        CacheHelper::deleteKeys('Providers', $provider->uuid);

        return $provider->fresh();
    }

    /**
     * Added: queues an on-demand Ideasoft sync for one provider (the manual counterpart of the
     * scheduled marketplace:sync-ideasoft run). Returns what was queued; the work itself runs on
     * the marketplace-sync queue, and progress shows in provider-sync-states.
     *
     * @param  string  $ref     Provider uuid (public id)
     * @param  string  $entity  products | inventory | all
     * @param  bool    $full    Ignore the cursor and pull everything
     * @return array<string, mixed>
     */
    public static function syncNow(string $ref, string $entity = 'all', bool $full = false): array
    {
        // Looked up through the scoped model, so users can only sync providers they can see.
        $provider = self::getByRef($ref);

        // Added: eBay joins Ideasoft as an on-demand syncable adapter; each has its own job map.
        $adapter = $provider ? strtolower(trim((string) $provider->adapter)) : '';

        if (! $provider || ! in_array($adapter, ['ideasoft', 'ebay'], true)) {
            throw new \NextDeveloper\Commons\Exceptions\NotFoundException('Syncable provider not found.');
        }

        if (! \NextDeveloper\IAM\Helpers\UserHelper::can('update', $provider)) {
            throw new \NextDeveloper\Commons\Exceptions\NotAllowedException('You are not allowed to sync this provider.');
        }

        if (! $provider->is_active) {
            throw new \NextDeveloper\Commons\Exceptions\NotAllowedException('This provider is paused. Activate it before syncing.');
        }

        if ((string) $provider->getDecryptedAccessToken() === '') {
            throw new \NextDeveloper\Commons\Exceptions\NotAllowedException('This store is not connected yet. Connect the account first.');
        }

        $jobs = $adapter === 'ebay'
            ? \NextDeveloper\Marketplace\Console\Commands\SyncEbayCommand::ENTITY_JOBS
            : \NextDeveloper\Marketplace\Console\Commands\SyncIdeasoftCommand::ENTITY_JOBS;
        $entity = strtolower($entity);

        if ($entity !== 'all' && ! array_key_exists($entity, $jobs)) {
            throw new \NextDeveloper\Commons\Exceptions\NotAllowedException('Unknown entity. Use products, inventory or all.');
        }

        // Debounce: a double click or a script loop must not stack full catalog walks.
        if (! \Illuminate\Support\Facades\Cache::add('marketplace:'.$adapter.':sync-now:'.$provider->id, 1, 60)) {
            throw new \NextDeveloper\Commons\Exceptions\NotAllowedException('A sync was triggered for this provider less than a minute ago.');
        }

        $entities = $entity === 'all' ? array_keys($jobs) : [$entity];

        // Chained, so inventory never runs before products have created the catalogs it updates.
        \Illuminate\Support\Facades\Bus::chain(
            array_map(fn (string $e) => new $jobs[$e]($provider->id, false, $full), $entities)
        )->dispatch();

        return [
            'provider_id' => $provider->uuid,
            'queued' => $entities,
            'full' => $full,
        ];
    }
}
