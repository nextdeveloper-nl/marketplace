<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Marketplace\Actions\OrderItems\CalculatingOrderTotalAmount;
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

    /**
     * @throws NotAllowedException
     */
    public static function create($data)
    {
        $orderItem = parent::create($data);

        $action = new CalculatingOrderTotalAmount($orderItem);
        $action->handle();
        return $orderItem;
    }

}