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
     * GET a path from the Ideasoft Admin API as JSON.
     *
     * If Ideasoft answers 401 (token revoked or expired earlier than expires_in said), the stored
     * refresh token is used once and the request is retried before giving up.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->http()->get($path, $query);

        if ($response->status() === 401) {
            $oauth = new IdeasoftOAuthService($this->provider);

            if ($oauth->hasRefreshToken()) {
                $this->provider = $oauth->refreshAccessToken();
                $response = $this->http()->get($path, $query);
            }
        }

        if (! $response->successful()) {
            $this->logApiError('Ideasoft GET failed', [
                'path' => $path,
                'status' => $response->status(),
                'response_body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            throw new \RuntimeException('Ideasoft GET '.$path.' failed with HTTP '.$response->status().'.');
        }

        return (array) $response->json();
    }

    /**
     * Fetch every product, page by page, optionally only those changed at or after $since.
     *
     * Endpoint /admin-api/products is confirmed to exist (answers 401 without a token). The paging
     * parameters (page, limit) and the response envelope are not documented publicly, so the
     * envelope is read defensively (bare list, or wrapped in data/items/hydra:member), and
     * paging stops on an empty or short page. Use `marketplace:ideasoft-probe` to see a real payload.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(?\Illuminate\Support\Carbon $since = null): array
    {
        $limit = 100;
        $maxPages = 500; // hard stop: 50,000 products, protects against a paging loop
        $products = [];
        $seen = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $items = $this->extractList($this->get('/admin-api/products', ['page' => $page, 'limit' => $limit]));

            if ($items === []) {
                break;
            }

            // Guard against an API that ignores `page` and returns the same page forever.
            $firstId = (string) ($items[0]['id'] ?? '');
            if ($firstId !== '' && isset($seen[$firstId])) {
                break;
            }
            $seen[$firstId] = true;

            foreach ($items as $item) {
                if ($since !== null && ! $this->changedSince($item, $since)) {
                    continue;
                }

                $products[] = $item;
            }

            if (count($items) < $limit) {
                break;
            }
        }

        return $products;
    }

    /**
     * Pull the list of records out of whatever envelope the API used.
     *
     * @param  array<mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    private function extractList(array $body): array
    {
        if (array_is_list($body)) {
            return $body;
        }

        foreach (['data', 'items', 'hydra:member', 'results'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                return array_values($body[$key]);
            }
        }

        return [];
    }

    /**
     * Whether a record was updated at or after $since. Records with no usable timestamp are kept,
     * because dropping them would hide products that cannot be filtered.
     *
     * @param  array<string, mixed>  $item
     */
    private function changedSince(array $item, \Illuminate\Support\Carbon $since): bool
    {
        $raw = $item['updatedAt'] ?? $item['updated_at'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return true;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->greaterThanOrEqualTo($since);
        } catch (\Throwable) {
            return true;
        }
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
