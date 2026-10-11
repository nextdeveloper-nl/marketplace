<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ideasoft;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\IdeasoftOAuthService;

/**
 * Thin REST client for the Ideasoft merchant API.
 *
 * Ideasoft's public documentation (apidoc.ideasoft.dev) is a JS-rendered
 * Stoplight portal that could not be scraped for exact endpoint paths and
 * payload shapes at the time this class was written. What is confirmed, from
 * https://www.ideasoft.com.tr/yardim/api-kullanimi/:
 *
 *  - Auth is OAuth2 with a Client ID/Client Secret pair, created per-store
 *    under the merchant's admin at Entegrasyonlar > API.
 *  - The full grant is 3-legged (authorize redirect + callback + code
 *    exchange), which this class does not implement yet — see
 *    IdeasoftAdapter::authenticate() and docs/marketplace/ideasoft-oauth.md.
 *    This client currently supports only exchanging an already-obtained
 *    authorization code or refreshing an existing token, so a provider row
 *    can be connected with a manually obtained token in the interim.
 *  - Products, stock, price, orders and accounting data are exposed; REST
 *    and XML are both mentioned, this client targets REST.
 *
 * Every request-shaped method below is deliberately left unimplemented
 * (throws) rather than guessing a path, to avoid a wrong guess silently
 * shipping into production. Fill each in against confirmed endpoints from
 * the portal or apisupport@ideasoft.com.tr.
 */
class IdeasoftClient
{
    private Providers $provider;

    private string $storeDomain;

    private string $clientId;

    private string $clientSecret;

    private int $timeout = 30;

    public function __construct(Providers $provider)
    {
        $this->provider = $provider;

        $config = $this->config();

        $storeDomain = trim((string) ($config['store_domain'] ?? ''));

        if ($storeDomain === '') {
            throw new NotFoundException(
                'Ideasoft provider '.$provider->uuid.' has no store_domain in api_config.'
            );
        }

        $clientId = trim((string) ($config['client_id'] ?? ''));

        if ($clientId === '') {
            throw new NotFoundException(
                'Ideasoft provider '.$provider->uuid.' has no client_id in api_config.'
            );
        }

        $clientSecret = (string) $provider->getDecryptedClientSecret();

        if ($clientSecret === '') {
            throw new NotFoundException(
                'Ideasoft provider '.$provider->uuid.' has no client_secret. Connect the store first.'
            );
        }

        $this->storeDomain = $storeDomain;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getStoreDomain(): string
    {
        return $this->storeDomain;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return $this->provider->getApiConfigArray();
    }

    /**
     * Whether a usable access token is currently stored for this provider.
     *
     * This is not a live credential check — it only confirms a token was
     * saved, mirroring how the panel's "leave blank to keep current" form
     * never round-trips the secret to verify it. A real authenticate() that
     * calls Ideasoft is pending the OAuth flow in the class docblock above.
     */
    public function hasStoredToken(): bool
    {
        return (string) $this->provider->getDecryptedAccessToken() !== '';
    }

    /**
     * Base HTTP client, pre-authenticated with the stored bearer token.
     *
     * @throws NotFoundException When no token has been connected yet.
     */
    private function http(): PendingRequest
    {
        // Added: refresh the access token first when it is expired/near expiry and a refresh
        // token is on file, so callers never hit Ideasoft with a stale bearer token.
        $oauth = new IdeasoftOAuthService($this->provider);
        if ($oauth->needsRefresh() && $oauth->hasRefreshToken()) {
            $this->provider = $oauth->refreshAccessToken();
        }

        $token = (string) $this->provider->getDecryptedAccessToken();

        if ($token === '') {
            throw new NotFoundException(
                'Ideasoft provider '.$this->provider->uuid.' has no access token. Connect the store first.'
            );
        }

        return Http::baseUrl('https://'.$this->storeDomain)
            ->withToken($token)
            ->acceptJson()
            ->timeout($this->timeout);
    }

    /**
     * Fetch orders changed at or after the given moment.
     *
     * Path/payload unconfirmed — see class docblock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(\Illuminate\Support\Carbon $since): array
    {
        throw new \LogicException(
            'Ideasoft order fetch is not implemented yet: the exact endpoint path is unconfirmed. '
            .'See IdeasoftClient class docblock.'
        );
    }

    /**
     * Push a product to Ideasoft, creating or updating it.
     *
     * Path/payload unconfirmed — see class docblock.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function pushProduct(array $payload): array
    {
        throw new \LogicException(
            'Ideasoft product push is not implemented yet: the exact endpoint path is unconfirmed. '
            .'See IdeasoftClient class docblock.'
        );
    }

    /**
     * Push a stock level to Ideasoft for a single SKU.
     *
     * Path/payload unconfirmed — see class docblock.
     */
    public function pushStock(string $sku, int $quantity): bool
    {
        throw new \LogicException(
            'Ideasoft stock push is not implemented yet: the exact endpoint path is unconfirmed. '
            .'See IdeasoftClient class docblock.'
        );
    }

    /**
     * Log a request failure with enough context to diagnose without a
     * live repro, matching the convention in ShopifyClient.
     */
    private function logApiError(string $message, array $context = []): void
    {
        Log::error(__METHOD__.' - '.$message, $context + [
            'provider_id' => $this->provider->id,
            'store_domain' => $this->storeDomain,
        ]);
    }
}
