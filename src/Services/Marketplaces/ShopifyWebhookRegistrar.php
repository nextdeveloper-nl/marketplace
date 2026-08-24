<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Facades\Log;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;

/**
 * Registers and repairs a shop's webhook subscriptions.
 *
 * This exists because of one Shopify behaviour: after eight consecutive
 * delivery failures Shopify deletes the subscription outright and tells nobody
 * but the app's emergency developer email. A forty-minute outage can therefore
 * unsubscribe us from a merchant's orders permanently, and the first person to
 * notice is the merchant. Reconciling the live subscription list against the
 * topics we need — daily, not just at install — is what stops that being a
 * silent, unbounded data-loss window.
 */
class ShopifyWebhookRegistrar
{
    /**
     * Where Shopify should deliver. Compliance topics are configured on the app
     * itself (shopify.app.toml) rather than per shop, so they are not created
     * here even though the same endpoint serves them.
     */
    public const ENDPOINT_PATH = '/public/marketplace/shopify/webhook';

    public function __construct(private readonly ShopifyService $service) {}

    /**
     * Subscriptions currently registered for this app on this shop.
     *
     * @return array<int, array{id: string, topic: string, callbackUrl: string|null}>
     */
    public function list(): array
    {
        $out = [];

        foreach ($this->service->getAdapter()->getClient()->paginate(ShopifyGraphQL::WEBHOOK_LIST, [], 'webhookSubscriptions') as $node) {
            $out[] = [
                'id' => (string) data_get($node, 'id'),
                'topic' => (string) data_get($node, 'topic'),
                'callbackUrl' => data_get($node, 'endpoint.callbackUrl'),
            ];
        }

        return $out;
    }

    /**
     * Create every missing subscription, and report what was already there.
     *
     * @param  string[]|null  $topics  Defaults to the adapter's supported topics.
     * @return array{callback_url: string, created: string[], existing: string[], failed: array<string, string>, foreign: array<int, array<string, mixed>>}
     */
    public function reconcile(?array $topics = null, bool $dryRun = false): array
    {
        $callbackUrl = $this->callbackUrl();
        $topics ??= ShopifyAdapter::WEBHOOK_TOPICS;

        $existing = $this->list();

        $mine = [];
        $foreign = [];

        foreach ($existing as $subscription) {
            if ($subscription['callbackUrl'] === $callbackUrl) {
                $mine[] = $subscription['topic'];

                continue;
            }

            // Another environment's endpoint (a teammate's tunnel, a stale
            // staging URL) on the same app. Reported, never deleted: deleting
            // it would silently break whoever owns it.
            $foreign[] = $subscription;
        }

        $created = [];
        $failed = [];

        foreach ($topics as $topic) {
            if (in_array($topic, $mine, true)) {
                continue;
            }

            if ($dryRun) {
                $created[] = $topic;

                continue;
            }

            try {
                $this->create($topic, $callbackUrl);
                $created[] = $topic;
            } catch (\Throwable $e) {
                $failed[$topic] = $e->getMessage();
            }
        }

        if ($created !== [] || $failed !== []) {
            Log::info(__METHOD__.' - webhook subscriptions reconciled', [
                'provider_id' => $this->service->provider->id,
                'callback_url' => $callbackUrl,
                'created' => $created,
                'failed' => $failed,
                'dry_run' => $dryRun,
            ]);
        }

        return [
            'callback_url' => $callbackUrl,
            'created' => $created,
            'existing' => $mine,
            'failed' => $failed,
            'foreign' => $foreign,
        ];
    }

    /**
     * @throws \RuntimeException When Shopify rejects the subscription.
     */
    public function create(string $topic, string $callbackUrl): string
    {
        $client = $this->service->getAdapter()->getClient();

        $data = $client->mutate(
            ShopifyGraphQL::WEBHOOK_CREATE,
            [
                'topic' => $topic,
                'subscription' => ['callbackUrl' => $callbackUrl, 'format' => 'JSON'],
            ],
            // Deterministic: retrying a create for the same shop, topic and URL
            // must not produce a second subscription.
            substr(hash('sha256', $this->service->provider->id.':webhook:'.$topic.':'.$callbackUrl), 0, 36)
        );

        $id = data_get($data, 'webhookSubscriptionCreate.webhookSubscription.id');

        if (! $id) {
            throw new \RuntimeException('Shopify did not return a subscription id for '.$topic);
        }

        return (string) $id;
    }

    public function delete(string $subscriptionId): bool
    {
        $data = $this->service->getAdapter()->getClient()->mutate(
            ShopifyGraphQL::WEBHOOK_DELETE,
            ['id' => $subscriptionId],
            substr(hash('sha256', 'webhook-delete:'.$subscriptionId), 0, 36)
        );

        return (bool) data_get($data, 'webhookSubscriptionDelete.deletedWebhookSubscriptionId');
    }

    /**
     * The HTTPS endpoint Shopify will POST to.
     *
     * Shopify refuses plaintext and cannot reach a private host, so an
     * unreachable APP_URL is caught here with an explanation rather than as an
     * opaque rejection from the API. api_config.webhook_url overrides it, which
     * is how a developer points one shop at a tunnel.
     *
     * @throws \RuntimeException When no deliverable URL is configured.
     */
    public function callbackUrl(): string
    {
        $config = $this->service->provider->getApiConfigArray();

        $base = rtrim((string) ($config['webhook_url'] ?? config('app.url', '')), '/');

        if ($base === '') {
            throw new \RuntimeException('No APP_URL or api_config.webhook_url configured for this Shopify connection.');
        }

        $url = str_contains($base, self::ENDPOINT_PATH) ? $base : $base.self::ENDPOINT_PATH;

        if (! str_starts_with($url, 'https://')) {
            throw new \RuntimeException(
                'Shopify only delivers webhooks over HTTPS to a publicly reachable host; got "'.$url.'". '
                .'Set api_config.webhook_url on the provider to a tunnel (cloudflared/ngrok) for local development.'
            );
        }

        return $url;
    }
}
