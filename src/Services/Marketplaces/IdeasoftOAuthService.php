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
use NextDeveloper\Marketplace\Services\ProvidersService;

/**
 * Ideasoft OAuth2 authorization-code flow (3-legged).
 *
 *  1. buildAuthorizationUrl()  - authenticated user asks to connect a store; we return the
 *     store's authorize URL carrying a one-time `state` that maps back to the provider.
 *  2. handleCallback()         - Ideasoft redirects the browser back (unauthenticated) with
 *     `code` + `state`; we resolve the provider from `state` and exchange the code for tokens.
 *  3. refreshAccessToken()     - exchanges the stored refresh token for a new access token.
 *
 * Tokens: access token goes to the encrypted access_token_enc column. The refresh token and
 * expiry have no dedicated column, so they live in api_config['oauth'] with the refresh token
 * encrypted (api_config itself is plain json).
 *
 * NOTE: the default authorize/token paths below follow Ideasoft's documented convention but
 * were not confirmed against a live store. Each can be overridden per provider through
 * api_config ('oauth_authorize_url', 'oauth_token_url', 'oauth_redirect_uri') without a deploy.
 */
class IdeasoftOAuthService
{
    /** How long a pending authorization (state) stays valid, in minutes. */
    public const STATE_TTL_MINUTES = 15;

    /** Refresh this many seconds before the access token actually expires. */
    public const REFRESH_SKEW_SECONDS = 120;

    private const STATE_CACHE_PREFIX = 'marketplace:ideasoft:oauth:state:';

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
     * Build the URL the browser must be sent to so the merchant can approve the app.
     */
    public function buildAuthorizationUrl(): string
    {
        $config = $this->provider->getApiConfigArray();

        $clientId = trim((string) ($config['client_id'] ?? ''));
        if ($clientId === '') {
            throw new NotFoundException('Ideasoft provider '.$this->provider->uuid.' has no client_id in api_config.');
        }

        // One-time, unguessable state; maps back to the provider on callback (CSRF protection).
        $state = Str::random(40);
        Cache::put(
            self::STATE_CACHE_PREFIX.$state,
            $this->provider->id,
            now()->addMinutes(self::STATE_TTL_MINUTES)
        );

        return $this->authorizeUrl($config).'?'.http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $this->redirectUri($config),
        ]);
    }

    /**
     * Exchange an authorization code for tokens and store them on the provider.
     */
    public function handleCallback(string $code): Providers
    {
        $tokens = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri($this->provider->getApiConfigArray()),
        ]);

        return $this->storeTokens($tokens);
    }

    /**
     * Use the stored refresh token to obtain a new access token.
     */
    public function refreshAccessToken(): Providers
    {
        $refreshToken = $this->storedRefreshToken();

        if ($refreshToken === null) {
            throw new NotAllowedException(
                'Ideasoft provider '.$this->provider->uuid.' has no refresh token. Reconnect the store.'
            );
        }

        $tokens = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
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
     * POST to the token endpoint with the app credentials.
     *
     * @return array<string, mixed>
     */
    private function requestToken(array $params): array
    {
        $config = $this->provider->getApiConfigArray();

        // Added: fail early and clearly when the secret is missing (e.g. blanked by a later edit),
        // instead of sending an empty secret and getting a generic invalid_client back.
        if ((string) $this->provider->getDecryptedClientSecret() === '') {
            Log::error(__METHOD__.' - Ideasoft provider has no client secret stored', [
                'provider_id' => $this->provider->id,
            ]);

            throw new NotAllowedException(
                'Ideasoft provider '.$this->provider->uuid.' has no client secret. Save the client secret and connect again.'
            );
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post($this->tokenUrl($config), $params + [
                    'client_id' => trim((string) ($config['client_id'] ?? '')),
                    'client_secret' => (string) $this->provider->getDecryptedClientSecret(),
                ]);
        } catch (\Throwable $e) {
            // Network / DNS / TLS problem before Ideasoft answered.
            Log::error(__METHOD__.' - Ideasoft token request could not be sent', [
                'provider_id' => $this->provider->id,
                'grant_type' => $params['grant_type'] ?? null,
                'token_url' => $this->tokenUrl($config),
                'exception' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $body = $response->json();

        if (! $response->successful() || empty($body['access_token'])) {
            // Never log the request (it carries the client secret). The failed response body is
            // safe to log: it holds Ideasoft's error, not tokens. Truncated to keep the log small.
            // Note: the key is not named "message" because that collides with the log's own field.
            Log::error(__METHOD__.' - Ideasoft token request failed', [
                'provider_id' => $this->provider->id,
                'grant_type' => $params['grant_type'] ?? null,
                'token_url' => $this->tokenUrl($config),
                'redirect_uri' => $params['redirect_uri'] ?? null,
                'status' => $response->status(),
                'error' => $body['error'] ?? null,
                'error_description' => $body['error_description'] ?? null,
                'response_body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            throw new NotAllowedException(
                'Ideasoft rejected the token request ('.($body['error'] ?? 'HTTP '.$response->status()).').'
            );
        }

        // Success trail without any token values.
        Log::info(__METHOD__.' - Ideasoft token request succeeded', [
            'provider_id' => $this->provider->id,
            'grant_type' => $params['grant_type'] ?? null,
            'expires_in' => $body['expires_in'] ?? null,
            'scope' => $body['scope'] ?? null,
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

        // Ideasoft may omit refresh_token on refresh; keep the old one in that case.
        if (! empty($tokens['refresh_token'])) {
            $oauth['refresh_token_enc'] = Crypt::encryptString($tokens['refresh_token']);
        }

        $config['oauth'] = $oauth;

        return ProvidersService::storeOAuthTokens($this->provider, $tokens['access_token'], $config);
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

    private function authorizeUrl(array $config): string
    {
        return $config['oauth_authorize_url'] ?? 'https://'.$this->storeDomain($config).'/panel/auth';
    }

    private function tokenUrl(array $config): string
    {
        return $config['oauth_token_url'] ?? 'https://'.$this->storeDomain($config).'/oauth/v2/token';
    }

    /**
     * Must match, character for character, the redirect URI registered in the Ideasoft app.
     *
     * Resolution order: per-provider api_config.oauth_redirect_uri, then the
     * IDEASOFT_REDIRECT_URI env (config marketplace.ideasoft_redirect_uri), then the URL of the
     * current request. config('app.url') is deliberately not used: it is not reliably a full
     * public URL (it can be a bare host or an internal name), which produced a wrong redirect_uri.
     */
    private function redirectUri(array $config): string
    {
        $uri = $config['oauth_redirect_uri']
            ?? config('marketplace.ideasoft_redirect_uri')
            ?? url('/public/marketplace/ideasoft/callback');

        // Behind a TLS-terminating proxy the request can look like http; Ideasoft apps use https.
        if (str_starts_with($uri, 'http://') && ! str_contains($uri, 'localhost')) {
            $uri = 'https://'.substr($uri, 7);
        }

        return $uri;
    }

    private function storeDomain(array $config): string
    {
        $domain = trim((string) ($config['store_domain'] ?? ''));

        if ($domain === '') {
            throw new NotFoundException('Ideasoft provider '.$this->provider->uuid.' has no store_domain in api_config.');
        }

        return $domain;
    }
}
