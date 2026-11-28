<?php

namespace NextDeveloper\Marketplace\Tests\Helpers;

use NextDeveloper\Marketplace\Helpers\ProductOfferings;
use PHPUnit\Framework\TestCase;

/**
 * Plan offering types and product families (leo4#1397). No database needed.
 */
class ProductOfferingsTest extends TestCase
{
    public function test_a_plan_with_nothing_set_passes(): void
    {
        $this->assertSame([], ProductOfferings::planErrors(null, null, null, null));
    }

    public function test_each_offering_type_gets_a_default_period(): void
    {
        $this->assertSame('one_time', ProductOfferings::billingPeriodFor('sale', null));
        $this->assertSame('day', ProductOfferings::billingPeriodFor('short_term_rental', ''));
        $this->assertSame('month', ProductOfferings::billingPeriodFor('long_term_rental', null));
        $this->assertSame('day', ProductOfferings::billingPeriodFor('service', null));
        $this->assertSame('week', ProductOfferings::billingPeriodFor('short_term_rental', 'week'));
        $this->assertNull(ProductOfferings::billingPeriodFor(null, null));

        foreach (ProductOfferings::OFFERING_TYPES as $type) {
            $this->assertContains(ProductOfferings::billingPeriodFor($type, null), ProductOfferings::BILLING_PERIODS);
        }
    }

    public function test_robotena_offerings_are_valid(): void
    {
        $this->assertSame([], ProductOfferings::planErrors('sale', 'one_time', null, null));
        $this->assertSame([], ProductOfferings::planErrors('short_term_rental', 'day', 1, 30));
        $this->assertSame([], ProductOfferings::planErrors('long_term_rental', 'month', 12, 36));
        $this->assertSame([], ProductOfferings::planErrors('service', 'day', null, null));
        $this->assertSame([], ProductOfferings::planErrors('service', 'one_time', null, null));
        $this->assertSame([], ProductOfferings::planErrors(null, 'month', null, null));
    }

    public function test_unknown_values_are_rejected(): void
    {
        $this->assertArrayHasKey('offering_type', ProductOfferings::planErrors('lease', null, null, null));
        $this->assertArrayHasKey('billing_period', ProductOfferings::planErrors(null, 'hourly', null, null));
    }

    public function test_a_sale_is_paid_once(): void
    {
        $this->assertArrayHasKey('billing_period', ProductOfferings::planErrors('sale', 'month', null, null));
    }

    public function test_a_rental_is_not_one_time(): void
    {
        $this->assertArrayHasKey('billing_period', ProductOfferings::planErrors('long_term_rental', 'one_time', null, null));
        $this->assertArrayHasKey('billing_period', ProductOfferings::planErrors('short_term_rental', 'one_time', null, null));
    }

    public function test_a_duration_belongs_to_a_rental_with_a_period(): void
    {
        $this->assertArrayHasKey('min_rental_duration', ProductOfferings::planErrors('sale', 'one_time', 1, null));
        $this->assertArrayHasKey('min_rental_duration', ProductOfferings::planErrors(null, null, null, 5));
        $this->assertArrayHasKey('min_rental_duration', ProductOfferings::planErrors('long_term_rental', null, 3, null));
    }

    public function test_durations_are_whole_positive_and_ordered(): void
    {
        $this->assertArrayHasKey('min_rental_duration', ProductOfferings::planErrors('long_term_rental', 'month', 0, null));
        $this->assertArrayHasKey('max_rental_duration', ProductOfferings::planErrors('long_term_rental', 'month', null, -2));
        $this->assertArrayHasKey('min_rental_duration', ProductOfferings::planErrors('long_term_rental', 'month', '1.5', null));
        $this->assertArrayHasKey('max_rental_duration', ProductOfferings::planErrors('long_term_rental', 'month', 12, 6));
        $this->assertSame([], ProductOfferings::planErrors('long_term_rental', 'month', '6', '6'));
    }

    /**
     * The PHP lists must match the check constraints and the enum values the DDL adds.
     */
    public function test_lists_match_the_ddl(): void
    {
        $sql = (string) file_get_contents(__DIR__.'/../../schemas/2026_11_28_product_families_and_offering_types.sql');

        $this->assertStringContainsString(
            "offering_type IN ('".implode("', '", ProductOfferings::OFFERING_TYPES)."')",
            $sql
        );
        $this->assertStringContainsString(
            "billing_period IN ('".implode("', '", ProductOfferings::BILLING_PERIODS)."')",
            $sql
        );
        $this->assertStringContainsString(
            "relation_type IN ('".implode("', '", ProductOfferings::RELATION_TYPES)."')",
            $sql
        );

        foreach (ProductOfferings::FAMILY_MEMBER_PRODUCT_TYPES as $productType) {
            $this->assertStringContainsString("ALTER TYPE product_type ADD VALUE IF NOT EXISTS '".$productType."'", $sql);
        }
    }
}
