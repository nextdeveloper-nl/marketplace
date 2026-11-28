<?php

namespace NextDeveloper\Marketplace\Tests\Helpers;

use NextDeveloper\Marketplace\Helpers\ProductPublishing;
use NextDeveloper\Marketplace\Services\ProductDocumentsService;
use NextDeveloper\Marketplace\Services\ProductImagesService;
use PHPUnit\Framework\TestCase;

/**
 * leo4#1398 rules that need no database: when the image check applies, and the accepted file types.
 */
class ProductPublishingTest extends TestCase
{
    public function test_a_private_product_turned_public_is_checked(): void
    {
        $this->assertTrue(ProductPublishing::turnsPublic(false, true));
        $this->assertTrue(ProductPublishing::turnsPublic(false, '1'));
        $this->assertTrue(ProductPublishing::turnsPublic(false, 'true'));
        $this->assertTrue(ProductPublishing::turnsPublic(false, 1));
    }

    public function test_a_product_that_is_public_already_is_not_checked(): void
    {
        $this->assertFalse(ProductPublishing::turnsPublic(true, true));
        $this->assertFalse(ProductPublishing::turnsPublic(true, false));
    }

    public function test_staying_private_or_not_sending_the_flag_is_not_checked(): void
    {
        $this->assertFalse(ProductPublishing::turnsPublic(false, false));
        $this->assertFalse(ProductPublishing::turnsPublic(false, '0'));
        $this->assertFalse(ProductPublishing::turnsPublic(false, null));
        $this->assertFalse(ProductPublishing::turnsPublic(false, 'yes please'));
    }

    public function test_documents_are_pdf_catalog_and_user_manual(): void
    {
        $this->assertSame(['catalog', 'user_manual'], ProductDocumentsService::TYPES);
        $this->assertSame('application/pdf', ProductDocumentsService::MIME_TYPE);
    }

    public function test_uploaded_images_exclude_svg(): void
    {
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp', 'image/gif'], ProductImagesService::UPLOAD_MIME_TYPES);
        $this->assertNotContains('image/svg+xml', ProductImagesService::UPLOAD_MIME_TYPES);
    }
}
