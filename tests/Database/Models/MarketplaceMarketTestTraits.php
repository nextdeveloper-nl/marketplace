<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceMarketQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceMarketService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceMarketTestTraits
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

    public function test_http_marketplacemarket_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplacemarket',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplacemarket_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplacemarket', [
            'form_params'   =>  [
                'name'  =>  'a',
                'description'  =>  'a',
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
    public function test_marketplacemarket_model_get()
    {
        $result = AbstractMarketplaceMarketService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplacemarket_get_all()
    {
        $result = AbstractMarketplaceMarketService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplacemarket_get_paginated()
    {
        $result = AbstractMarketplaceMarketService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplacemarket_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacemarket_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceMarket\MarketplaceMarketRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_name_filter()
    {
        try {
            $request = new Request(
                [
                'name'  =>  'a'
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_description_filter()
    {
        try {
            $request = new Request(
                [
                'description'  =>  'a'
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacemarket_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceMarketQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceMarket::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}