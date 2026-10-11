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
}
