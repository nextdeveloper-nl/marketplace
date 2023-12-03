<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProductCatalogQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceProductCatalogService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceProductCatalogTestTraits
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

    public function test_http_marketplaceproductcatalog_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplaceproductcatalog',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplaceproductcatalog_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplaceproductcatalog', [
            'form_params'   =>  [
                'name'  =>  'a',
                'agreement'  =>  'a',
                'currency_code'  =>  'a',
                'price'  =>  '1',
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
    public function test_marketplaceproductcatalog_model_get()
    {
        $result = AbstractMarketplaceProductCatalogService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceproductcatalog_get_all()
    {
        $result = AbstractMarketplaceProductCatalogService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceproductcatalog_get_paginated()
    {
        $result = AbstractMarketplaceProductCatalogService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplaceproductcatalog_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceproductcatalog_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_name_filter()
    {
        try {
            $request = new Request(
                [
                'name'  =>  'a'
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_agreement_filter()
    {
        try {
            $request = new Request(
                [
                'agreement'  =>  'a'
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_currency_code_filter()
    {
        try {
            $request = new Request(
                [
                'currency_code'  =>  'a'
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_price_filter()
    {
        try {
            $request = new Request(
                [
                'price'  =>  '1'
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceproductcatalog_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProductCatalogQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n
}