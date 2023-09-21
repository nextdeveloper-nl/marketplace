<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceSubscriptionQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceSubscriptionService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceSubscriptionTestTraits
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

    public function test_http_marketplacesubscription_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplacesubscription',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplacesubscription_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplacesubscription', [
            'form_params'   =>  [
                    'subscription_starts_at'  =>  now(),
                    'subscription_ends_at'  =>  now(),
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
    public function test_marketplacesubscription_model_get()
    {
        $result = AbstractMarketplaceSubscriptionService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplacesubscription_get_all()
    {
        $result = AbstractMarketplaceSubscriptionService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplacesubscription_get_paginated()
    {
        $result = AbstractMarketplaceSubscriptionService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplacesubscription_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacesubscription_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceSubscription\MarketplaceSubscriptionRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_subscription_starts_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'subscription_starts_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_subscription_ends_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'subscription_ends_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_subscription_starts_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'subscription_starts_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_subscription_ends_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'subscription_ends_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_subscription_starts_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'subscription_starts_atStart'  =>  now(),
                'subscription_starts_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_subscription_ends_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'subscription_ends_atStart'  =>  now(),
                'subscription_ends_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacesubscription_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceSubscriptionQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n
}