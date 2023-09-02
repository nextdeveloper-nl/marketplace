<?php

namespace NextDeveloper\Marketplace\Services\AbstractServices;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProductCatalogQueryFilter;
use NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogCreatedEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogCreatingEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogUpdatedEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogUpdatingEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogDeletedEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProductCatalog\MarketplaceProductCatalogDeletingEvent;


/**
* This class is responsible from managing the data for MarketplaceProductCatalog
*
* Class MarketplaceProductCatalogService.
*
* @package NextDeveloper\Marketplace\Database\Models
*/
class AbstractMarketplaceProductCatalogService {
    public static function get(MarketplaceProductCatalogQueryFilter $filter = null, array $params = []) : Collection|LengthAwarePaginator {
        $enablePaginate = array_key_exists('paginate', $params);

        /**
        * Here we are adding null request since if filter is null, this means that this function is called from
        * non http application. This is actually not I think its a correct way to handle this problem but it's a workaround.
        *
        * Please let me know if you have any other idea about this; baris.bulut@nextdeveloper.com
        */
        if($filter == null)
            $filter = new MarketplaceProductCatalogQueryFilter(new Request());

        $perPage = config('commons.pagination.per_page');

        if($perPage == null)
            $perPage = 20;

        if(array_key_exists('per_page', $params)) {
            $perPage = intval($params['per_page']);

            if($perPage == 0)
                $perPage = 20;
        }

        if(array_key_exists('orderBy', $params)) {
            $filter->orderBy($params['orderBy']);
        }

        $model = MarketplaceProductCatalog::filter($filter);

        if($model && $enablePaginate)
            return $model->paginate($perPage);
        else
            return $model->get();
    }

    public static function getAll() {
        return MarketplaceProductCatalog::all();
    }

    /**
    * This method returns the model by looking at reference id
    *
    * @param $ref
    * @return mixed
    */
    public static function getByRef($ref) : ?MarketplaceProductCatalog {
        return MarketplaceProductCatalog::findByRef($ref);
    }

    /**
    * This method returns the model by lookint at its id
    *
    * @param $id
    * @return MarketplaceProductCatalog|null
    */
    public static function getById($id) : ?MarketplaceProductCatalog {
        return MarketplaceProductCatalog::where('id', $id)->first();
    }

    /**
    * This method created the model from an array.
    *
    * Throws an exception if stuck with any problem.
    *
    * @param array $data
    * @return mixed
    * @throw Exception
    */
    public static function create(array $data) {
        event( new MarketplaceProductCatalogCreatingEvent() );

                if (array_key_exists('marketplace_product_id', $data))
            $data['marketplace_product_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Marketplace\Database\Models\MarketplaceProduct',
                $data['marketplace_product_id']
            );
	        
        try {
            $model = MarketplaceProductCatalog::create($data);
        } catch(\Exception $e) {
            throw $e;
        }

        event( new MarketplaceProductCatalogCreatedEvent($model) );

        return $model->fresh();
    }

/**
* This function expects the ID inside the object.
*
* @param array $data
* @return MarketplaceProductCatalog
*/
public static function updateRaw(array $data) : ?MarketplaceProductCatalog
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
    * @param array $data
    * @return mixed
    * @throw Exception
    */
    public static function update($id, array $data) {
        $model = MarketplaceProductCatalog::where('uuid', $id)->first();

                if (array_key_exists('marketplace_product_id', $data))
            $data['marketplace_product_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Marketplace\Database\Models\MarketplaceProduct',
                $data['marketplace_product_id']
            );
	
        event( new MarketplaceProductCatalogUpdatingEvent($model) );

        try {
           $isUpdated = $model->update($data);
           $model = $model->fresh();
        } catch(\Exception $e) {
           throw $e;
        }

        event( new MarketplaceProductCatalogUpdatedEvent($model) );

        return $model->fresh();
    }

    /**
    * This method updated the model from an array.
    *
    * Throws an exception if stuck with any problem.
    *
    * @param
    * @param array $data
    * @return mixed
    * @throw Exception
    */
    public static function delete($id, array $data) {
        $model = MarketplaceProductCatalog::where('uuid', $id)->first();

        event( new MarketplaceProductCatalogDeletingEvent() );

        try {
            $model = $model->delete();
        } catch(\Exception $e) {
            throw $e;
        }

        event( new MarketplaceProductCatalogDeletedEvent($model) );

        return $model;
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
