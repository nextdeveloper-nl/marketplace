<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\EbayOAuthService;

/**
 * Thin REST client for eBay's APIs (Browse, Sell Inventory/Account, Taxonomy, Identity).
 *
 * Hand-rolled on Illuminate\Http for the same reason as ShopifyClient: no new
 * package can be installed in this application.
 *
 * Two authentication modes:
 *
 *  - Application mode (no provider): OAuth client-credentials token that represents our
 *    keyset, not a seller. Shared across the platform and cached, because eBay rate-limits
 *    how many tokens a keyset may mint per day. Only good for public APIs (Browse).
 *
 *  - Seller mode (a Providers row): the seller's own user token, stored encrypted on the
 *    provider by EbayOAuthService. Needed for every Sell API. The token is refreshed through
 *    the stored refresh token when it is about to expire or eBay answers 401.
 */
class EbayClient
{
    private const SCOPE = 'https://api.ebay.com/oauth/api_scope';

    /**
     * Refresh the token this many seconds before eBay says it expires, so a
     * request never leaves with a token that dies in flight.
     */
    private const TOKEN_EXPIRY_MARGIN = 300;

    private string $clientId;

    private string $clientSecret;

    private string $environment;

    private string $marketplaceId;

    private int $timeout;

    private int $retryMax;

    private int $retryBaseMs;

    private ?Providers $provider;

    public function __construct(?string $marketplaceId = null, ?Providers $provider = null)
    {
        $config = (array) config('marketplace.ebay', []);

        $this->clientId = trim((string) ($config['client_id'] ?? ''));
        $this->clientSecret = trim((string) ($config['client_secret'] ?? ''));

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new NotFoundException(
                'eBay is not configured. Set EBAY_CLIENT_ID (App ID) and EBAY_CLIENT_SECRET (Cert ID).'
            );
        }

        $this->provider = $provider;
        $this->environment = strtolower((string) ($config['environment'] ?? 'production')) === 'sandbox'
            ? 'sandbox'
            : 'production';

        // Seller mode takes the marketplace from the provider's own eBay settings unless told otherwise.
        $providerMarketplace = $provider
            ? ($provider->getApiConfigArray()['ebay']['marketplace_id'] ?? null)
            : null;

        $this->marketplaceId = $marketplaceId
            ?: ($providerMarketplace ?: (string) ($config['marketplace_id'] ?? 'EBAY_US'));
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->retryMax = max(0, (int) ($config['retry_max'] ?? 3));
        $this->retryBaseMs = max(0, (int) ($config['retry_base_ms'] ?? 500));
    }

    public function getMarketplaceId(): string
    {
        return $this->marketplaceId;
    }

    /**
     * GET a JSON resource.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * POST a JSON body. eBay answers many writes with 201/204 and an empty body, which decodes to [].
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = [], array $query = []): array
    {
        return $this->send('POST', $path, $query, $body);
    }

    /**
     * PUT a JSON body. On the Inventory API a PUT replaces the whole resource, so callers
     * must send the full, merged document.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function put(string $path, array $body = [], array $query = []): array
    {
        return $this->send('PUT', $path, $query, $body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->send('DELETE', $path, $query);
    }

    /**
     * Return a valid application token, minting one only when the cache is empty.
     */
    public function applicationToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->acceptJson()
            ->post('/identity/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => self::SCOPE,
            ]);

        $body = $this->decode($response, 'POST /identity/v1/oauth2/token');
        $token = (string) ($body['access_token'] ?? '');

        if ($token === '') {
            throw new \RuntimeException('eBay token response did not contain an access_token.');
        }

        $ttl = max(60, (int) ($body['expires_in'] ?? 7200) - self::TOKEN_EXPIRY_MARGIN);

        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * Run one request with the retry rules:
     *  - 401: drop/refresh the token and retry once;
     *  - 429: back off and retry (eBay did not process the call, so every verb is safe);
     *  - 5xx: back off and retry, but not for POST, which may have been half-applied.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $refreshed = false;
        $attempt = 0;

        while (true) {
            $response = $this->dispatch($method, $path, $query, $body);

            if ($response->status() === 401 && ! $refreshed) {
                $refreshed = true;
                $this->refreshToken();

                continue;
            }

            $retryable = $response->status() === 429 || ($response->serverError() && $method !== 'POST');

            if ($retryable && $attempt < $this->retryMax) {
                // Exponential backoff: base, 2x base, 4x base ...
                usleep((int) ($this->retryBaseMs * (2 ** $attempt)) * 1000);
                $attempt++;

                continue;
            }

            return $this->decode($response, $method.' '.$path);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function dispatch(string $method, string $path, array $query, ?array $body): Response
    {
        $request = $this->request($path);

        // The Inventory API refuses writes without a Content-Language header.
        if ($method !== 'GET' && str_starts_with($path, '/sell/inventory')) {
            $request = $request->withHeaders(['Content-Language' => 'en-US']);
        }

        return match ($method) {
            'GET' => $request->get($path, $query),
            'DELETE' => $request->delete($path.($query ? '?'.http_build_query($query) : '')),
            'POST' => $request->asJson()->post($path.($query ? '?'.http_build_query($query) : ''), $body ?? []),
            'PUT' => $request->asJson()->put($path.($query ? '?'.http_build_query($query) : ''), $body ?? []),
        };
    }

    private function request(string $path): PendingRequest
    {
        return Http::baseUrl($this->baseUrlFor($path))
            ->timeout($this->timeout)
            ->withToken($this->bearerToken())
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => $this->marketplaceId])
            ->acceptJson();
    }

    private function bearerToken(): string
    {
        if ($this->provider === null) {
            return $this->applicationToken();
        }

        // Seller mode: refresh ahead of expiry when we know it, otherwise use what is stored.
        $oauth = new EbayOAuthService($this->provider);
        if ($oauth->needsRefresh() && $oauth->hasRefreshToken()) {
            $this->provider = $oauth->refreshAccessToken();
        }

        $token = (string) $this->provider->getDecryptedAccessToken();

        if ($token === '') {
            throw new NotAllowedException(
                'eBay provider '.$this->provider->uuid.' is not connected yet. Connect the eBay account first.'
            );
        }

        return $token;
    }

    /**
     * After a 401: seller mode exchanges the refresh token, application mode drops the cached token.
     */
    private function refreshToken(): void
    {
        if ($this->provider === null) {
            $this->forgetToken();

            return;
        }

        $this->provider = (new EbayOAuthService($this->provider))->refreshAccessToken();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $operation): array
    {
        if ($response->successful()) {
            // 201/204 with an empty body decode to [].
            return (array) $response->json();
        }

        // eBay reports failures as {"errors":[{"errorId":..,"message":..}]}.
        $errors = (array) $response->json('errors', []);
        $message = $errors[0]['longMessage'] ?? $errors[0]['message']
            ?? $response->json('error_description')
            ?? $response->reason();

        Log::error(__METHOD__.' - eBay request failed', [
            'operation' => $operation,
            'status' => $response->status(),
            'errors' => $errors ?: $response->body(),
        ]);

        // 4xx other than auth/rate-limit means eBay disagrees with the data or account state:
        // surface it as a user error so callers can show it to the seller.
        if ($response->clientError() && ! in_array($response->status(), [401, 403, 429], true)) {
            throw new EbayUserException(sprintf('eBay %s: %s', $operation, $message), $response->status(), $errors);
        }

        $hint = match (true) {
            $response->status() === 429 => ' (rate limit reached — the Browse API allows 5,000 calls/day by default)',
            $response->serverError() => ' (eBay server error — retry later)',
            default => '',
        };

        throw new \RuntimeException(sprintf(
            'eBay %s failed with HTTP %d: %s%s',
            $operation,
            $response->status(),
            $message,
            $hint
        ));
    }

    private function baseUrl(): string
    {
        return $this->environment === 'sandbox'
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
    }

    /**
     * The Identity API lives on the apiz.* host; everything else on api.*.
     */
    private function baseUrlFor(string $path): string
    {
        if (str_starts_with($path, '/commerce/identity')) {
            return $this->environment === 'sandbox'
                ? 'https://apiz.sandbox.ebay.com'
                : 'https://apiz.ebay.com';
        }

        return $this->baseUrl();
    }

    private function tokenCacheKey(): string
    {
        // Keyed by the keyset too, so rotating the App ID or Cert ID never reuses a stale token.
        return 'ebay:app_token:'.$this->environment.':'.md5($this->clientId.':'.$this->clientSecret);
    }
}
