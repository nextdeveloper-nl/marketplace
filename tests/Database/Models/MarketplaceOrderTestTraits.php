<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceOrderQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceOrderService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceOrderTestTraits
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

    public function test_http_marketplaceorder_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplaceorder',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplaceorder_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplaceorder', [
            'form_params'   =>  [
                'external_order_id'  =>  'a',
                'external_order_number'  =>  'a',
                'status'  =>  'a',
                'order_type'  =>  'a',
                'delivery_method'  =>  'a',
                'sync_error_message'  =>  'a',
                'customer_note'  =>  'a',
                    'ordered_at'  =>  now(),
                    'accepted_at'  =>  now(),
                    'prepared_at'  =>  now(),
                    'dispatched_at'  =>  now(),
                    'delivered_at'  =>  now(),
                    'cancelled_at'  =>  now(),
                    'estimated_delivery_time'  =>  now(),
                    'last_synced_at'  =>  now(),
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
    public function test_marketplaceorder_model_get()
    {
        $result = AbstractMarketplaceOrderService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceorder_get_all()
    {
        $result = AbstractMarketplaceOrderService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceorder_get_paginated()
    {
        $result = AbstractMarketplaceOrderService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplaceorder_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceorder_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceOrder\MarketplaceOrderRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_external_order_id_filter()
    {
        try {
            $request = new Request(
                [
                'external_order_id'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_external_order_number_filter()
    {
        try {
            $request = new Request(
                [
                'external_order_number'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_status_filter()
    {
        try {
            $request = new Request(
                [
                'status'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_order_type_filter()
    {
        try {
            $request = new Request(
                [
                'order_type'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_delivery_method_filter()
    {
        try {
            $request = new Request(
                [
                'delivery_method'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_sync_error_message_filter()
    {
        try {
            $request = new Request(
                [
                'sync_error_message'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_customer_note_filter()
    {
        try {
            $request = new Request(
                [
                'customer_note'  =>  'a'
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_ordered_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'ordered_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_accepted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'accepted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_prepared_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'prepared_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_dispatched_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'dispatched_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_delivered_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'delivered_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_cancelled_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'cancelled_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_estimated_delivery_time_filter_start()
    {
        try {
            $request = new Request(
                [
                'estimated_delivery_timeStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_last_synced_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'last_synced_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_ordered_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'ordered_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_accepted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'accepted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_prepared_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'prepared_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_dispatched_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'dispatched_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_delivered_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'delivered_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_cancelled_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'cancelled_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_estimated_delivery_time_filter_end()
    {
        try {
            $request = new Request(
                [
                'estimated_delivery_timeEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_last_synced_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'last_synced_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_ordered_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'ordered_atStart'  =>  now(),
                'ordered_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_accepted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'accepted_atStart'  =>  now(),
                'accepted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_prepared_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'prepared_atStart'  =>  now(),
                'prepared_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_dispatched_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'dispatched_atStart'  =>  now(),
                'dispatched_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_delivered_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'delivered_atStart'  =>  now(),
                'delivered_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_cancelled_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'cancelled_atStart'  =>  now(),
                'cancelled_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_estimated_delivery_time_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'estimated_delivery_timeStart'  =>  now(),
                'estimated_delivery_timeEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_last_synced_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'last_synced_atStart'  =>  now(),
                'last_synced_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceorder_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceOrderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceOrder::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}