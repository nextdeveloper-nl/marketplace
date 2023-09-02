<?php

namespace NextDeveloper\Marketplace\Services\AbstractServices;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Marketplace\Database\Models\MarketplaceProduct;
use NextDeveloper\Marketplace\Database\Filters\MarketplaceProductQueryFilter;
use NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductCreatedEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductCreatingEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductUpdatedEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductUpdatingEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductDeletedEvent;
use NextDeveloper\Marketplace\Events\MarketplaceProduct\MarketplaceProductDeletingEvent;


/**
* This class is responsible from managing the data for MarketplaceProduct
*
* Class MarketplaceProductService.
*
* @package NextDeveloper\Marketplace\Database\Models
*/
class AbstractMarketplaceProductService {
    public static function get(MarketplaceProductQueryFilter $filter = null, array $params = []) : Collection|LengthAwarePaginator {
        $enablePaginate = array_key_exists('paginate', $params);

        /**
        * Here we are adding null request since if filter is null, this means that this function is called from
        * non http application. This is actually not I think its a correct way to handle this problem but it's a workaround.
        *
        * Please let me know if you have any other idea about this; baris.bulut@nextdeveloper.com
        */
        if($filter == null)
            $filter = new MarketplaceProductQueryFilter(new Request());

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

        $model = MarketplaceProduct::filter($filter);

        if($model && $enablePaginate)
            return $model->paginate($perPage);
        else
            return $model->get();
    }

    public static function getAll() {
        return MarketplaceProduct::all();
    }

    /**
    * This method returns the model by looking at reference id
    *
    * @param $ref
    * @return mixed
    */
    public static function getByRef($ref) : ?MarketplaceProduct {
        return MarketplaceProduct::findByRef($ref);
    }

    /**
    * This method returns the model by lookint at its id
    *
    * @param $id
    * @return MarketplaceProduct|null
    */
    public static function getById($id) : ?MarketplaceProduct {
        return MarketplaceProduct::where('id', $id)->first();
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
        event( new MarketplaceProductCreatingEvent() );

                if (array_key_exists('common_category_id', $data))
            $data['common_category_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Commons\Database\Models\CommonCategory',
                $data['common_category_id']
            );
	        if (array_key_exists('common_country_id', $data))
            $data['common_country_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Commons\Database\Models\CommonCountry',
                $data['common_country_id']
            );
	        if (array_key_exists('common_language_id', $data))
            $data['common_language_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Commons\Database\Models\CommonLanguage',
                $data['common_language_id']
            );
	        if (array_key_exists('iam_account_id', $data))
            $data['iam_account_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\IAM\Database\Models\IamAccount',
                $data['iam_account_id']
            );
	        if (array_key_exists('iam_user_id', $data))
            $data['iam_user_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\IAM\Database\Models\IamUser',
                $data['iam_user_id']
            );
	        
        try {
            $model = MarketplaceProduct::create($data);
        } catch(\Exception $e) {
            throw $e;
        }

        event( new MarketplaceProductCreatedEvent($model) );

        return $model->fresh();
    }

/**
* This function expects the ID inside the object.
*
* @param array $data
* @return MarketplaceProduct
*/
public static function updateRaw(array $data) : ?MarketplaceProduct
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
        $model = MarketplaceProduct::where('uuid', $id)->first();

                if (array_key_exists('common_category_id', $data))
            $data['common_category_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Commons\Database\Models\CommonCategory',
                $data['common_category_id']
            );
	        if (array_key_exists('common_country_id', $data))
            $data['common_country_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Commons\Database\Models\CommonCountry',
                $data['common_country_id']
            );
	        if (array_key_exists('common_language_id', $data))
            $data['common_language_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Commons\Database\Models\CommonLanguage',
                $data['common_language_id']
            );
	        if (array_key_exists('iam_account_id', $data))
            $data['iam_account_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\IAM\Database\Models\IamAccount',
                $data['iam_account_id']
            );
	        if (array_key_exists('iam_user_id', $data))
            $data['iam_user_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\IAM\Database\Models\IamUser',
                $data['iam_user_id']
            );
	
        event( new MarketplaceProductUpdatingEvent($model) );

        try {
           $isUpdated = $model->update($data);
           $model = $model->fresh();
        } catch(\Exception $e) {
           throw $e;
        }

        event( new MarketplaceProductUpdatedEvent($model) );

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
        $model = MarketplaceProduct::where('uuid', $id)->first();

        event( new MarketplaceProductDeletingEvent() );

        try {
            $model = $model->delete();
        } catch(\Exception $e) {
            throw $e;
        }

        event( new MarketplaceProductDeletedEvent($model) );

        return $model;
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
