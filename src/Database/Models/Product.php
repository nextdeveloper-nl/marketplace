<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Marketplace\Database\Observers\ProductObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Marketplace\Database\Observers\ProductsObserver;

/**
* Class Products.
*
* @package NextDeveloper\Marketplace\Database\Models
*/
class Products extends Model
{
use Filterable, UuidId;
	use SoftDeletes;


	public $timestamps = true;

protected $table = 'marketplace_products';


/**
* @var array
*/
protected $guarded = [];

/**
*  Here we have the fulltext fields. We can use these for fulltext search if enabled.
*/
protected $fullTextFields = [

];

/**
* @var array
*/
protected $appends = [

];

/**
* We are casting fields to objects so that we can work on them better
* @var array
*/
protected $casts = [
'id'                       => 'integer',
		'uuid'                     => 'string',
		'name'                     => 'string',
		'description'              => 'string',
		'content'                  => 'string',
		'highlights'               => 'string',
		'after_sales_introduction' => 'string',
		'support_content'          => 'string',
		'refund_policy'            => 'string',
		'eula'                     => 'string',
		'slug'                     => 'string',
		'version'                  => 'string',
		'management_class'         => 'string',
		'discount_rate'            => 'boolean',
		'is_maintenance'           => 'boolean',
		'is_public'                => 'boolean',
		'is_invisible'             => 'boolean',
		'is_active'                => 'boolean',
		'common_category_id'       => 'integer',
		'common_country_id'        => 'integer',
		'common_language_id'       => 'integer',
		'iam_account_id'           => 'integer',
		'iam_user_id'              => 'integer',
		'created_at'               => 'datetime',
		'updated_at'               => 'datetime',
		'deleted_at'               => 'datetime',
];

/**
* We are casting data fields.
* @var array
*/
protected $dates = [
'created_at',
		'updated_at',
		'deleted_at',
];

/**
* @var array
*/
protected $with = [

];

/**
* @var int
*/
protected $perPage = 20;

/**
* @return void
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

if(!$modelScopes) $modelScopes = [];
if (!$globalScopes) $globalScopes = [];

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

// EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n
}