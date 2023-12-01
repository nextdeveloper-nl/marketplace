<?php

namespace NextDeveloper\Marketplace\Database\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * This is the trait that we add to a class when we need to create a product out of this item.
 * This class creates a product in the marketplace table for this object.
 */
trait Productable
{
    public static function bootProductable()
    {
        //  This function runs when there is a new item created in that table.
        static::created(function ($model) {
            self::create($model);
        });
    }

    public function product() {

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
