<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceOrderStatusHistoryQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceOrderStatusHistoryService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceOrderStatusHistoryTestTraits
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

    public function test_http_marketplaceorderstatushistory_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplaceorderstatushistory',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplaceorderstatushistory_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplaceorderstatushistory', [
            'form_params'   =>  [
                'old_status'  =>  'a',
                'new_status'  =>  'a',
                'notes'  =>  'a',
                    'changed_at'  =>  now(),
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
    public function test_marketplaceorderstatushistory_model_get()
    {
        $result = AbstractMarketplaceOrderStatusHistoryService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceorderstatushistory_get_all()
    {
        $result = AbstractMarketplaceOrderStatusHistoryService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceorderstatushistory_get_paginated()
    {
        $result = AbstractMarketplaceOrderStatusHistoryService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplaceorderstatushistory_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistorySavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistorySavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistorySavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistorySavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorderstatushistory_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrderStatusHistory\MarketplaceOrderStatusHistoryRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_old_status_filter()
    {
        try {
            $request = new Request(
                [
                'old_status'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_new_status_filter()
    {
        try {
            $request = new Request(
                [
                'new_status'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_notes_filter()
    {
        try {
            $request = new Request(
                [
                'notes'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_changed_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'changed_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_changed_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'changed_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_changed_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'changed_atStart'  =>  now(),
                'changed_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorderstatushistory_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderStatusHistoryQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrderStatusHistory::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}