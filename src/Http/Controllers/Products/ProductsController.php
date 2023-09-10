<?php

namespace NextDeveloper\Marketplace\Http\Controllers\Products;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Generator\Http\Traits\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\Products\ProductsUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\ProductsQueryFilter;
use NextDeveloper\Marketplace\Services\ProductsService;
use NextDeveloper\Marketplace\Http\Requests\Products\ProductsCreateRequest;

class ProductsController extends AbstractController
{
    /**
    * This method returns the list of products.
    *
    * optional http params:
    * - paginate: If you set paginate parameter, the result will be returned paginated.
    *
    * @param ProductsQueryFilter $filter An object that builds search query
    * @param Request $request Laravel request object, this holds all data about request. Automatically populated.
    * @return \Illuminate\Http\JsonResponse
    */
    public function index(ProductsQueryFilter $filter, Request $request) {
        $data = ProductsService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
    * This method receives ID for the related model and returns the item to the client.
    *
    * @param $productsId
    * @return mixed|null
    * @throws \Laravel\Octane\Exceptions\DdException
    */
    public function show($ref) {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = ProductsService::getByRef($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method created Products object on database.
    *
    * @param ProductsCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function store(ProductsCreateRequest $request) {
        $model = ProductsService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates Products object on database.
    *
    * @param $productsId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function update($productsId, ProductsUpdateRequest $request) {
        $model = ProductsService::update($productsId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
    * This method updates Products object on database.
    *
    * @param $productsId
    * @param CountryCreateRequest $request
    * @return mixed|null
    * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
    */
    public function destroy($productsId) {
        $model = ProductsService::delete($productsId);

        return ResponsableFactory::makeResponse($this, $model);
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}