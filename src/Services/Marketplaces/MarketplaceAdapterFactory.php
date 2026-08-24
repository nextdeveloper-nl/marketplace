<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use NextDeveloper\Marketplace\Database\Models\Providers;

/**
 * MarketplaceAdapterFactory
 *
 * Resolves the orchestrating service for a marketplace connection from the
 * `adapter` column on its Providers row.
 *
 * This replaces the hardcoded map that used to live inside
 * FetchProviderOrdersCommand, so that adding a marketplace no longer means
 * editing a console command, and so other modules can register their own
 * drivers at boot:
 *
 *   MarketplaceAdapterFactory::register('Shopify', ShopifyService::class);
 *
 * Lookup is case-insensitive because `adapter` is free text typed by whoever
 * created the provider row.
 */
class MarketplaceAdapterFactory
{
    /**
     * Map of adapter keys to their orchestrating service class.
     *
     * @var array<string, class-string>
     */
    private static array $registry = [
        'trendyolgoyemek' => TrendyolGoYemekService::class,
        'shopify' => ShopifyService::class,
    ];

    /**
     * Build the service bound to this provider.
     *
     * @throws \InvalidArgumentException When the adapter key is not registered.
     */
    public static function make(Providers $provider): object
    {
        $class = self::resolve($provider);

        if ($class === null) {
            throw new \InvalidArgumentException(
                sprintf(
                    'No marketplace adapter registered for "%s". Registered adapters: %s',
                    (string) $provider->adapter,
                    implode(', ', self::registeredAdapters())
                )
            );
        }

        return new $class($provider);
    }

    /**
     * Return the service class for a provider, or null when unregistered.
     *
     * Callers that iterate over every provider should prefer this over make(),
     * so one unconfigured row does not abort the whole run.
     *
     * @return class-string|null
     */
    public static function resolve(Providers $provider): ?string
    {
        $key = strtolower(trim((string) $provider->adapter));

        return self::$registry[$key] ?? null;
    }

    /**
     * Register an adapter at runtime. Useful for tests and for modules that
     * ship their own marketplace drivers.
     *
     * @param  class-string  $class
     */
    public static function register(string $adapter, string $class): void
    {
        self::$registry[strtolower(trim($adapter))] = $class;
    }

    /**
     * Return every registered adapter key.
     *
     * @return string[]
     */
    public static function registeredAdapters(): array
    {
        return array_keys(self::$registry);
    }
}
