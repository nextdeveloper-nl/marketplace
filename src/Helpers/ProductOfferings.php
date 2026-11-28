<?php

namespace NextDeveloper\Marketplace\Helpers;

/**
 * Product families and plan offering types (plusclouds/leo4#1397), kept free of the database so they can be tested
 * alone. Every list here matches a check constraint (or enum) in schemas/2026_11_28_product_families_and_offering_types.sql.
 */
class ProductOfferings
{
    /**
     * Values allowed in marketplace_product_catalogs.offering_type (chk_marketplace_product_catalogs_offering_type).
     * long_term_rental is Robot-as-a-Service. NULL means not set.
     */
    public const OFFERING_TYPES = ['sale', 'short_term_rental', 'long_term_rental', 'service'];

    /**
     * Values allowed in marketplace_product_catalogs.billing_period (chk_marketplace_product_catalogs_billing_period).
     * NULL means not set.
     */
    public const BILLING_PERIODS = ['one_time', 'day', 'week', 'month', 'year'];

    /**
     * The period a plan gets when its offering type is set without one.
     *
     * @var array<string, string>
     */
    public const DEFAULT_BILLING_PERIODS = [
        'sale' => 'one_time',
        'short_term_rental' => 'day',
        'long_term_rental' => 'month',
        'service' => 'day',
    ];

    /**
     * Offering types a rental duration belongs to.
     */
    public const RENTAL_OFFERING_TYPES = ['short_term_rental', 'long_term_rental'];

    /**
     * Values allowed in marketplace_product_relations.relation_type (chk_marketplace_product_relations_type).
     */
    public const RELATION_TYPES = ['accessory', 'spare_part', 'bundle'];

    /**
     * Values of the product_type enum that mark a product as a family member. The enum already had 'product'
     * (the main product) and 'service'; these two are added by the DDL.
     */
    public const FAMILY_MEMBER_PRODUCT_TYPES = ['accessory', 'spare_part'];

    /**
     * The billing period to store: the one sent, or the offering type's default when none was sent.
     */
    public static function billingPeriodFor(?string $offeringType, ?string $billingPeriod): ?string
    {
        if ($billingPeriod !== null && $billingPeriod !== '') {
            return $billingPeriod;
        }

        if ($offeringType === null || $offeringType === '') {
            return null;
        }

        return self::DEFAULT_BILLING_PERIODS[$offeringType] ?? null;
    }

    /**
     * Rules the check constraints cannot express, for a plan's final values (after an update is merged in).
     * Returns field => message; empty when the plan is consistent.
     *
     * - a sale is paid once, so it cannot have a recurring period;
     * - a rental is paid per period, so it cannot be one_time;
     * - a rental duration belongs to a rental with a recurring period;
     * - durations are whole periods, at least 1, and the minimum is not above the maximum.
     *
     * @return array<string, string>
     */
    public static function planErrors(?string $offeringType, ?string $billingPeriod, mixed $minDuration, mixed $maxDuration): array
    {
        $errors = [];

        if ($offeringType !== null && ! in_array($offeringType, self::OFFERING_TYPES, true)) {
            $errors['offering_type'] = 'The offering type must be one of: '.implode(', ', self::OFFERING_TYPES).'.';
        }

        if ($billingPeriod !== null && ! in_array($billingPeriod, self::BILLING_PERIODS, true)) {
            $errors['billing_period'] = 'The billing period must be one of: '.implode(', ', self::BILLING_PERIODS).'.';
        }

        if ($offeringType === 'sale' && $billingPeriod !== null && $billingPeriod !== 'one_time') {
            $errors['billing_period'] = 'A sale is paid once: its billing period must be one_time.';
        }

        if (in_array($offeringType, self::RENTAL_OFFERING_TYPES, true) && $billingPeriod === 'one_time') {
            $errors['billing_period'] = 'A rental is charged per day, week, month or year, not one_time.';
        }

        $hasDuration = $minDuration !== null || $maxDuration !== null;

        if ($hasDuration && ! in_array($offeringType, self::RENTAL_OFFERING_TYPES, true)) {
            $errors['min_rental_duration'] = 'A rental duration can only be set on a short or long term rental plan.';
        } elseif ($hasDuration && ($billingPeriod === null || $billingPeriod === 'one_time')) {
            $errors['min_rental_duration'] = 'A rental duration is counted in billing periods: set a day, week, month or year period.';
        }

        foreach (['min_rental_duration' => $minDuration, 'max_rental_duration' => $maxDuration] as $field => $value) {
            if ($value !== null && (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1)) {
                $errors[$field] = 'The rental duration must be a whole number of at least 1.';
            }
        }

        if (! isset($errors['min_rental_duration']) && ! isset($errors['max_rental_duration'])
            && $minDuration !== null && $maxDuration !== null
            && (int) $minDuration > (int) $maxDuration) {
            $errors['max_rental_duration'] = 'The longest rental cannot be shorter than the shortest rental.';
        }

        return $errors;
    }
}
