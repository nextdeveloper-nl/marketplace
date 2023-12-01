<?php

namespace NextDeveloper\Marketplace\Database\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Catalogable is an item that can be used to create a product catalog. This can be either a hotel room, or
 * golf course. The subscription on the other hand will be room reservation or tee time reservation.
 *
 * While creating this catalog, we are looking for the parent product. Regardless to how many products
 * are there for the related object, we create the product catalog for all products that is created in the products
 * table related to that object.
 *
 * For instance; Lets say we have a product called hotel. Until a certain point hotel does not have a restaurant, but
 * after a point this hotel object now have a restaurant catalog. Then we create the restaurant catalog for all
 * hotel products in the database.
 *
 * In this case we need to know which relation has made in between the product and the catalog. That is why we
 * need to know the object for the product and the relation field for that product. To know that we need to know the
 * specific $catalogable variable including the product_class and product_id_field
 */
trait Catalogable
{
    public static function bootCatalogable()
    {
        //  This function runs when there is a new item created in that table.
        static::created(function ($model) {
            self::create($model);
        });
    }

    public function catalog() {

    }

    /**
     * Creates a new product on the database related to this item.
     *
     * @param Model $model
     * @return void
     */
    private static function create(Model $model) {

    }
}
