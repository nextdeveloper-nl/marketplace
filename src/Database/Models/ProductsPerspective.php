<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Database\Traits\HasStates;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Marketplace\Database\Observers\ProductsPerspectiveObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Taggable;

/**
 * ProductsPerspective model.
 *
 * @package  NextDeveloper\Marketplace\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $name
 * @property string $description
 * @property string $content
 * @property array $highlights
 * @property $subscription_type
 * @property string $slug
 * @property string $version
 * @property string $sales_pitch
 * @property boolean $is_service
 * @property boolean $is_in_maintenance
 * @property boolean $is_public
 * @property boolean $is_invisible
 * @property boolean $is_active
 * @property boolean $is_approved
 * @property string $category
 * @property integer $common_category_id
 * @property string $marketplace
 * @property integer $marketplace_market_id
 * @property string $maintainer
 * @property string $about_maintainer
 * @property string $responsible
 * @property integer $product_catalog_count
 * @property boolean $has_free_trial
 * @property $starting_from
 * @property string $currency_code
 * @property string $partner_meeting_link
 * @property string $refund_policy
 * @property string $after_sales_introduction
 * @property string $support_content
 * @property string $eula
 * @property array $tags
 * @property integer $iam_account_id
 * @property integer $iam_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class ProductsPerspective extends Model
{
    use Filterable, UuidId, CleanCache, Taggable;
    use SoftDeletes;


    public $timestamps = true;

    protected $table = 'marketplace_products_perspective';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'name',
            'description',
            'content',
            'highlights',
            'subscription_type',
            'slug',
            'version',
            'sales_pitch',
            'is_service',
            'is_in_maintenance',
            'is_public',
            'is_invisible',
            'is_active',
            'is_approved',
            'category',
            'common_category_id',
            'marketplace',
            'marketplace_market_id',
            'maintainer',
            'about_maintainer',
            'responsible',
            'product_catalog_count',
            'has_free_trial',
            'starting_from',
            'currency_code',
            'partner_meeting_link',
            'refund_policy',
            'after_sales_introduction',
            'support_content',
            'eula',
            'tags',
            'iam_account_id',
            'iam_user_id',
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
    'slug' => 'string',
    'version' => 'string',
    'sales_pitch' => 'string',
    'is_service' => 'boolean',
    'is_in_maintenance' => 'boolean',
    'is_public' => 'boolean',
    'is_invisible' => 'boolean',
    'is_active' => 'boolean',
    'is_approved' => 'boolean',
    'category' => 'string',
    'common_category_id' => 'integer',
    'marketplace' => 'string',
    'marketplace_market_id' => 'integer',
    'maintainer' => 'string',
    'about_maintainer' => 'string',
    'responsible' => 'string',
    'product_catalog_count' => 'integer',
    'has_free_trial' => 'boolean',
    'currency_code' => 'string',
    'partner_meeting_link' => 'string',
    'refund_policy' => 'string',
    'after_sales_introduction' => 'string',
    'support_content' => 'string',
    'eula' => 'string',
    'tags' => \NextDeveloper\Commons\Database\Casts\TextArray::class,
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
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
        parent::observe(ProductsPerspectiveObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_products_perspective');

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
