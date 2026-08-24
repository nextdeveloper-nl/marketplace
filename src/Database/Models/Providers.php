<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Commons\Database\Traits\HasStates;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;
use NextDeveloper\Commons\Database\Traits\Taggable;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\IAM\Database\Models\Accounts;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\Marketplace\Database\Observers\ProvidersObserver;

/**
 * Providers model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $description
 * @property string $action
 * @property string $url
 * @property int $marketplace_market_id
 * @property int $iam_account_id
 * @property int $iam_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 * @property $api_config
 * @property bool $is_active
 * @property string $adapter
 */
class Providers extends Model
{
    use CleanCache, Filterable, HasStates, RunAsAdministrator, Taggable, UuidId;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'marketplace_providers';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
        'name',
        'description',
        'action',
        'url',
        'marketplace_market_id',
        'iam_account_id',
        'iam_user_id',
        'api_config',
        'is_active',
        'adapter',
        'access_token_enc',
        'webhook_secret_enc',
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
        'action' => 'string',
        'url' => 'string',
        'marketplace_market_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'api_config' => 'array',
        'is_active' => 'boolean',
        'adapter' => 'string',
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
        parent::observe(ProvidersObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('marketplace.scopes.global');
        $modelScopes = config('marketplace.scopes.marketplace_providers');

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

    public function products(): HasMany
    {
        return $this->hasMany(Products::class);
    }

    public function markets(): BelongsTo
    {
        return $this->belongsTo(Markets::class);
    }

    public function accounts(): BelongsTo
    {
        return $this->belongsTo(Accounts::class);
    }

    public function users(): BelongsTo
    {
        return $this->belongsTo(Users::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Orders::class);
    }

    public function productMappings(): HasMany
    {
        return $this->hasMany(ProductMappings::class);
    }

    public function productCatalogMappings(): HasMany
    {
        return $this->hasMany(ProductCatalogMappings::class);
    }

    public function statusMappings(): HasMany
    {
        return $this->hasMany(StatusMappings::class);
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Encrypt the Shopify Admin API access token on write.
     *
     * Deliberately not stored in api_config: that column is plaintext json and
     * holds non-secret connection settings. Shopify classes any app touching
     * customer data as Level 2 protected data, which requires encryption at
     * rest, so the credential lives in its own encrypted column instead.
     *
     * Mutator only, mirroring commons ExternalServices and accounting
     * CreditCards: reads return ciphertext and callers decrypt explicitly, so
     * a stray toArray() cannot leak the token into a log or an API response.
     */
    public function setAccessTokenEncAttribute($value): void
    {
        $this->attributes['access_token_enc'] = $value === null || $value === ''
            ? null
            : encrypt($value);
    }

    /**
     * Encrypt the webhook signing secret on write. See setAccessTokenEncAttribute().
     */
    public function setWebhookSecretEncAttribute($value): void
    {
        $this->attributes['webhook_secret_enc'] = $value === null || $value === ''
            ? null
            : encrypt($value);
    }

    /**
     * Decrypted Shopify access token, or null when the shop is not connected.
     *
     * Returns null rather than throwing on a decrypt failure: an APP_KEY
     * rotation must surface as "reconnect this shop", not as a fatal in a
     * queue worker looping over every provider.
     */
    public function getDecryptedAccessToken(): ?string
    {
        return $this->decryptColumn('access_token_enc');
    }

    /**
     * Decrypted webhook signing secret, or null when none has been exchanged.
     */
    public function getDecryptedWebhookSecret(): ?string
    {
        return $this->decryptColumn('webhook_secret_enc');
    }

    /**
     * Decode api_config, which is plain json and may arrive as a string.
     *
     * @return array<string, mixed>
     */
    public function getApiConfigArray(): array
    {
        $config = $this->api_config;

        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        return is_array($config) ? $config : [];
    }

    /**
     * Read one key out of the sync policy stored in api_config.
     *
     * @param  string  $entity  products | inventory | orders | fulfillment | customers
     * @return array<string, mixed>
     */
    public function getSyncPolicy(string $entity): array
    {
        $policy = $this->getApiConfigArray()['sync_policy'][$entity] ?? [];

        return is_array($policy) ? $policy : [];
    }

    /**
     * Whether this entity may be written back to the marketplace.
     *
     * Defaults to pull-only: a provider whose policy is missing or malformed
     * must never start pushing to a merchant's live catalogue by accident.
     */
    public function canPush(string $entity): bool
    {
        $direction = $this->getSyncPolicy($entity)['direction'] ?? 'pull';

        return in_array($direction, ['push', 'two-way'], true);
    }

    /**
     * Whether this entity may be read from the marketplace.
     */
    public function canPull(string $entity): bool
    {
        $direction = $this->getSyncPolicy($entity)['direction'] ?? 'pull';

        return in_array($direction, ['pull', 'two-way'], true);
    }

    /**
     * Decrypt a stored credential column, tolerating plaintext and bad payloads.
     */
    private function decryptColumn(string $column): ?string
    {
        $value = $this->attributes[$column] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return decrypt($value);
        } catch (DecryptException $e) {
            Log::error(__METHOD__.' - cannot decrypt '.$column, [
                'provider_id' => $this->id,
                'column' => $column,
            ]);

            return null;
        }
    }
}
