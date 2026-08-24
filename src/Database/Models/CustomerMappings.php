<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Commons\Database\Traits\HasStates;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;
use NextDeveloper\Commons\Database\Traits\Taggable;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Marketplace\Database\Observers\CustomerMappingsObserver;

/**
 * CustomerMappings model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $iam_user_id
 * @property int $marketplace_provider_id
 * @property int $marketplace_market_id
 * @property string $external_customer_id
 * @property string $sync_hash
 * @property Carbon $external_updated_at
 * @property Carbon $last_synced_at
 * @property int $iam_account_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class CustomerMappings extends Model
{
    use CleanCache, Filterable, HasStates, RunAsAdministrator, Taggable, UuidId;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'marketplace_customer_mappings';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
        'iam_user_id',
        'marketplace_provider_id',
        'marketplace_market_id',
        'external_customer_id',
        'sync_hash',
        'external_updated_at',
        'last_synced_at',
        'iam_account_id',
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
        'iam_user_id' => 'integer',
        'marketplace_provider_id' => 'integer',
        'marketplace_market_id' => 'integer',
        'external_customer_id' => 'string',
        'sync_hash' => 'string',
        'external_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
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
        parent::observe(CustomerMappingsObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_customer_mappings');

        if (! $modelScopes) {
            $modelScopes = [];
        }
        if (! $globalScopes) {
            $globalScopes = [];
        }

        $scopes = array_merge(
            $globalScopes,
            $modelScopes
        );

        if ($scopes) {
            foreach ($scopes as $scope) {
                static::addGlobalScope(app($scope));
            }
        }
    }

    public function providers(): BelongsTo
    {
        return $this->belongsTo(Providers::class, 'marketplace_provider_id');
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
