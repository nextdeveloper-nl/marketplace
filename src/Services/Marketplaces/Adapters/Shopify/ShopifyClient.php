<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\Marketplace\Database\Models\Providers;

/**
 * Thin GraphQL client for the Shopify Admin API.
 *
 * Hand-rolled on Illuminate\Http rather than the official SDK: `composer
 * require` cannot run in this application, so no new package can be installed.
 *
 * Four platform behaviours are handled here rather than in every caller:
 *
 *  1. Cost-based throttling. Shopify meters GraphQL by calculated query cost in
 *     a leaky bucket whose size varies per merchant plan (100/200/1000/2000
 *     points per second). Bucket sizes are read from the response rather than
 *     hardcoded — Shopify's own documentation example is stale.
 *  2. Idempotency. From API version 2026-04 the `@idempotent` directive is
 *     mandatory on inventory and refund mutations, and its absence fails at
 *     runtime rather than schema validation. Every mutation sent through
 *     mutate() carries a key, so no caller can forget.
 *  3. userErrors. Shopify returns HTTP 200 with an error payload, so a
 *     successful status code proves nothing.
 *  4. Cursor pagination, including a guard against the documented bug where
 *     `endCursor` stops advancing and the loop never terminates.
 */
class ShopifyClient
{
    /**
     * Admin API version used when a provider does not pin one.
     */
    public const DEFAULT_API_VERSION = '2026-07';

    /**
     * Stop consuming the bucket below this fraction of its capacity, and wait
     * for the leak to refill it instead of collecting 429s.
     */
    private const THROTTLE_FLOOR_RATIO = 0.15;

    /**
     * Attempts for a throttled or transiently failed request.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Hard ceiling on a single sleep, so a bad restoreRate cannot park a worker.
     */
    private const MAX_SLEEP_SECONDS = 20;

    private Providers $provider;

    private string $shopDomain;

    private string $apiVersion;

    private string $accessToken;

    private int $timeout = 30;

    /**
     * Last observed throttle state, refreshed on every response.
     *
     * @var array{maximumAvailable: float, currentlyAvailable: float, restoreRate: float}|null
     */
    private ?array $throttleStatus = null;

    public function __construct(Providers $provider)
    {
        $this->provider = $provider;

        $config = $this->config();

        $shopDomain = trim((string) ($config['shop_domain'] ?? ''));

        if ($shopDomain === '') {
            throw new NotFoundException(
                'Shopify provider '.$provider->uuid.' has no shop_domain in api_config.'
            );
        }

        $token = (string) $provider->getDecryptedAccessToken();

        if ($token === '') {
            throw new NotFoundException(
                'Shopify provider '.$provider->uuid.' has no access token. Connect the shop first.'
            );
        }

        $this->shopDomain = $this->normalizeShopDomain($shopDomain);
        $this->apiVersion = (string) ($config['api_version'] ?? self::DEFAULT_API_VERSION);
        $this->accessToken = $token;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Execute a GraphQL query and return its `data` payload.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables = []): array
    {
        return $this->send($query, $variables);
    }

    /**
     * Execute a GraphQL mutation with an idempotency key.
     *
     * $idempotencyKey must be derived deterministically from what is being
     * written — provider, entity, entity id, operation, payload hash — so that
     * a retried job reuses it. A random key on retry double-writes the refund
     * it was supposed to protect.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function mutate(string $mutation, array $variables, string $idempotencyKey): array
    {
        return $this->send($this->withIdempotentDirective($mutation, $idempotencyKey), $variables, $idempotencyKey);
    }

    /**
     * Root mutation fields Shopify requires `@idempotent` on.
     *
     * Since API 2026-04 these reject the call at runtime when the directive is
     * missing — the schema gives no warning, so it surfaces as a failed write
     * rather than a failed deploy. The list is explicit because the directive
     * is only *allowed* on these: attaching it to, say, fulfillmentCreate makes
     * Shopify reject that call instead.
     *
     * @var string[]
     */
    private const IDEMPOTENT_REQUIRED_FIELDS = [
        'inventorySetQuantities',
        'inventoryAdjustQuantities',
        'inventoryMoveQuantities',
        'inventorySetOnHandQuantities',
        'inventorySetScheduledChanges',
        'refundCreate',
        'orderCreateMandatePayment',
    ];

    /**
     * Attach `@idempotent` to the mutation field when Shopify demands it.
     *
     * The directive goes on the *field*, not on the operation — Shopify's own
     * error for the latter is "'@idempotent' can't be applied to mutations
     * (allowed: fields)". Sending the key only as an HTTP header is not enough
     * either: the header is accepted and then ignored, which looks like it
     * works right up until a retried refund pays a customer twice.
     *
     * Injected here rather than written into every document so that no caller
     * can forget it, and so the key stays derived from the operation instead of
     * being hand-copied into GraphQL text.
     */
    private function withIdempotentDirective(string $mutation, string $idempotencyKey): string
    {
        if ($idempotencyKey === '' || str_contains($mutation, '@idempotent')) {
            return $mutation;
        }

        // The root field is the first selection inside the operation body.
        if (! preg_match('/\bmutation\b[^{]*\{\s*(\w+)\s*(\([^)]*\))?\s*\{/', $mutation, $match)) {
            return $mutation;
        }

        if (! in_array($match[1], self::IDEMPOTENT_REQUIRED_FIELDS, true)) {
            return $mutation;
        }

        $replacement = rtrim($match[0], '{').'@idempotent(key: "'.$idempotencyKey.'") {';

        return str_replace($match[0], $replacement, $mutation);
    }

    /**
     * Walk every page of a paginated connection, yielding each node.
     *
     * @param  string  $query  Must accept `$cursor` and expose pageInfo.
     * @param  array<string, mixed>  $variables
     * @param  string  $connectionPath  Dot path to the connection, e.g. "products".
     * @return \Generator<int, array<string, mixed>>
     */
    public function paginate(string $query, array $variables, string $connectionPath): \Generator
    {
        $cursor = null;
        $seen = [];
        $pageCount = 0;

        do {
            $data = $this->send($query, array_merge($variables, ['cursor' => $cursor]));

            $connection = data_get($data, $connectionPath);

            if (! is_array($connection)) {
                Log::warning(__METHOD__.' - connection path missing in response', [
                    'provider_id' => $this->provider->id,
                    'path' => $connectionPath,
                ]);

                return;
            }

            foreach (($connection['edges'] ?? []) as $edge) {
                if (isset($edge['node'])) {
                    yield $edge['node'];
                }
            }

            $hasNext = (bool) data_get($connection, 'pageInfo.hasNextPage', false);
            $next = data_get($connection, 'pageInfo.endCursor');

            // Documented Shopify bug: endCursor stops advancing while
            // hasNextPage stays true, which spins forever. Stop instead.
            if ($hasNext && $next !== null && isset($seen[$next])) {
                Log::error(__METHOD__.' - endCursor repeated, aborting pagination', [
                    'provider_id' => $this->provider->id,
                    'path' => $connectionPath,
                    'cursor' => $next,
                    'pages' => $pageCount,
                ]);

                return;
            }

            if ($next !== null) {
                $seen[$next] = true;
            }

            $cursor = $next;
            $pageCount++;
        } while ($hasNext && $cursor !== null);
    }

    /**
     * Verify the credentials by asking the shop to identify itself.
     */
    public function authenticate(): bool
    {
        try {
            $data = $this->query('query { shop { id name myshopifyDomain currencyCode } }');

            return data_get($data, 'shop.id') !== null;
        } catch (\Throwable $e) {
            Log::warning(__METHOD__.' - Shopify authentication failed', [
                'provider_id' => $this->provider->id,
                'shop' => $this->shopDomain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Last observed throttle state, for logging and for sync pacing decisions.
     *
     * @return array<string, float>|null
     */
    public function getThrottleStatus(): ?array
    {
        return $this->throttleStatus;
    }

    public function getShopDomain(): string
    {
        return $this->shopDomain;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Send one GraphQL document, retrying on throttling and transient failures.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function send(string $document, array $variables = [], ?string $idempotencyKey = null): array
    {
        $body = ['query' => $document];

        if ($variables !== []) {
            $body['variables'] = $variables;
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            $this->awaitCapacity();

            $response = $this->client($idempotencyKey)->post('/graphql.json', $body);

            $json = $response->json();
            $json = is_array($json) ? $json : [];

            $this->rememberThrottleStatus($json);

            // Transport-level throttling.
            if ($response->status() === 429) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new \RuntimeException('Shopify rate limited the request after '.$attempt.' attempts.');
                }

                $this->sleepSeconds($this->retryAfterSeconds($response->header('Retry-After')));

                continue;
            }

            if ($response->serverError()) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new \RuntimeException(
                        'Shopify returned HTTP '.$response->status().': '.$response->body()
                    );
                }

                $this->sleepSeconds(min(2 ** $attempt, self::MAX_SLEEP_SECONDS));

                continue;
            }

            if ($response->clientError()) {
                throw new \RuntimeException(
                    'Shopify rejected the request with HTTP '.$response->status().': '.$response->body()
                );
            }

            // GraphQL-level throttling arrives as HTTP 200 with a THROTTLED code.
            if ($this->isThrottled($json)) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new \RuntimeException('Shopify throttled the query after '.$attempt.' attempts.');
                }

                $this->sleepSeconds($this->secondsUntilAffordable($this->requestedCost($json)));

                continue;
            }

            $this->assertNoErrors($json);

            $data = $json['data'] ?? [];

            $this->assertNoUserErrors(is_array($data) ? $data : []);

            return is_array($data) ? $data : [];
        }
    }

    /**
     * Build the HTTP client.
     *
     * The key also travels as a header. That alone does not satisfy Shopify —
     * the `@idempotent` directive on the operation is what counts — but it
     * keeps the key visible in request logs and to any proxy in between.
     */
    private function client(?string $idempotencyKey = null): PendingRequest
    {
        $headers = [
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ];

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return Http::baseUrl(sprintf('https://%s/admin/api/%s', $this->shopDomain, $this->apiVersion))
            ->timeout($this->timeout)
            ->withHeaders($headers)
            ->acceptJson();
    }

    /**
     * Pause before sending when the bucket is nearly empty, so we pace ourselves
     * instead of relying on 429s to tell us we went too fast.
     */
    private function awaitCapacity(): void
    {
        if ($this->throttleStatus === null) {
            return;
        }

        $max = $this->throttleStatus['maximumAvailable'];
        $current = $this->throttleStatus['currentlyAvailable'];

        if ($max <= 0) {
            return;
        }

        if (($current / $max) >= self::THROTTLE_FLOOR_RATIO) {
            return;
        }

        $target = $max * self::THROTTLE_FLOOR_RATIO;

        $this->sleepSeconds($this->secondsToRestore($target - $current));
    }

    /**
     * Seconds of leak needed to afford a query of the given cost.
     */
    private function secondsUntilAffordable(?float $cost): float
    {
        if ($this->throttleStatus === null) {
            return 1.0;
        }

        $needed = ($cost ?? $this->throttleStatus['maximumAvailable'] * 0.1)
            - $this->throttleStatus['currentlyAvailable'];

        return $this->secondsToRestore($needed);
    }

    /**
     * Convert a points deficit into seconds, using the shop's restore rate.
     */
    private function secondsToRestore(float $points): float
    {
        if ($points <= 0) {
            return 0.0;
        }

        $rate = $this->throttleStatus['restoreRate'] ?? 0.0;

        if ($rate <= 0) {
            return 1.0;
        }

        return min($points / $rate, self::MAX_SLEEP_SECONDS);
    }

    /**
     * Record the bucket state Shopify reports. Never hardcode these numbers:
     * they differ per merchant plan and have been raised without notice.
     *
     * @param  array<string, mixed>  $json
     */
    private function rememberThrottleStatus(array $json): void
    {
        $status = data_get($json, 'extensions.cost.throttleStatus');

        if (! is_array($status)) {
            return;
        }

        $this->throttleStatus = [
            'maximumAvailable' => (float) ($status['maximumAvailable'] ?? 0),
            'currentlyAvailable' => (float) ($status['currentlyAvailable'] ?? 0),
            'restoreRate' => (float) ($status['restoreRate'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function requestedCost(array $json): ?float
    {
        $cost = data_get($json, 'extensions.cost.requestedQueryCost');

        return $cost === null ? null : (float) $cost;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function isThrottled(array $json): bool
    {
        foreach (($json['errors'] ?? []) as $error) {
            if (data_get($error, 'extensions.code') === 'THROTTLED') {
                return true;
            }
        }

        return false;
    }

    /**
     * Top-level GraphQL errors: malformed document, missing scope, bad version.
     *
     * @param  array<string, mixed>  $json
     */
    private function assertNoErrors(array $json): void
    {
        $errors = $json['errors'] ?? [];

        if ($errors === [] || ! is_array($errors)) {
            return;
        }

        $messages = array_map(
            static fn ($error) => (string) ($error['message'] ?? 'unknown error'),
            $errors
        );

        throw new \RuntimeException('Shopify GraphQL error: '.implode('; ', $messages));
    }

    /**
     * Mutation-level errors. Shopify reports these inside a 200 response, so
     * without this check a failed write looks exactly like a successful one.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoUserErrors(array $data): void
    {
        foreach ($data as $field => $payload) {
            if (! is_array($payload)) {
                continue;
            }

            // Older mutations expose `userErrors`; newer typed ones use a
            // mutation-specific key such as `inventorySetQuantitiesUserErrors`.
            foreach ($payload as $key => $value) {
                if (! is_array($value) || $value === []) {
                    continue;
                }

                if ($key !== 'userErrors' && ! str_ends_with($key, 'UserErrors')) {
                    continue;
                }

                $messages = [];

                foreach ($value as $error) {
                    $fieldPath = data_get($error, 'field');
                    $fieldPath = is_array($fieldPath) ? implode('.', $fieldPath) : (string) $fieldPath;

                    $messages[] = trim(sprintf(
                        '%s%s [%s]',
                        $fieldPath !== '' ? $fieldPath.': ' : '',
                        (string) data_get($error, 'message', 'unknown error'),
                        (string) data_get($error, 'code', '-')
                    ));
                }

                throw new ShopifyUserException(
                    sprintf('Shopify %s failed: %s', $field, implode('; ', $messages)),
                    $value
                );
            }
        }
    }

    /**
     * `Retry-After` is documented in seconds and may be fractional.
     */
    private function retryAfterSeconds(?string $header): float
    {
        if ($header === null || ! is_numeric($header)) {
            return 2.0;
        }

        return min((float) $header, self::MAX_SLEEP_SECONDS);
    }

    private function sleepSeconds(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        usleep((int) round(min($seconds, self::MAX_SLEEP_SECONDS) * 1_000_000));
    }

    /**
     * Accept "acme", "acme.myshopify.com" or a full URL and return the host.
     */
    private function normalizeShopDomain(string $domain): string
    {
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = rtrim(explode('/', $domain)[0], '.');

        if (! str_contains($domain, '.')) {
            $domain .= '.myshopify.com';
        }

        return strtolower($domain);
    }

    /**
     * Decoded api_config for this provider.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $config = $this->provider->api_config;

        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        return is_array($config) ? $config : [];
    }
}
