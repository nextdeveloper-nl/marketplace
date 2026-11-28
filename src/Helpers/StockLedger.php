<?php

namespace NextDeveloper\Marketplace\Helpers;

/**
 * The stock rules of a plan (marketplace_product_catalogs), kept free of the database so they can be tested alone.
 *
 * quantity_in_inventory is the running total. -1 means unlimited (the column default): the plan is not counted, so
 * the first movement starts counting from 0. A movement may never take the stock below 0.
 */
class StockLedger
{
    public const UNLIMITED = -1;

    /**
     * Values allowed in marketplace_product_catalogs.stock_status (check constraint
     * chk_marketplace_product_catalogs_stock_status). NULL means the seller does not track a status.
     */
    public const STATUSES = ['in_stock', 'out_of_stock', 'in_transit', 'at_customs', 'on_order'];

    /**
     * Statuses of stock that is not on the shelf yet. These are the ones an expected arrival date belongs to.
     */
    public const ARRIVING_STATUSES = ['in_transit', 'at_customs', 'on_order'];

    /**
     * Values allowed in marketplace_stock_movements.movement_type, with the direction each one moves the stock in.
     * 0 = the sign comes from the quantity itself (adjustment).
     *
     * @var array<string, int>
     */
    public const MOVEMENT_DIRECTIONS = [
        'incoming' => 1,
        'sale' => -1,
        'rental_out' => -1,
        'rental_return' => 1,
        'adjustment' => 0,
    ];

    /**
     * @return list<string>
     */
    public static function movementTypes(): array
    {
        return array_keys(self::MOVEMENT_DIRECTIONS);
    }

    /**
     * The signed change a movement makes. Directional types ignore the sign the caller sent (a sale of 3 and a sale
     * of -3 both remove 3), an adjustment keeps it.
     */
    public static function signedQuantity(string $movementType, int $quantity): int
    {
        if (! array_key_exists($movementType, self::MOVEMENT_DIRECTIONS)) {
            throw new \InvalidArgumentException('Unknown stock movement type: '.$movementType);
        }

        $direction = self::MOVEMENT_DIRECTIONS[$movementType];

        return $direction === 0 ? $quantity : $direction * abs($quantity);
    }

    /**
     * The stock after applying a signed change. Null when the result would be below zero.
     */
    public static function quantityAfter(int $currentQuantity, int $signedQuantity): ?int
    {
        $base = $currentQuantity < 0 ? 0 : $currentQuantity;

        $after = $base + $signedQuantity;

        return $after < 0 ? null : $after;
    }

    /**
     * The status the plan should have after a movement. Only an explicit status is moved, a plan without one keeps
     * none:
     *  - stock arriving (positive change) on a plan that was out of stock or still on its way puts it in stock and
     *    clears the expected arrival date;
     *  - stock reaching 0 on a plan that was in stock puts it out of stock.
     *
     * @return array{stock_status: ?string, clear_expected_arrival: bool}
     */
    public static function statusAfter(?string $currentStatus, int $signedQuantity, int $quantityAfter): array
    {
        if ($currentStatus === null) {
            return ['stock_status' => null, 'clear_expected_arrival' => false];
        }

        if ($signedQuantity > 0 && $quantityAfter > 0
            && in_array($currentStatus, array_merge(['out_of_stock'], self::ARRIVING_STATUSES), true)) {
            return ['stock_status' => 'in_stock', 'clear_expected_arrival' => true];
        }

        if ($quantityAfter === 0 && $currentStatus === 'in_stock') {
            return ['stock_status' => 'out_of_stock', 'clear_expected_arrival' => false];
        }

        return ['stock_status' => $currentStatus, 'clear_expected_arrival' => false];
    }
}
