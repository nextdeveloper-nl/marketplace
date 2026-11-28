<?php

namespace NextDeveloper\Marketplace\Http\Controllers\StockMovements;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\StockMovements\StockMovementsUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\StockMovementsQueryFilter;
use NextDeveloper\Marketplace\Database\Models\StockMovements;
use NextDeveloper\Marketplace\Services\StockMovementsService;
use NextDeveloper\Marketplace\Http\Requests\StockMovements\StockMovementsCreateRequest;
use NextDeveloper\Commons\Http\Traits\Tags;use NextDeveloper\Commons\Http\Traits\Addresses;
class StockMovementsController extends AbstractController
{
    private $model = StockMovements::class;

    use Tags;
    use Addresses;
    /**
     * This method returns the list of stockmovements.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  StockMovementsQueryFilter $filter  An object that builds search query
     * @param  Request                         $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(StockMovementsQueryFilter $filter, Request $request)
    {
        $data = StockMovementsService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This function returns the list of actions that can be performed on this object.
     *
     * @return void
     */
    public function getActions()
    {
        $data = StockMovementsService::getActions();

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
        $actionId = StockMovementsService::doAction($objectId, $action, request()->all());

        return $this->withArray(
            [
            'action_id' =>  $actionId
            ]
        );
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $stockMovementsId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = StockMovementsService::getByRef($ref);

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
        $objects = StockMovementsService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created StockMovements object on database.
     *
     * @param  StockMovementsCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(StockMovementsCreateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = StockMovementsService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates StockMovements object on database.
     *
     * @param  $stockMovementsId
     * @param  StockMovementsUpdateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($stockMovementsId, StockMovementsUpdateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = StockMovementsService::update($stockMovementsId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates StockMovements object on database.
     *
     * @param  $stockMovementsId
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($stockMovementsId)
    {
        $model = StockMovementsService::delete($stockMovementsId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Units moved per plan and movement type (sold, rented out, returned, received, adjusted), optionally per
     * day / week / month. Query params: marketplace_product_id, marketplace_product_catalog_id, moved_at_start,
     * moved_at_end, interval.
     */
    public function summary(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = $request->only([
            'marketplace_product_id',
            'marketplace_product_catalog_id',
            'moved_at_start',
            'moved_at_end',
            'interval',
        ]);

        return response()->json(['data' => StockMovementsService::summary($params)]);
    }

}
