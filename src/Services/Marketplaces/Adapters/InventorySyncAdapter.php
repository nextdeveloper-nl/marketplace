<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Exceptions\StaleInventoryException;

/**
 * Capability interface for marketplaces that expose inventory levels.
 */
interface InventorySyncAdapter
{
    /**
     * Fetch inventory levels changed at or after the given moment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchInventoryLevels(Carbon $since): array;

    /**
     * Set the remote stock level for a catalog entry using compare-and-swap.
     *
     * `$changeFrom` is the quantity we last observed remotely. Implementations
     * must send it to the marketplace so the write is rejected when the remote
     * value has moved underneath us, and must surface that rejection rather
     * than retrying blindly — a blind retry is how oversell happens.
     *
     * @param  int  $quantity  Desired absolute quantity.
     * @param  int|null  $changeFrom  Last observed remote quantity, null to force.
     * @return bool True when the remote level was updated.
     *
     * @throws StaleInventoryException
     *                                 When the remote quantity no longer matches $changeFrom.
     */
    /**
     * @param  string|null  $operationRef  Identifies this specific push, so that a
     *                                     retry is idempotent while a later push
     *                                     moving the same numbers is not folded
     *                                     into it.
     */
    public function pushInventoryLevel(ProductCatalogs $catalog, int $quantity, ?int $changeFrom = null, ?string $operationRef = null): bool;
}
