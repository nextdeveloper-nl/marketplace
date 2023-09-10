<?php

namespace NextDeveloper\Marketplace\Http\Controllers\MarketplaceSubscription;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Generator\Http\Traits\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\MarketplaceSubscription\MarketplaceSubscriptionUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceSubscriptionQueryFilter;
use NextDeveloper\Marketplace\Services\MarketplaceSubscriptionService;
use NextDeveloper\Marketplace\Http\Requests\MarketplaceSubscription\MarketplaceSubscriptionCreateRequest;

class MarketplaceSubscriptionController extends AbstractController
{
    /**
    * This method returns the list of marketplacesubscriptions.
    *
    * optional http params:
    * - paginate: If you set paginate parameter, the result will be returned paginated.
    *
    * @param MarketplaceSubscriptionQueryFilter $filter An object that builds search query
    * @param Request $request Laravel request object, this holds all data about request. Automatically populated.
    * @return \Illuminate\Http\JsonResponse
    */
    public function index(MarketplaceSubscriptionQueryFilter $filter, Request $request) {
        $data = MarketplaceSubscriptionService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
    * This method receives ID for the related model and returns the item to the client.
    *
    * @param $marketplaceSubscriptionId
    * @return mixed|null
    * @throws \Laravel\Octane\Exceptions\DdException
    */
    public function show($ref) {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = MarketplaceSubscriptionService::getByRef($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method created MarketplaceSubscription object on database.
    *
    * @param MarketplaceSubscriptionCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function store(MarketplaceSubscriptionCreateRequest $request) {
        $model = MarketplaceSubscriptionService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates MarketplaceSubscription object on database.
    *
    * @param $marketplaceSubscriptionId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function update($marketplaceSubscriptionId, MarketplaceSubscriptionUpdateRequest $request) {
        $model = MarketplaceSubscriptionService::update($marketplaceSubscriptionId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates MarketplaceSubscription object on database.
    *
    * @param $marketplaceSubscriptionId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function destroy($marketplaceSubscriptionId) {
        $model = MarketplaceSubscriptionService::delete($marketplaceSubscriptionId);

        return ResponsableFactory::makeResponse($this, $model);
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}