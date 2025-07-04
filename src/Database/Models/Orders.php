<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Marketplace\Database\Observers\OrdersObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Taggable;

/**
 * Orders model.
 *
 * @package  NextDeveloper\Marketplace\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property integer $marketplace_market_id
 * @property integer $marketplace_provider_id
 * @property integer $marketplace_product_id
 * @property string $external_order_id
 * @property string $external_order_number
 * @property string $status
 * @property \Carbon\Carbon $ordered_at
 * @property \Carbon\Carbon $accepted_at
 * @property \Carbon\Carbon $prepared_at
 * @property \Carbon\Carbon $dispatched_at
 * @property \Carbon\Carbon $delivered_at
 * @property \Carbon\Carbon $cancelled_at
 * @property $customer_data
 * @property $delivery_address
 * @property $marketplace_metadata
 * @property $subtotal_amount
 * @property $delivery_fee
 * @property $service_fee
 * @property $tax_amount
 * @property $discount_amount
 * @property $total_amount
 * @property string $order_type
 * @property string $delivery_method
 * @property \Carbon\Carbon $estimated_delivery_time
 * @property $raw_order_data
 * @property \Carbon\Carbon $last_synced_at
 * @property string $sync_error_message
 * @property integer $iam_account_id
 * @property integer $iam_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $customer_note
 * @property string $external_line_id
 */
class Orders extends Model
{
    use Filterable, CleanCache, Taggable;
    use UuidId;
    use SoftDeletes;


    public $timestamps = true;




    protected $table = 'marketplace_orders';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'marketplace_market_id',
            'marketplace_provider_id',
            'marketplace_product_id',
            'external_order_id',
            'external_order_number',
            'status',
            'ordered_at',
            'accepted_at',
            'prepared_at',
            'dispatched_at',
            'delivered_at',
            'cancelled_at',
            'customer_data',
            'delivery_address',
            'marketplace_metadata',
            'subtotal_amount',
            'delivery_fee',
            'service_fee',
            'tax_amount',
            'discount_amount',
            'total_amount',
            'order_type',
            'delivery_method',
            'estimated_delivery_time',
            'raw_order_data',
            'last_synced_at',
            'sync_error_message',
            'iam_account_id',
            'iam_user_id',
            'customer_note',
            'external_line_id',
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
    'marketplace_market_id' => 'integer',
    'marketplace_provider_id' => 'integer',
    'marketplace_product_id' => 'integer',
    'external_order_id' => 'string',
    'external_order_number' => 'string',
    'status' => 'string',
    'ordered_at' => 'datetime',
    'accepted_at' => 'datetime',
    'prepared_at' => 'datetime',
    'dispatched_at' => 'datetime',
    'delivered_at' => 'datetime',
    'cancelled_at' => 'datetime',
    'customer_data' => 'array',
    'delivery_address' => 'array',
    'marketplace_metadata' => 'array',
    'order_type' => 'string',
    'delivery_method' => 'string',
    'estimated_delivery_time' => 'datetime',
    'raw_order_data' => 'array',
    'last_synced_at' => 'datetime',
    'sync_error_message' => 'string',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    'customer_note' => 'string',
    'external_line_id' => 'string',
    ];

    /**
     We are casting data fields.
     *
     @var array
     */
    protected $dates = [
    'ordered_at',
    'accepted_at',
    'prepared_at',
    'dispatched_at',
    'delivered_at',
    'cancelled_at',
    'estimated_delivery_time',
    'last_synced_at',
    'created_at',
    'updated_at',
    'deleted_at',
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
        parent::observe(OrdersObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_orders');

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
