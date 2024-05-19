<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Marketplace\Database\Observers\ProductsObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Taggable;

/**
 * Products model.
 *
 * @package  NextDeveloper\Marketplace\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $name
 * @property string $description
 * @property string $content
 * @property array $highlights
 * @property string $after_sales_introduction
 * @property string $support_content
 * @property string $refund_policy
 * @property string $eula
 * @property $subscription_type
 * @property string $slug
 * @property string $version
 * @property $product_type
 * @property boolean $is_in_maintenance
 * @property boolean $is_public
 * @property boolean $is_invisible
 * @property boolean $is_active
 * @property integer $common_category_id
 * @property integer $iam_account_id
 * @property integer $iam_user_id
 * @property array $tags
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property boolean $is_service
 * @property integer $marketplace_market_id
 * @property string $sales_pitch
 */
class Products extends Model
{
    use Filterable, UuidId, CleanCache, Taggable;
    use SoftDeletes;


    public $timestamps = true;

    protected $table = 'marketplace_products';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'name',
            'description',
            'content',
            'highlights',
            'after_sales_introduction',
            'support_content',
            'refund_policy',
            'eula',
            'subscription_type',
            'slug',
            'version',
            'product_type',
            'is_in_maintenance',
            'is_public',
            'is_invisible',
            'is_active',
            'common_category_id',
            'iam_account_id',
            'iam_user_id',
            'tags',
            'is_service',
            'marketplace_market_id',
            'sales_pitch',
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
    'description' => 'string',
    'content' => 'string',
    'highlights' => \NextDeveloper\Commons\Database\Casts\TextArray::class,
    'after_sales_introduction' => 'string',
    'support_content' => 'string',
    'refund_policy' => 'string',
    'eula' => 'string',
    'slug' => 'string',
    'version' => 'string',
    'is_in_maintenance' => 'boolean',
    'is_public' => 'boolean',
    'is_invisible' => 'boolean',
    'is_active' => 'boolean',
    'common_category_id' => 'integer',
    'tags' => \NextDeveloper\Commons\Database\Casts\TextArray::class,
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    'is_service' => 'boolean',
    'marketplace_market_id' => 'integer',
    'sales_pitch' => 'string',
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
        parent::observe(ProductsObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_products');

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
