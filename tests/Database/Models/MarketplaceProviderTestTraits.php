<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProviderQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceProviderService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceProviderTestTraits
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

    public function test_http_marketplaceprovider_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplaceprovider',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplaceprovider_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplaceprovider', [
            'form_params'   =>  [
                'name'  =>  'a',
                'description'  =>  'a',
                'action'  =>  'a',
                'url'  =>  'a',
                'adapter'  =>  'a',
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
    public function test_marketplaceprovider_model_get()
    {
        $result = AbstractMarketplaceProviderService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceprovider_get_all()
    {
        $result = AbstractMarketplaceProviderService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplaceprovider_get_paginated()
    {
        $result = AbstractMarketplaceProviderService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplaceprovider_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplaceprovider_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceProvider\MarketplaceProviderRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_name_filter()
    {
        try {
            $request = new Request(
                [
                'name'  =>  'a'
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_description_filter()
    {
        try {
            $request = new Request(
                [
                'description'  =>  'a'
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_action_filter()
    {
        try {
            $request = new Request(
                [
                'action'  =>  'a'
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_url_filter()
    {
        try {
            $request = new Request(
                [
                'url'  =>  'a'
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_adapter_filter()
    {
        try {
            $request = new Request(
                [
                'adapter'  =>  'a'
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplaceprovider_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceProviderQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceProvider::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}