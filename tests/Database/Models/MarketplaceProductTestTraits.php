<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProductQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceProductService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceProductTestTraits
{
    public $http;

    /**
    *   Creating the Guzzle object
    */
    public function setupGuzzle()
    {
        $this->http = new Client([
            'base_uri'  =>  '127.0.0.1:8000'
        ]);
    }

    /**
    *   Destroying the Guzzle object
    */
    public function destroyGuzzle()
    {
        $this->http = null;
    }

    public function test_http_marketplaceproduct_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplaceproduct',
            ['http_errors' => false]
        );

        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
        ]);
    }

    public function test_http_marketplaceproduct_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request('POST', '/marketplace/marketplaceproduct', [
            'form_params'   =>  [
                'name'  =>  'a',
                'description'  =>  'a',
                'content'  =>  'a',
                'highlights'  =>  'a',
                'after_sales_introduction'  =>  'a',
                'support_content'  =>  'a',
                'refund_policy'  =>  'a',
                'eula'  =>  'a',
                'slug'  =>  'a',
                'version'  =>  'a',
                'management_class'  =>  'a',
                'discount_rate'  =>  '1',
                            ],
                ['http_errors' => false]
            ]
        );

        $this->assertEquals($response->getStatusCode(), Response::HTTP_OK);
    }

    /**
    * Get test
    *
    * @return bool
    */
    public function test_marketplaceproduct_model_get()
    {
        $result = AbstractMarketplaceProductService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceproduct_get_all()
    {
        $result = AbstractMarketplaceProductService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceproduct_get_paginated()
    {
        $result = AbstractMarketplaceProductService::get(null, [
            'paginated' =>  'true'
        ]);

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplaceproduct_event_retrieved_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductRetrievedEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_created_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductCreatedEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_creating_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductCreatingEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_saving_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductSavingEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_saved_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductSavedEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_updating_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductUpdatingEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_updated_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductUpdatedEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_deleting_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductDeletingEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_deleted_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductDeletedEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_restoring_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductRestoringEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_restored_without_object()
    {
        try {
            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductRestoredEvent() );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductRetrievedEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductCreatedEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductCreatingEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductSavingEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductSavedEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductUpdatingEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductUpdatedEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductDeletingEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductDeletedEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductRestoringEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproduct_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::first();

            event( new \NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductRestoredEvent($model) );
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_name_filter()
    {
        try {
            $request = new Request([
                'name'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_description_filter()
    {
        try {
            $request = new Request([
                'description'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_content_filter()
    {
        try {
            $request = new Request([
                'content'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_highlights_filter()
    {
        try {
            $request = new Request([
                'highlights'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_after_sales_introduction_filter()
    {
        try {
            $request = new Request([
                'after_sales_introduction'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_support_content_filter()
    {
        try {
            $request = new Request([
                'support_content'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_refund_policy_filter()
    {
        try {
            $request = new Request([
                'refund_policy'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_eula_filter()
    {
        try {
            $request = new Request([
                'eula'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_slug_filter()
    {
        try {
            $request = new Request([
                'slug'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_version_filter()
    {
        try {
            $request = new Request([
                'version'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_management_class_filter()
    {
        try {
            $request = new Request([
                'management_class'  =>  'a'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_discount_rate_filter()
    {
        try {
            $request = new Request([
                'discount_rate'  =>  '1'
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_created_at_filter_start()
    {
        try {
            $request = new Request([
                'created_atStart'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_updated_at_filter_start()
    {
        try {
            $request = new Request([
                'updated_atStart'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_deleted_at_filter_start()
    {
        try {
            $request = new Request([
                'deleted_atStart'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_created_at_filter_end()
    {
        try {
            $request = new Request([
                'created_atEnd'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_updated_at_filter_end()
    {
        try {
            $request = new Request([
                'updated_atEnd'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_deleted_at_filter_end()
    {
        try {
            $request = new Request([
                'deleted_atEnd'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request([
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request([
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproduct_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request([
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
            ]);

            $filter = new MarketplaceProductQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n
}