<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Marketplace\Database\Observers\ProductMappingsObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Taggable;
use NextDeveloper\Commons\Database\Traits\HasStates;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * ProductMappings model.
 *
 * @package  NextDeveloper\Marketplace\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property integer $marketplace_product_id
 * @property integer $marketplace_provider_id
 * @property string $external_product_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class ProductMappings extends Model
{
    use Filterable, UuidId, CleanCache, Taggable, HasStates, RunAsAdministrator;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'marketplace_product_mappings';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'marketplace_product_id',
            'marketplace_provider_id',
            'external_product_id',
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
    'marketplace_product_id' => 'integer',
    'marketplace_provider_id' => 'integer',
    'external_product_id' => 'string',
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
        parent::observe(ProductMappingsObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_product_mappings');

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
    
    public function providers() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\Marketplace\Database\Models\Providers::class);
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE









}
