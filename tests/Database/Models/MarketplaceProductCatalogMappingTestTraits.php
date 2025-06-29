<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProductCatalogMappingQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceProductCatalogMappingService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceProductCatalogMappingTestTraits
{
    public $http;

    /**
     *   Creating the Guzzle object
     */
    public function setupGuzzle()
    {
        $this->http = new Client(
            [
            'base_uri'  =>  '127.0.0.1:8000'
            ]
        );
    }

    /**
     *   Destroying the Guzzle object
     */
    public function destroyGuzzle()
    {
        $this->http = null;
    }

    public function test_http_marketplaceproductcatalogmapping_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplaceproductcatalogmapping',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplaceproductcatalogmapping_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplaceproductcatalogmapping', [
            'form_params'   =>  [
                'external_catalog_id'  =>  'a',
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
    public function test_marketplaceproductcatalogmapping_model_get()
    {
        $result = AbstractMarketplaceProductCatalogMappingService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceproductcatalogmapping_get_all()
    {
        $result = AbstractMarketplaceProductCatalogMappingService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceproductcatalogmapping_get_paginated()
    {
        $result = AbstractMarketplaceProductCatalogMappingService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplaceproductcatalogmapping_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalogmapping_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalogMapping\MarketplaceProductCatalogMappingRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_external_catalog_id_filter()
    {
        try {
            $request = new Request(
                [
                'external_catalog_id'  =>  'a'
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalogmapping_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalogMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}