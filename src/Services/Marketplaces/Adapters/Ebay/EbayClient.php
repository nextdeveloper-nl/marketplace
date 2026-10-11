<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotFoundException;

/**
 * Thin REST client for eBay's application-level APIs (Browse).
 *
 * Hand-rolled on Illuminate\Http for the same reason as ShopifyClient: no new
 * package can be installed in this application.
 *
 * Authenticates with an application token (OAuth client-credentials grant).
 * That token represents our keyset, not a seller, so it is shared across the
 * whole platform and cached: eBay rate-limits how many tokens a keyset may mint
 * per day, and minting one per request would exhaust that long before the
 * Browse call quota.
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

    public function __construct(?string $marketplaceId = null)
    {
        $config = (array) config('marketplace.ebay', []);

        $this->clientId = trim((string) ($config['client_id'] ?? ''));
        $this->clientSecret = trim((string) ($config['client_secret'] ?? ''));

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new NotFoundException(
                'eBay is not configured. Set EBAY_CLIENT_ID (App ID) and EBAY_CLIENT_SECRET (Cert ID).'
            );
        }

        $this->environment = strtolower((string) ($config['environment'] ?? 'production')) === 'sandbox'
            ? 'sandbox'
            : 'production';
        $this->marketplaceId = $marketplaceId ?: (string) ($config['marketplace_id'] ?? 'EBAY_US');
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    public function getMarketplaceId(): string
    {
        return $this->marketplaceId;
    }

    /**
     * GET a JSON resource. A 401 means the cached token was revoked or expired
     * early, so it is dropped and the call retried once with a fresh one.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->request()->get($path, $query);

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $this->request()->get($path, $query);
        }

        return $this->decode($response, 'GET '.$path);
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

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout)
            ->withToken($this->applicationToken())
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => $this->marketplaceId])
            ->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $operation): array
    {
        if ($response->successful()) {
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

    private function tokenCacheKey(): string
    {
        // Keyed by the keyset too, so rotating the App ID or Cert ID never reuses a stale token.
        return 'ebay:app_token:'.$this->environment.':'.md5($this->clientId.':'.$this->clientSecret);
    }
}
