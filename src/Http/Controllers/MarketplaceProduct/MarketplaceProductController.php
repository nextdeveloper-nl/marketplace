<?php

namespace NextDeveloper\Marketplace\Http\Controllers\MarketplaceProduct;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Generator\Http\Traits\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\MarketplaceProduct\MarketplaceProductUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProductQueryFilter;
use NextDeveloper\Marketplace\Services\MarketplaceProductService;
use NextDeveloper\Marketplace\Http\Requests\MarketplaceProduct\MarketplaceProductCreateRequest;

class MarketplaceProductController extends AbstractController
{
    /**
    * This method returns the list of marketplaceproducts.
    *
    * optional http params:
    * - paginate: If you set paginate parameter, the result will be returned paginated.
    *
    * @param MarketplaceProductQueryFilter $filter An object that builds search query
    * @param Request $request Laravel request object, this holds all data about request. Automatically populated.
    * @return \Illuminate\Http\JsonResponse
    */
    public function index(MarketplaceProductQueryFilter $filter, Request $request) {
        $data = MarketplaceProductService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
    * This method receives ID for the related model and returns the item to the client.
    *
    * @param $marketplaceProductId
    * @return mixed|null
    * @throws \Laravel\Octane\Exceptions\DdException
    */
    public function show($ref) {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = MarketplaceProductService::getByRef($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method created MarketplaceProduct object on database.
    *
    * @param MarketplaceProductCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function store(MarketplaceProductCreateRequest $request) {
        $model = MarketplaceProductService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates MarketplaceProduct object on database.
    *
    * @param $marketplaceProductId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function update($marketplaceProductId, MarketplaceProductUpdateRequest $request) {
        $model = MarketplaceProductService::update($marketplaceProductId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates MarketplaceProduct object on database.
    *
    * @param $marketplaceProductId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function destroy($marketplaceProductId) {
        $model = MarketplaceProductService::delete($marketplaceProductId);

        return ResponsableFactory::makeResponse($this, $model);
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}