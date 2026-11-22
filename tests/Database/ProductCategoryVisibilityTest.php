<?php

namespace NextDeveloper\Marketplace\Tests\Database;

use NextDeveloper\Marketplace\Services\ProductsService;
use PHPUnit\Framework\TestCase;

/**
 * Product category visibility guard. No database needed: the null case returns before any query.
 */
class ProductCategoryVisibilityTest extends TestCase
{
    public function test_product_without_category_needs_no_check(): void
    {
        $method = new \ReflectionMethod(ProductsService::class, 'assertCategoryUsable');
        $method->setAccessible(true);

        $this->assertNull($method->invoke(null, null));
    }

    public function test_service_overrides_create_and_update_to_guard_category(): void
    {
        foreach (['create', 'update'] as $name) {
            $this->assertSame(
                ProductsService::class,
                (new \ReflectionMethod(ProductsService::class, $name))->getDeclaringClass()->getName(),
                "ProductsService::$name must apply the category visibility guard"
            );
        }
    }
}
