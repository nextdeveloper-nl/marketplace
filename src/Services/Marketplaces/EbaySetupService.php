<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayClient;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayUserException;
use NextDeveloper\Marketplace\Services\ProvidersService;

/**
 * Post-connect setup for an eBay seller: everything eBay requires before a listing can exist.
 *
 * eBay will not publish an offer without a fulfillment, payment and return business policy, a
 * merchant location, and a category tree for the marketplace. This service reads what the seller
 * already has, lets the panel pick from it (or create a location), and stores the selection in
 * api_config['ebay'] on the provider. Import (read-only) does not need this; push/create does.
 *
 * The stored shape:
 *   api_config.ebay = {
 *     marketplace_id, seller_username, category_tree_id, merchant_location_key,
 *     policies: {fulfillment_policy_id, payment_policy_id, return_policy_id},
 *     default_condition
 *   }
 */
class EbaySetupService
{
    private EbayClient $client;

    public function __construct(private readonly Providers $provider)
    {
        $this->client = new EbayClient(null, $provider);
    }

    /**
     * Connection health for the panel: whether tokens exist, when they lapse, and (live) which
     * seller eBay says the token belongs to. Never throws: a dead connection is a status, not an error.
     *
     * @return array<string, mixed>
     */
    public function connectionStatus(): array
    {
        $oauth = new EbayOAuthService($this->provider);
        $stored = $this->provider->getApiConfigArray();

        $status = [
            'provider_id' => $this->provider->uuid,
            'connected' => (string) $this->provider->getDecryptedAccessToken() !== '',
            'has_refresh_token' => $oauth->hasRefreshToken(),
            'refresh_token_expired' => $oauth->refreshTokenExpired(),
            'refresh_token_expires_at' => $stored['oauth']['refresh_token_expires_at'] ?? null,
            'marketplace_id' => $this->client->getMarketplaceId(),
            'seller_username' => $stored['ebay']['seller_username'] ?? null,
            'verified' => false,
        ];

        if ($status['connected']) {
            try {
                $identity = (new EbayAdapter($this->provider))->identity();
                $status['verified'] = $identity !== [];
                $status['seller_username'] = $identity['username'] ?? $status['seller_username'];
            } catch (\Throwable $e) {
                Log::warning(__METHOD__.' - eBay identity check failed', [
                    'provider_id' => $this->provider->id,
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $status;
    }

    /**
     * Everything the setup screen needs in one call: the seller's policies and locations to pick
     * from, the marketplace's category tree, and what is already saved.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        // A seller not yet in the policy program would otherwise see empty policy lists.
        $this->ensurePolicyProgram();

        return [
            'marketplace_id' => $this->client->getMarketplaceId(),
            'policies' => $this->policies(),
            'locations' => $this->locations(),
            'category_tree_id' => $this->categoryTreeId(),
            'saved' => $this->provider->getApiConfigArray()['ebay'] ?? [],
        ];
    }

    /**
     * The seller's existing business policies on this marketplace, reduced to what a picker needs.
     *
     * @return array{fulfillment: array<int, array{id: string, name: string}>, payment: array<int, array{id: string, name: string}>, return: array<int, array{id: string, name: string}>}
     */
    public function policies(): array
    {
        $marketplace = $this->client->getMarketplaceId();

        return [
            'fulfillment' => $this->listPolicies('fulfillment_policy', 'fulfillmentPolicies', 'fulfillmentPolicyId', $marketplace),
            'payment' => $this->listPolicies('payment_policy', 'paymentPolicies', 'paymentPolicyId', $marketplace),
            'return' => $this->listPolicies('return_policy', 'returnPolicies', 'returnPolicyId', $marketplace),
        ];
    }

    /**
     * Opt the seller in to business policy management when they are not yet in the program.
     * Sellers that never used policies are often not opted in, and the policy calls then fail.
     *
     * @return bool True when an opt-in request was sent.
     */
    public function ensurePolicyProgram(): bool
    {
        $programs = $this->client->get('/sell/account/v1/program/get_opted_in_programs');

        foreach ((array) ($programs['programs'] ?? []) as $program) {
            if (($program['programType'] ?? null) === 'SELLING_POLICY_MANAGEMENT') {
                return false;
            }
        }

        $this->client->post('/sell/account/v1/program/opt_in', ['programType' => 'SELLING_POLICY_MANAGEMENT']);

        Log::info(__METHOD__.' - opted eBay seller in to SELLING_POLICY_MANAGEMENT', ['provider_id' => $this->provider->id]);

        return true;
    }

    /**
     * The seller's inventory locations (ship-from addresses).
     *
     * @return array<int, array{key: string, name: string|null, status: string|null}>
     */
    public function locations(): array
    {
        $response = $this->client->get('/sell/inventory/v1/location', ['limit' => 100]);

        return array_values(array_map(fn (array $location) => [
            'key' => (string) ($location['merchantLocationKey'] ?? ''),
            'name' => $location['name'] ?? null,
            'status' => $location['merchantLocationStatus'] ?? null,
        ], (array) ($response['locations'] ?? [])));
    }

    /**
     * Create a warehouse location and return its key. eBay needs at least country + postal code
     * (or city + state/province) for the address.
     *
     * @param  array<string, string>  $data  key, name, country, postal_code, city, state_or_province, address_line1
     */
    public function createLocation(array $data): string
    {
        $key = (string) $data['key'];

        $address = array_filter([
            'addressLine1' => $data['address_line1'] ?? null,
            'city' => $data['city'] ?? null,
            'stateOrProvince' => $data['state_or_province'] ?? null,
            'postalCode' => $data['postal_code'] ?? null,
            'country' => $data['country'],
        ]);

        $this->client->post('/sell/inventory/v1/location/'.rawurlencode($key), [
            'location' => ['address' => $address],
            'name' => $data['name'] ?? $key,
            'merchantLocationStatus' => 'ENABLED',
            'locationTypes' => ['WAREHOUSE'],
        ]);

        return $key;
    }

    /**
     * The category tree id eBay uses for this marketplace (needed for category and aspect lookups).
     */
    public function categoryTreeId(): string
    {
        // Taxonomy is an application-level API: the keyset's own token is the one it accepts, a seller
        // token (issued for the Sell scopes only) may be refused.
        $response = (new EbayClient($this->client->getMarketplaceId()))->get('/commerce/taxonomy/v1/get_default_category_tree_id', [
            'marketplace_id' => $this->client->getMarketplaceId(),
        ]);

        return (string) ($response['categoryTreeId'] ?? '');
    }

    /**
     * Validate the seller's choices against what eBay actually has for them, then store them in
     * api_config['ebay'] on the provider (through ProvidersService, no direct model write).
     *
     * @param  array<string, mixed>  $input  fulfillment_policy_id, payment_policy_id, return_policy_id,
     *                                       merchant_location_key, default_condition (all optional)
     *
     * @throws NotAllowedException When a chosen policy or location does not exist for this seller.
     */
    public function saveSettings(array $input): Providers
    {
        $policies = $this->policies();
        $settings = [];

        $choices = [
            'fulfillment_policy_id' => 'fulfillment',
            'payment_policy_id' => 'payment',
            'return_policy_id' => 'return',
        ];

        foreach ($choices as $field => $type) {
            if (empty($input[$field])) {
                continue;
            }

            if (! in_array($input[$field], array_column($policies[$type], 'id'), true)) {
                throw new NotAllowedException("Unknown eBay {$type} policy '{$input[$field]}' for this seller.");
            }

            $settings['policies'][$field] = $input[$field];
        }

        if (! empty($input['merchant_location_key'])) {
            if (! in_array($input['merchant_location_key'], array_column($this->locations(), 'key'), true)) {
                throw new NotAllowedException("Unknown eBay merchant location '{$input['merchant_location_key']}'.");
            }

            $settings['merchant_location_key'] = $input['merchant_location_key'];
        }

        if (! empty($input['default_condition'])) {
            $settings['default_condition'] = (string) $input['default_condition'];
        }

        $config = $this->provider->getApiConfigArray();
        $ebay = $config['ebay'] ?? [];

        // Policies merge key by key so choosing one does not wipe the others.
        $ebay['policies'] = ($settings['policies'] ?? []) + ($ebay['policies'] ?? []);
        unset($settings['policies']);

        $ebay = $settings + $ebay;
        $ebay['marketplace_id'] = $ebay['marketplace_id'] ?? $this->client->getMarketplaceId();

        // Category tree is looked up once and kept; it only changes with the marketplace.
        if (empty($ebay['category_tree_id'])) {
            $ebay['category_tree_id'] = $this->categoryTreeId();
        }

        $config['ebay'] = $ebay;

        return ProvidersService::update($this->provider->uuid, ['api_config' => $config]);
    }

    /**
     * Fetch one policy family. A seller not opted in to policy management gets a 400 from eBay;
     * that is reported as an empty list plus a logged hint rather than a crash, and
     * ensurePolicyProgram() fixes it.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function listPolicies(string $path, string $listKey, string $idKey, string $marketplace): array
    {
        try {
            $response = $this->client->get('/sell/account/v1/'.$path, ['marketplace_id' => $marketplace]);
        } catch (EbayUserException $e) {
            Log::warning(__METHOD__.' - eBay policies unavailable', [
                'provider_id' => $this->provider->id,
                'policy' => $path,
                'error_message' => $e->getMessage(),
            ]);

            return [];
        }

        return array_values(array_map(fn (array $policy) => [
            'id' => (string) ($policy[$idKey] ?? ''),
            'name' => (string) ($policy['name'] ?? ''),
        ], (array) ($response[$listKey] ?? [])));
    }
}
