<?php

namespace NextDeveloper\Marketplace\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Marketplace\Database\Observers\ProductCatalogObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Marketplace\Database\Observers\ProductCatalogsObserver;

/**
* Class ProductCatalogs.
*
* @package NextDeveloper\Marketplace\Database\Models
*/
class ProductCatalogs extends Model
{
use Filterable, UuidId;
	use SoftDeletes;


	public $timestamps = true;

protected $table = 'marketplace_product_catalog';


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
'id'                     => 'integer',
		'uuid'                   => 'string',
		'name'                   => 'string',
		'agreement'              => 'string',
		'price'                  => 'double',
		'currency_code'          => 'string',
		'marketplace_product_id' => 'integer',
		'created_at'             => 'datetime',
		'updated_at'             => 'datetime',
		'deleted_at'             => 'datetime',
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
parent::observe(ProductCatalogsObserver::class);

self::registerScopes();
}

public static function registerScopes()
{
$globalScopes = config('marketplace.scopes.global');
$modelScopes = config('marketplace.scopes.marketplace_product_catalog');

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