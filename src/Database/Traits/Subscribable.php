<?php

namespace NextDeveloper\Marketplace\Database\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Subscribable is an item which is created when a subscription starts to a certain service or a product.
 *
 * When there is subscribable created, we ask for an abstract function of what is the catalog item and the price field
 * for this product. This information should be saved in a variable called $subscribable. The information we are
 * looking for are;
 *
 * If this is a hotel room reservation;
 *
 * - catalog_class -> StayRooms::class
 * - catalog_id_field -> 'stay_room_id'
 */
trait Subscribable
{
    public static function bootSubscribable()
    {
        //  This function runs when there is a new item created in that table.
        static::created(function ($model) {
            self::create($model);
        });
    }

    public function subscription() {

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
