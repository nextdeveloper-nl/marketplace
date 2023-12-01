<?php

namespace NextDeveloper\Marketplace\Services\AbstractServices;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Filters\ProductCatalogsQueryFilter;
use NextDeveloper\Marketplace\Events\ProductCatalogs\ProductCatalogsCreatedEvent;
use NextDeveloper\Marketplace\Events\ProductCatalogs\ProductCatalogsCreatingEvent;
use NextDeveloper\Marketplace\Events\ProductCatalogs\ProductCatalogsUpdatedEvent;
use NextDeveloper\Marketplace\Events\ProductCatalogs\ProductCatalogsUpdatingEvent;
use NextDeveloper\Marketplace\Events\ProductCatalogs\ProductCatalogsDeletedEvent;
use NextDeveloper\Marketplace\Events\ProductCatalogs\ProductCatalogsDeletingEvent;


/**
 * This class is responsible from managing the data for ProductCatalogs
 *
 * Class ProductCatalogsService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class AbstractProductCatalogsService
{
    public static function get(ProductCatalogsQueryFilter $filter = null, array $params = []) : Collection|LengthAwarePaginator
    {
        $enablePaginate = array_key_exists('paginate', $params);

        /**
        * Here we are adding null request since if filter is null, this means that this function is called from
        * non http application. This is actually not I think its a correct way to handle this problem but it's a workaround.
        *
        * Please let me know if you have any other idea about this; baris.bulut@nextdeveloper.com
        */
        if($filter == null) {
            $filter = new ProductCatalogsQueryFilter(new Request());
        }

        $perPage = config('commons.pagination.per_page');

        if($perPage == null) {
            $perPage = 20;
        }

        if(array_key_exists('per_page', $params)) {
            $perPage = intval($params['per_page']);

            if($perPage == 0) {
                $perPage = 20;
            }
        }

        if(array_key_exists('orderBy', $params)) {
            $filter->orderBy($params['orderBy']);
        }

        $model = ProductCatalogs::filter($filter);

        if($model && $enablePaginate) {
            return $model->paginate($perPage);
        } else {
            return $model->get();
        }
    }

    public static function getAll()
    {
        return ProductCatalogs::all();
    }

    /**
     * This method returns the model by looking at reference id
     *
     * @param  $ref
     * @return mixed
     */
    public static function getByRef($ref) : ?ProductCatalogs
    {
        return ProductCatalogs::findByRef($ref);
    }

    /**
     * This method returns the model by lookint at its id
     *
     * @param  $id
     * @return ProductCatalogs|null
     */
    public static function getById($id) : ?ProductCatalogs
    {
        return ProductCatalogs::where('id', $id)->first();
    }

    /**
     * This method created the model from an array.
     *
     * Throws an exception if stuck with any problem.
     *
     * @param  array $data
     * @return mixed
     * @throw  Exception
     */
    public static function create(array $data)
    {
        event(new ProductCatalogsCreatingEvent());

        if (array_key_exists('marketplace_product_id', $data)) {
            $data['marketplace_product_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Marketplace\Database\Models\Products',
                $data['marketplace_product_id']
            );
        }
    
        try {
            $model = ProductCatalogs::create($data);
        } catch(\Exception $e) {
            throw $e;
        }

        event(new ProductCatalogsCreatedEvent($model));

        return $model->fresh();
    }

    /**
     This function expects the ID inside the object.
    
     @param  array $data
     @return ProductCatalogs
     */
    public static function updateRaw(array $data) : ?ProductCatalogs
    {
        if(array_key_exists('id', $data)) {
            return self::update($data['id'], $data);
        }

        return null;
    }

    /**
     * This method updated the model from an array.
     *
     * Throws an exception if stuck with any problem.
     *
     * @param
     * @param  array $data
     * @return mixed
     * @throw  Exception
     */
    public static function update($id, array $data)
    {
        $model = ProductCatalogs::where('uuid', $id)->first();

        if (array_key_exists('marketplace_product_id', $data)) {
            $data['marketplace_product_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Marketplace\Database\Models\Products',
                $data['marketplace_product_id']
            );
        }
    
        event(new ProductCatalogsUpdatingEvent($model));

        try {
            $isUpdated = $model->update($data);
            $model = $model->fresh();
        } catch(\Exception $e) {
            throw $e;
        }

        event(new ProductCatalogsUpdatedEvent($model));

        return $model->fresh();
    }

    /**
     * This method updated the model from an array.
     *
     * Throws an exception if stuck with any problem.
     *
     * @param
     * @param  array $data
     * @return mixed
     * @throw  Exception
     */
    public static function delete($id)
    {
        $model = ProductCatalogs::where('uuid', $id)->first();

        event(new ProductCatalogsDeletingEvent());

        try {
            $model = $model->delete();
        } catch(\Exception $e) {
            throw $e;
        }

        return $model;
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
