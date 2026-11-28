<?php

namespace NextDeveloper\Marketplace\Tests\Helpers;

use NextDeveloper\Marketplace\Helpers\StockLedger;
use PHPUnit\Framework\TestCase;

/**
 * Stock rules of a plan. No database needed.
 */
class StockLedgerTest extends TestCase
{
    public function test_directional_types_ignore_the_sign_sent(): void
    {
        $this->assertSame(5, StockLedger::signedQuantity('incoming', 5));
        $this->assertSame(5, StockLedger::signedQuantity('incoming', -5));
        $this->assertSame(2, StockLedger::signedQuantity('rental_return', -2));
        $this->assertSame(-3, StockLedger::signedQuantity('sale', 3));
        $this->assertSame(-3, StockLedger::signedQuantity('sale', -3));
        $this->assertSame(-1, StockLedger::signedQuantity('rental_out', 1));
    }

    public function test_adjustment_keeps_its_sign(): void
    {
        $this->assertSame(-4, StockLedger::signedQuantity('adjustment', -4));
        $this->assertSame(4, StockLedger::signedQuantity('adjustment', 4));
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StockLedger::signedQuantity('stolen', 1);
    }

    public function test_quantity_after_is_the_running_total(): void
    {
        $this->assertSame(7, StockLedger::quantityAfter(4, 3));
        $this->assertSame(1, StockLedger::quantityAfter(4, -3));
        $this->assertSame(0, StockLedger::quantityAfter(4, -4));
    }

    public function test_stock_never_goes_below_zero(): void
    {
        $this->assertNull(StockLedger::quantityAfter(4, -5));
        $this->assertNull(StockLedger::quantityAfter(0, -1));
    }

    public function test_unlimited_plan_starts_counting_from_zero(): void
    {
        $this->assertSame(10, StockLedger::quantityAfter(StockLedger::UNLIMITED, 10));
        $this->assertNull(StockLedger::quantityAfter(StockLedger::UNLIMITED, -1));
    }

    public function test_arriving_stock_puts_a_waiting_plan_in_stock(): void
    {
        foreach (['out_of_stock', 'in_transit', 'at_customs', 'on_order'] as $status) {
            $this->assertSame(
                ['stock_status' => 'in_stock', 'clear_expected_arrival' => true],
                StockLedger::statusAfter($status, 5, 5),
                $status
            );
        }
    }

    public function test_selling_the_last_unit_puts_the_plan_out_of_stock(): void
    {
        $this->assertSame(
            ['stock_status' => 'out_of_stock', 'clear_expected_arrival' => false],
            StockLedger::statusAfter('in_stock', -2, 0)
        );
    }

    public function test_other_moves_keep_the_status(): void
    {
        $this->assertSame('in_stock', StockLedger::statusAfter('in_stock', -1, 3)['stock_status']);
        //  A sale out of a plan that is also waiting for a shipment does not hide the shipment.
        $this->assertSame('in_transit', StockLedger::statusAfter('in_transit', -1, 0)['stock_status']);
        //  A downward adjustment is not an arrival.
        $this->assertSame('out_of_stock', StockLedger::statusAfter('out_of_stock', -1, 0)['stock_status']);
    }

    public function test_plan_without_a_status_keeps_none(): void
    {
        $this->assertSame(
            ['stock_status' => null, 'clear_expected_arrival' => false],
            StockLedger::statusAfter(null, -4, 0)
        );
    }

    public function test_lists_match_the_check_constraints(): void
    {
        $schema = file_get_contents(__DIR__.'/../../schemas/2026_11_28_stock_status_and_movements.sql');

        $this->assertStringContainsString(
            "stock_status IN ('".implode("', '", StockLedger::STATUSES)."')",
            $schema
        );
        $this->assertStringContainsString(
            "movement_type IN ('".implode("', '", StockLedger::movementTypes())."')",
            $schema
        );
    }
}
