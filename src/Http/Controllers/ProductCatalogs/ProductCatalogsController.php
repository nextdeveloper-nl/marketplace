<?php

namespace NextDeveloper\Marketplace\Http\Controllers\ProductCatalogs;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\ProductCatalogs\ProductCatalogsUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\ProductCatalogsQueryFilter;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Services\ProductCatalogsService;
use NextDeveloper\Marketplace\Http\Requests\ProductCatalogs\ProductCatalogsCreateRequest;
use NextDeveloper\Commons\Http\Traits\Tags;
class ProductCatalogsController extends AbstractController
{
    private $model = ProductCatalogs::class;

    use Tags;
    /**
     * This method returns the list of productcatalogs.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  ProductCatalogsQueryFilter $filter  An object that builds search query
     * @param  Request                    $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ProductCatalogsQueryFilter $filter, Request $request)
    {
        $data = ProductCatalogsService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $productCatalogsId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = ProductCatalogsService::getByRef($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method returns the list of sub objects the related object. Sub object means an object which is preowned by
     * this object.
     *
     * It can be tags, addresses, states etc.
     *
     * @param  $ref
     * @param  $subObject
     * @return void
     */
    public function relatedObjects($ref, $subObject)
    {
        $objects = ProductCatalogsService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created ProductCatalogs object on database.
     *
     * @param  ProductCatalogsCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(ProductCatalogsCreateRequest $request)
    {
        $model = ProductCatalogsService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates ProductCatalogs object on database.
     *
     * @param  $productCatalogsId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($productCatalogsId, ProductCatalogsUpdateRequest $request)
    {
        $model = ProductCatalogsService::update($productCatalogsId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates ProductCatalogs object on database.
     *
     * @param  $productCatalogsId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($productCatalogsId)
    {
        $model = ProductCatalogsService::delete($productCatalogsId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
