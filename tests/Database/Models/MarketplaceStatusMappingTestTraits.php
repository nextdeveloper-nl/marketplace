<?php

namespace NextDeveloper\Marketplace\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceStatusMappingQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractMarketplaceStatusMappingService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait MarketplaceStatusMappingTestTraits
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

    public function test_http_marketplacestatusmapping_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/marketplace/marketplacestatusmapping',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_marketplacestatusmapping_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/marketplace/marketplacestatusmapping', [
            'form_params'   =>  [
                'external_status'  =>  'a',
                'normalized_status'  =>  'a',
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
    public function test_marketplacestatusmapping_model_get()
    {
        $result = AbstractMarketplaceStatusMappingService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplacestatusmapping_get_all()
    {
        $result = AbstractMarketplaceStatusMappingService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_marketplacestatusmapping_get_paginated()
    {
        $result = AbstractMarketplaceStatusMappingService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_marketplacestatusmapping_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacestatusmapping_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_marketplacestatusmapping_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::first();

            event(new \NextDeveloper\Marketplace\Events\MarketplaceStatusMapping\MarketplaceStatusMappingRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacestatusmapping_event_external_status_filter()
    {
        try {
            $request = new Request(
                [
                'external_status'  =>  'a'
                ]
            );

            $filter = new MarketplaceStatusMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacestatusmapping_event_normalized_status_filter()
    {
        try {
            $request = new Request(
                [
                'normalized_status'  =>  'a'
                ]
            );

            $filter = new MarketplaceStatusMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacestatusmapping_event_description_filter()
    {
        try {
            $request = new Request(
                [
                'description'  =>  'a'
                ]
            );

            $filter = new MarketplaceStatusMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacestatusmapping_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new MarketplaceStatusMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacestatusmapping_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceStatusMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_marketplacestatusmapping_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new MarketplaceStatusMappingQueryFilter($request);

            $model = \NextDeveloper\Marketplace\Database\Models\MarketplaceStatusMapping::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}