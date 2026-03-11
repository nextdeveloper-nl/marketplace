<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Database\Traits\HasStates;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Marketplace\Database\Observers\OrderItemsPerspectiveObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Taggable;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * OrderItemsPerspective model.
 *
 * @package  NextDeveloper\Marketplace\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $name
 * @property integer $marketplace_order_id
 * @property integer $marketplace_product_catalog_id
 * @property integer $quantity
 * @property integer $quantity_in_inventory
 * @property $price_per_item
 * @property $total_price
 * @property $modifiers
 * @property string $special_instructions
 * @property $item_data
 * @property string $sku
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property integer $iam_account_id
 * @property integer $iam_user_id
 * @property string $delivery_method
 * @property string $product_name
 * @property string $provider_name
 * @property string $order_number
 * @property string $status
 * @property \Carbon\Carbon $ordered_at
 * @property \Carbon\Carbon $accepted_at
 * @property string $customer_note
 */
class OrderItemsPerspective extends Model
{
    use Filterable, UuidId, CleanCache, Taggable, HasStates, RunAsAdministrator;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'marketplace_order_items_perspective';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'name',
            'marketplace_order_id',
            'marketplace_product_catalog_id',
            'quantity',
            'quantity_in_inventory',
            'price_per_item',
            'total_price',
            'modifiers',
            'special_instructions',
            'item_data',
            'sku',
            'iam_account_id',
            'iam_user_id',
            'delivery_method',
            'product_name',
            'provider_name',
            'order_number',
            'status',
            'ordered_at',
            'accepted_at',
            'customer_note',
    ];

    /**
      Here we have the fulltext fields. We can use these for fulltext search if enabled.
     */
    protected $fullTextFields = [

    ];

    /**
     @var array
     */
    protected $appends = [

    ];

    /**
     We are casting fields to objects so that we can work on them better
     *
     @var array
     */
    protected $casts = [
    'id' => 'integer',
    'name' => 'string',
    'marketplace_order_id' => 'integer',
    'marketplace_product_catalog_id' => 'integer',
    'quantity' => 'integer',
    'quantity_in_inventory' => 'integer',
    'modifiers' => 'array',
    'special_instructions' => 'string',
    'item_data' => 'array',
    'sku' => 'string',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    'delivery_method' => 'string',
    'product_name' => 'string',
    'provider_name' => 'string',
    'order_number' => 'string',
    'status' => 'string',
    'ordered_at' => 'datetime',
    'accepted_at' => 'datetime',
    'customer_note' => 'string',
    ];

    /**
     We are casting data fields.
     *
     @var array
     */
    protected $dates = [
    'created_at',
    'updated_at',
    'deleted_at',
    'ordered_at',
    'accepted_at',
    ];

    /**
     @var array
     */
    protected $with = [

    ];

    /**
     @var int
     */
    protected $perPage = 20;

    /**
     @return void
     */
    public static function boot()
    {
        parent::boot();

        //  We create and add Observer even if we wont use it.
        parent::observe(OrderItemsPerspectiveObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_order_items_perspective');

        if(!$modelScopes) { $modelScopes = [];
        }
        if (!$globalScopes) { $globalScopes = [];
        }

        $scopes = array_merge(
            $globalScopes,
            $modelScopes
        );

        if($scopes) {
            foreach ($scopes as $scope) {
                static::addGlobalScope(app($scope));
            }
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE


}
