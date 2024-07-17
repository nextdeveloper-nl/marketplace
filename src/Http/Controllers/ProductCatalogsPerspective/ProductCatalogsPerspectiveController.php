<?php

namespace NextDeveloper\Marketplace\Http\Controllers\ProductCatalogsPerspective;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\ProductCatalogsPerspective\ProductCatalogsPerspectiveUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\ProductCatalogsPerspectiveQueryFilter;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogsPerspective;
use NextDeveloper\Marketplace\Services\ProductCatalogsPerspectiveService;
use NextDeveloper\Marketplace\Http\Requests\ProductCatalogsPerspective\ProductCatalogsPerspectiveCreateRequest;
use NextDeveloper\Commons\Http\Traits\Tags as TagsTrait;use NextDeveloper\Commons\Http\Traits\Addresses as AddressesTrait;
class ProductCatalogsPerspectiveController extends AbstractController
{
    private $model = ProductCatalogsPerspective::class;

    use TagsTrait;
    use AddressesTrait;
    /**
     * This method returns the list of productcatalogsperspectives.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  ProductCatalogsPerspectiveQueryFilter $filter  An object that builds search query
     * @param  Request                               $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ProductCatalogsPerspectiveQueryFilter $filter, Request $request)
    {
        $data = ProductCatalogsPerspectiveService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This function returns the list of actions that can be performed on this object.
     *
     * @return void
     */
    public function getActions()
    {
        $data = ProductCatalogsPerspectiveService::getActions();

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * Makes the related action to the object
     *
     * @param  $objectId
     * @param  $action
     * @return array
     */
    public function doAction($objectId, $action)
    {
        $actionId = ProductCatalogsPerspectiveService::doAction($objectId, $action, request()->all());

        return $this->withArray(
            [
            'action_id' =>  $actionId
            ]
        );
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $productCatalogsPerspectiveId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = ProductCatalogsPerspectiveService::getByRef($ref);

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
        $objects = ProductCatalogsPerspectiveService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created ProductCatalogsPerspective object on database.
     *
     * @param  ProductCatalogsPerspectiveCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(ProductCatalogsPerspectiveCreateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = ProductCatalogsPerspectiveService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates ProductCatalogsPerspective object on database.
     *
     * @param  $productCatalogsPerspectiveId
     * @param  ProductCatalogsPerspectiveUpdateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($productCatalogsPerspectiveId, ProductCatalogsPerspectiveUpdateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = ProductCatalogsPerspectiveService::update($productCatalogsPerspectiveId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates ProductCatalogsPerspective object on database.
     *
     * @param  $productCatalogsPerspectiveId
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($productCatalogsPerspectiveId)
    {
        $model = ProductCatalogsPerspectiveService::delete($productCatalogsPerspectiveId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
