<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\Marketplace\Database\Models\OrderItems;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractOrdersService;

/**
 * This class is responsible from managing the data for Orders
 *
 * Class OrdersService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class OrdersService extends AbstractOrdersService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    public static function deleteOrderItemsByOrderId($ordersId): void
    {
        $items = OrderItems::where('marketplace_order_id', $ordersId)
            ->get();

        foreach ($items as $item) {
            $item->delete();
        }
    }

}