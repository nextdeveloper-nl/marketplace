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
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Helpers\ProductOfferings;
use NextDeveloper\Commons\Database\Traits\Taggable;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

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
 * @property integer $quantity_in_inventory
 * @property integer $trial_date
 * @property array $features
 * @property boolean $is_public
 * @property integer $iam_account_id
 * @property integer $iam_user_id
 * @property integer $common_currency_id
 * @property string $stock_status
 * @property \Carbon\Carbon $expected_arrival_at
 * @property string $offering_type
 * @property string $billing_period
 * @property integer $min_rental_duration
 * @property integer $max_rental_duration
 */
class ProductCatalogs extends Model
{
    use Filterable, UuidId, CleanCache, Taggable, HasStates, RunAsAdministrator;
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
            'quantity_in_inventory',
            'trial_date',
            'features',
            'is_public',
            'iam_account_id',
            'iam_user_id',
            'common_currency_id',
            'stock_status',
            'expected_arrival_at',
            'offering_type',
            'billing_period',
            'min_rental_duration',
            'max_rental_duration',
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
    'quantity_in_inventory' => 'integer',
    'trial_date' => 'integer',
    'features' => \NextDeveloper\Commons\Database\Casts\TextArray::class,
    'is_public' => 'boolean',
    'common_currency_id' => 'integer',
    'stock_status' => 'string',
    'expected_arrival_at' => 'datetime',
    'offering_type' => 'string',
    'billing_period' => 'string',
    'min_rental_duration' => 'integer',
    'max_rental_duration' => 'integer',
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
    'expected_arrival_at',
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

    public function products() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\Marketplace\Database\Models\Products::class);
    }

    public function subscriptions() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\NextDeveloper\Marketplace\Database\Models\Subscriptions::class);
    }

    public function orderItems() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\NextDeveloper\Marketplace\Database\Models\OrderItems::class);
    }

    public function productCatalogMappings() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings::class);
    }

    public function stockMovements() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\NextDeveloper\Marketplace\Database\Models\StockMovements::class, 'marketplace_product_catalog_id');
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * The product's transformed output (ProductsPerspective: starting_from, currency_code, has_free_trial, plan
     * count) is built from its plans and cached with no expiry. CleanCache only clears the plan's own keys, so
     * a plan change must also clear its product's, or the product keeps showing the old price.
     */
    protected static function booted(): void
    {
        static::saving(fn (self $catalog) => $catalog->assertConsistentOffering());
        static::saved(fn (self $catalog) => $catalog->forgetProductCache());
        static::deleted(fn (self $catalog) => $catalog->forgetProductCache());
        static::restored(fn (self $catalog) => $catalog->forgetProductCache());
    }

    /**
     * Offering type, billing period and rental durations must agree (leo4#1397). Checked on the plan's final values,
     * so a partial update (only billing_period, say) is checked against what the row already holds, and every write
     * path (API, first plan created with a product, sync jobs) gets the same rules. A plan with none of them set, as
     * every plan written before them, always passes.
     *
     * An offering type saved without a period gets the type's default (sale = one_time, rental = day or month,
     * service = day).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertConsistentOffering(): void
    {
        if ($this->offering_type !== null && $this->billing_period === null) {
            $this->billing_period = ProductOfferings::billingPeriodFor($this->offering_type, null);
        }

        $errors = ProductOfferings::planErrors(
            $this->offering_type,
            $this->billing_period,
            $this->min_rental_duration,
            $this->max_rental_duration,
        );

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    /**
     * Clears the cached output of the product this plan belongs to, and of the previous product when the plan
     * was moved to another one.
     */
    public function forgetProductCache(): void
    {
        $productIds = array_unique(array_filter([
            $this->marketplace_product_id,
            $this->getOriginal('marketplace_product_id'),
        ]));

        if (! $productIds) {
            return;
        }

        $productUuids = Products::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $productIds)
            ->pluck('uuid');

        foreach ($productUuids as $productUuid) {
            CacheHelper::deleteKeys(Products::class, $productUuid);
        }
    }

















}
