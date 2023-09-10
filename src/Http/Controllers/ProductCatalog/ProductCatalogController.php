<?php

namespace NextDeveloper\Marketplace\Http\Controllers\MarketplaceProductCatalog;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Generator\Http\Traits\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\MarketplaceProductCatalog\MarketplaceProductCatalogUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProductCatalogQueryFilter;
use NextDeveloper\Marketplace\Services\MarketplaceProductCatalogService;
use NextDeveloper\Marketplace\Http\Requests\MarketplaceProductCatalog\MarketplaceProductCatalogCreateRequest;

class MarketplaceProductCatalogController extends AbstractController
{
    /**
    * This method returns the list of marketplaceproductcatalogs.
    *
    * optional http params:
    * - paginate: If you set paginate parameter, the result will be returned paginated.
    *
    * @param MarketplaceProductCatalogQueryFilter $filter An object that builds search query
    * @param Request $request Laravel request object, this holds all data about request. Automatically populated.
    * @return \Illuminate\Http\JsonResponse
    */
    public function index(MarketplaceProductCatalogQueryFilter $filter, Request $request) {
        $data = MarketplaceProductCatalogService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
    * This method receives ID for the related model and returns the item to the client.
    *
    * @param $marketplaceProductCatalogId
    * @return mixed|null
    * @throws \Laravel\Octane\Exceptions\DdException
    */
    public function show($ref) {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = MarketplaceProductCatalogService::getByRef($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method created MarketplaceProductCatalog object on database.
    *
    * @param MarketplaceProductCatalogCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function store(MarketplaceProductCatalogCreateRequest $request) {
        $model = MarketplaceProductCatalogService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates MarketplaceProductCatalog object on database.
    *
    * @param $marketplaceProductCatalogId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function update($marketplaceProductCatalogId, MarketplaceProductCatalogUpdateRequest $request) {
        $model = MarketplaceProductCatalogService::update($marketplaceProductCatalogId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates MarketplaceProductCatalog object on database.
    *
    * @param $marketplaceProductCatalogId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function destroy($marketplaceProductCatalogId) {
        $model = MarketplaceProductCatalogService::delete($marketplaceProductCatalogId);

        return ResponsableFactory::makeResponse($this, $model);
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}