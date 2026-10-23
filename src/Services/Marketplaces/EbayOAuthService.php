<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayAdapter;
use NextDeveloper\Marketplace\Services\ProvidersService;

/**
 * eBay OAuth2 authorization-code flow (seller consent, 3-legged).
 *
 *  1. buildAuthorizationUrl()  - authenticated user asks to connect an eBay seller account; we
 *     return eBay's consent URL carrying a one-time `state` that maps back to the provider.
 *  2. handleCallback()         - eBay redirects the seller's browser back (unauthenticated) with
 *     `code` + `state`; we resolve the provider from `state` and exchange the code for tokens.
 *  3. refreshAccessToken()     - exchanges the stored refresh token for a new access token.
 *
 * Differences from Ideasoft worth knowing:
 *  - The app keyset (App ID / Cert ID) is platform-wide and comes from config('marketplace.ebay'),
 *    not from the provider row. The provider only holds the seller's tokens.
 *  - eBay does not take a redirect URL: `redirect_uri` is the RuName registered in the eBay
 *    developer portal, which maps to our accept URL server-side.
 *  - Client credentials are sent as HTTP Basic auth on the token endpoint.
 *
 * Tokens: the ~2h access token goes to the encrypted access_token_enc column. The refresh token
 * (valid ~18 months) and the expiry timestamps live in api_config['oauth'], with the refresh
 * token encrypted (api_config itself is plain json). When the refresh token lapses the seller must
 * consent again; refresh_token_expires_at is stored so that can be warned about beforehand.
 */
class EbayOAuthService
{
    /** How long a pending authorization (state) stays valid, in minutes. */
    public const STATE_TTL_MINUTES = 15;

    /** Refresh this many seconds before the access token actually expires. */
    public const REFRESH_SKEW_SECONDS = 120;

    private const STATE_CACHE_PREFIX = 'marketplace:ebay:oauth:state:';

    public function __construct(private readonly Providers $provider) {}

    /**
     * Resolve the provider a callback `state` belongs to, consuming the state.
     * The lookup bypasses AuthorizationScope on purpose: the callback has no logged-in user.
     */
    public static function resolveProviderFromState(string $state): ?Providers
    {
        $providerId = Cache::pull(self::STATE_CACHE_PREFIX.$state);

        if (! $providerId) {
            return null;
        }

        return Providers::withoutGlobalScopes()->where('id', $providerId)->first();
    }

    /**
     * Build the URL the browser must be sent to so the seller can grant access.
     */
    public function buildAuthorizationUrl(): string
    {
        $config = $this->appConfig();

        // One-time, unguessable state; maps back to the provider on callback (CSRF protection).
        $state = Str::random(40);
        Cache::put(
            self::STATE_CACHE_PREFIX.$state,
            $this->provider->id,
            now()->addMinutes(self::STATE_TTL_MINUTES)
        );

        return $this->authorizeBase().'?'.http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->ruName($config),
            'response_type' => 'code',
            'scope' => $this->scopes($config),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for tokens and store them on the provider.
     */
    public function handleCallback(string $code): Providers
    {
        $tokens = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->ruName($this->appConfig()),
        ]);

        $provider = $this->storeTokens($tokens);

        return $this->recordSellerIdentity($provider, $tokens['access_token']);
    }

    /**
     * Use the stored refresh token to obtain a new access token.
     */
    public function refreshAccessToken(): Providers
    {
        $refreshToken = $this->storedRefreshToken();

        if ($refreshToken === null) {
            throw new NotAllowedException(
                'eBay provider '.$this->provider->uuid.' has no refresh token. Reconnect the eBay account.'
            );
        }

        $tokens = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => $this->scopes($this->appConfig()),
        ]);

        return $this->storeTokens($tokens);
    }

    /**
     * Whether the stored access token is expired or about to be.
     */
    public function needsRefresh(): bool
    {
        $expiresAt = $this->provider->getApiConfigArray()['oauth']['expires_at'] ?? null;

        if (! $expiresAt) {
            return false;
        }

        return Carbon::parse($expiresAt)->subSeconds(self::REFRESH_SKEW_SECONDS)->isPast();
    }

    public function hasRefreshToken(): bool
    {
        return $this->storedRefreshToken() !== null;
    }

    /**
     * Whether the refresh token itself has lapsed, i.e. the seller has to consent again.
     */
    public function refreshTokenExpired(): bool
    {
        $expiresAt = $this->provider->getApiConfigArray()['oauth']['refresh_token_expires_at'] ?? null;

        return $expiresAt !== null && Carbon::parse($expiresAt)->isPast();
    }

    /**
     * POST to the token endpoint with the app credentials as HTTP Basic auth.
     *
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    private function requestToken(array $params): array
    {
        $config = $this->appConfig();

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) ($config['timeout'] ?? 30))
                ->withBasicAuth($config['client_id'], $config['client_secret'])
                ->post($this->tokenUrl(), $params);
        } catch (\Throwable $e) {
            // Network / DNS / TLS problem before eBay answered.
            Log::error(__METHOD__.' - eBay token request could not be sent', [
                'provider_id' => $this->provider->id,
                'grant_type' => $params['grant_type'] ?? null,
                'exception' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $body = $response->json();

        if (! $response->successful() || empty($body['access_token'])) {
            // Never log the request (it carries the code / refresh token). The failed response body
            // is safe to log: it holds eBay's error, not tokens. Truncated to keep the log small.
            Log::error(__METHOD__.' - eBay token request failed', [
                'provider_id' => $this->provider->id,
                'grant_type' => $params['grant_type'] ?? null,
                'status' => $response->status(),
                'error' => $body['error'] ?? null,
                'error_description' => $body['error_description'] ?? null,
                'response_body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            throw new NotAllowedException(
                'eBay rejected the token request ('.($body['error'] ?? 'HTTP '.$response->status()).').'
            );
        }

        // Success trail without any token values.
        Log::info(__METHOD__.' - eBay token request succeeded', [
            'provider_id' => $this->provider->id,
            'grant_type' => $params['grant_type'] ?? null,
            'expires_in' => $body['expires_in'] ?? null,
            'has_refresh_token' => ! empty($body['refresh_token']),
        ]);

        return $body;
    }

    /**
     * Persist tokens through ProvidersService (no direct model writes).
     *
     * @param  array<string, mixed>  $tokens
     */
    private function storeTokens(array $tokens): Providers
    {
        $config = $this->provider->getApiConfigArray();

        $oauth = $config['oauth'] ?? [];
        $oauth['expires_at'] = isset($tokens['expires_in'])
            ? now()->addSeconds((int) $tokens['expires_in'])->toIso8601String()
            : null;
        $oauth['connected_at'] = $oauth['connected_at'] ?? now()->toIso8601String();
        $oauth['refreshed_at'] = now()->toIso8601String();

        // eBay returns the refresh token only on the code exchange; the refresh grant keeps the old one.
        if (! empty($tokens['refresh_token'])) {
            $oauth['refresh_token_enc'] = Crypt::encryptString($tokens['refresh_token']);

            if (isset($tokens['refresh_token_expires_in'])) {
                $oauth['refresh_token_expires_at'] = now()->addSeconds((int) $tokens['refresh_token_expires_in'])->toIso8601String();
            }
        }

        $config['oauth'] = $oauth;

        return ProvidersService::storeOAuthTokens($this->provider, $tokens['access_token'], $config);
    }

    /**
     * Best-effort: ask eBay who just connected and keep the username in api_config['ebay'] so the
     * panel can show which seller account is linked. A failure here never fails the connection.
     */
    private function recordSellerIdentity(Providers $provider, string $accessToken): Providers
    {
        try {
            $identity = (new EbayAdapter($provider))->identity();

            if (empty($identity['username'])) {
                return $provider;
            }

            $config = $provider->getApiConfigArray();
            $config['ebay']['seller_username'] = $identity['username'];
            $config['ebay']['seller_user_id'] = $identity['userId'] ?? null;

            // Same bypassed write as the token storage: the callback has no logged-in user.
            return ProvidersService::storeOAuthTokens($provider, $accessToken, $config);
        } catch (\Throwable $e) {
            Log::warning(__METHOD__.' - could not read eBay seller identity', [
                'provider_id' => $provider->id,
                'exception' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            return $provider;
        }
    }

    private function storedRefreshToken(): ?string
    {
        $enc = $this->provider->getApiConfigArray()['oauth']['refresh_token_enc'] ?? null;

        if (! $enc) {
            return null;
        }

        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            Log::error(__METHOD__.' - cannot decrypt refresh token', ['provider_id' => $this->provider->id]);

            return null;
        }
    }

    /**
     * The platform eBay keyset, failing early and clearly when it is not configured.
     *
     * @return array<string, mixed>
     */
    private function appConfig(): array
    {
        $config = (array) config('marketplace.ebay', []);

        $config['client_id'] = trim((string) ($config['client_id'] ?? ''));
        $config['client_secret'] = trim((string) ($config['client_secret'] ?? ''));

        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            throw new NotFoundException(
                'eBay is not configured. Set EBAY_CLIENT_ID (App ID) and EBAY_CLIENT_SECRET (Cert ID).'
            );
        }

        return $config;
    }

    private function isSandbox(): bool
    {
        return strtolower((string) config('marketplace.ebay.environment', 'production')) === 'sandbox';
    }

    private function authorizeBase(): string
    {
        return $this->isSandbox()
            ? 'https://auth.sandbox.ebay.com/oauth2/authorize'
            : 'https://auth.ebay.com/oauth2/authorize';
    }

    private function tokenUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';
    }

    /**
     * The RuName registered for the active environment. The sandbox keyset has its own.
     *
     * @param  array<string, mixed>  $config
     */
    private function ruName(array $config): string
    {
        $ruName = trim((string) ($config['ru_name'] ?? ''));

        // Sandbox prefers its own RuName and falls back to the shared one.
        if ($this->isSandbox() && trim((string) ($config['ru_name_sandbox'] ?? '')) !== '') {
            $ruName = trim((string) $config['ru_name_sandbox']);
        }

        if ($ruName === '') {
            throw new NotFoundException(
                'eBay RuName is not configured. Set '.($this->isSandbox() ? 'EBAY_RU_NAME_SANDBOX' : 'EBAY_RU_NAME').'.'
            );
        }

        return $ruName;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scopes(array $config): string
    {
        return trim((string) ($config['oauth_scopes'] ?? ''));
    }
}
