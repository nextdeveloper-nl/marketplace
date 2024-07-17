<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Commons\Database\Traits\HasStates;
use NextDeveloper\Marketplace\Database\Observers\ProductCatalogsObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Taggable;

/**
 * ProductCatalogs model.
 *
 * @package  NextDeveloper\Marketplace\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $name
 * @property string $agreement
 * @property $args
 * @property $price
 * @property integer $marketplace_product_id
 * @property array $tags
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $sku
 * @property integer $trial_date
 * @property array $features
 * @property integer $iam_user_id
 * @property integer $iam_account_id
 * @property boolean $is_public
 * @property integer $quantity_in_inventory
 */
class ProductCatalogs extends Model
{
    use Filterable, UuidId, CleanCache, Taggable, HasStates;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'marketplace_product_catalogs';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'name',
            'agreement',
            'args',
            'price',
            'marketplace_product_id',
            'tags',
            'sku',
            'trial_date',
            'features',
            'iam_user_id',
            'iam_account_id',
            'is_public',
            'quantity_in_inventory',
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
    'agreement' => 'string',
    'args' => 'array',
    'marketplace_product_id' => 'integer',
    'tags' => \NextDeveloper\Commons\Database\Casts\TextArray::class,
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    'sku' => 'string',
    'trial_date' => 'integer',
    'features' => \NextDeveloper\Commons\Database\Casts\TextArray::class,
    'is_public' => 'boolean',
    'quantity_in_inventory' => 'integer',
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
        parent::observe(ProductCatalogsObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_product_catalogs');

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

    public function subscriptions() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\NextDeveloper\Marketplace\Database\Models\Subscriptions::class);
    }

    public function products() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\Marketplace\Database\Models\Products::class);
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
