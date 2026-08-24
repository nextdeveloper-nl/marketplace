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
use NextDeveloper\Marketplace\Database\Observers\WebhookEventsObserver;

/**
 * WebhookEvents model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $marketplace_provider_id
 * @property string $topic
 * @property string $external_event_id
 * @property Carbon $triggered_at
 * @property string $shop_domain
 * @property array $payload
 * @property array $headers
 * @property bool $is_processed
 * @property Carbon $processed_at
 * @property int $attempts
 * @property string $error_message
 * @property int $iam_account_id
 * @property int $iam_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class WebhookEvents extends Model
{
    use CleanCache, Filterable, HasStates, RunAsAdministrator, Taggable, UuidId;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'marketplace_webhook_events';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
        'marketplace_provider_id',
        'topic',
        'external_event_id',
        'triggered_at',
        'shop_domain',
        'payload',
        'headers',
        'is_processed',
        'processed_at',
        'attempts',
        'error_message',
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
        'topic' => 'string',
        'external_event_id' => 'string',
        'triggered_at' => 'datetime',
        'shop_domain' => 'string',
        'payload' => 'array',
        'headers' => 'array',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
        'error_message' => 'string',
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
        parent::observe(WebhookEventsObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_webhook_events');

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
