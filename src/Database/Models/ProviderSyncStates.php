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
use NextDeveloper\Marketplace\Database\Observers\ProviderSyncStatesObserver;

/**
 * ProviderSyncStates model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $marketplace_provider_id
 * @property string $entity_type
 * @property Carbon $cursor_updated_at
 * @property Carbon $last_run_at
 * @property Carbon $last_success_at
 * @property int $consecutive_failures
 * @property string $last_error
 * @property int $records_processed
 * @property int $iam_account_id
 * @property int $iam_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class ProviderSyncStates extends Model
{
    use CleanCache, Filterable, HasStates, RunAsAdministrator, Taggable, UuidId;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'marketplace_provider_sync_states';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
        'marketplace_provider_id',
        'entity_type',
        'cursor_updated_at',
        'last_run_at',
        'last_success_at',
        'consecutive_failures',
        'last_error',
        'records_processed',
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
        'marketplace_provider_id' => 'integer',
        'entity_type' => 'string',
        'cursor_updated_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_success_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'last_error' => 'string',
        'records_processed' => 'integer',
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
        parent::observe(ProviderSyncStatesObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_provider_sync_states');

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
