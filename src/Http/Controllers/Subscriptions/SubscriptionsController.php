<?php

namespace NextDeveloper\Marketplace\Http\Controllers\Subscriptions;

use Illuminate\Http\Request;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Marketplace\Http\Requests\Subscriptions\SubscriptionsUpdateRequest;
use NextDeveloper\Marketplace\Database\Filters\SubscriptionsQueryFilter;
use NextDeveloper\Marketplace\Database\Models\Subscriptions;
use NextDeveloper\Marketplace\Services\SubscriptionsService;
use NextDeveloper\Marketplace\Http\Requests\Subscriptions\SubscriptionsCreateRequest;
use NextDeveloper\Commons\Http\Traits\Tags;
class SubscriptionsController extends AbstractController
{
    private $model = Subscriptions::class;

    use Tags;
    /**
     * This method returns the list of subscriptions.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  SubscriptionsQueryFilter $filter  An object that builds search query
     * @param  Request                  $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(SubscriptionsQueryFilter $filter, Request $request)
    {
        $data = SubscriptionsService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $subscriptionsId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = SubscriptionsService::getByRef($ref);

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
        $objects = SubscriptionsService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created Subscriptions object on database.
     *
     * @param  SubscriptionsCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(SubscriptionsCreateRequest $request)
    {
        $model = SubscriptionsService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates Subscriptions object on database.
     *
     * @param  $subscriptionsId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($subscriptionsId, SubscriptionsUpdateRequest $request)
    {
        $model = SubscriptionsService::update($subscriptionsId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates Subscriptions object on database.
     *
     * @param  $subscriptionsId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($subscriptionsId)
    {
        $model = SubscriptionsService::delete($subscriptionsId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
