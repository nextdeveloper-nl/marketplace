<?php

namespace NextDeveloper\Marketplace\Actions\OrderItems;

use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\OrderItems;
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;

class CalculateOrderTotal extends \NextDeveloper\Commons\Actions\AbstractAction
{
    private const TAX_RATE = 0.2;

    /**
     * Events associated with calculating the order total.
     */
    public const EVENTS = [
        'created:NextDeveloper\Marketplace\OrderItems',
    ];

    /**
     * CalculateOrderTotal constructor.
     *
     * @param OrderItems $orderItems
     * @throws NotAllowedException
     */
    public function __construct(public OrderItems $orderItems)
    {
        $this->model = $orderItems;
        parent::__construct();
    }

    /**
     * Handles the calculation of the order total.
     */
    public function handle(): void
    {
        $this->setProgress(0, __METHOD__ . ' Starting to calculate order total');

        $order = $this->getOrder();
        if (!$order) {
            $this->setProgress(100, __METHOD__ . ' Order not found');
            return;
        }

        if ($this->isOrderAlreadyCalculated($order)) {
            $this->setProgress(100, __METHOD__ . ' Order total already calculated');
            return;
        }

        $subtotal = $this->calculateSubtotal($order->id);
        $this->updateOrderTotals($order, $subtotal);

        $this->setProgress(100, __METHOD__ . ' Order total calculated for order ID: ' . $order->id);
    }

    /**
     * Get the order associated with the order item.
     */
    private function getOrder(): ?Orders
    {
        return Orders::withoutGlobalScope(AuthorizationScope::class)
            ->find($this->orderItems->marketplace_order_id);
    }

    /**
     * Check if order totals have already been calculated.
     */
    private function isOrderAlreadyCalculated(Orders $order): bool
    {
        return !is_null($order->subtotal_amount) && !is_null($order->total_amount);
    }

    /**
     * Calculate the subtotal for all order items.
     */
    private function calculateSubtotal(int $orderId): float
    {
        return OrderItems::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_order_id', $orderId)
            ->join('product_catalogs', 'order_items.product_catalog_id', '=', 'product_catalogs.id')
            ->sum('product_catalogs.price');
    }

    /**
     * Update order with calculated totals.
     */
    private function updateOrderTotals(Orders $order, float $subtotal): void
    {
        $taxAmount = $subtotal * self::TAX_RATE;
        $totalAmount = $subtotal + $taxAmount;

        $order->updateQuietly([
            'subtotal_amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }
}
