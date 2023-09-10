<?php

Route::prefix('marketplace')->group(function() {
Route::prefix('product-catalog')->group(function () {
        Route::get('/', 'ProductCatalog\ProductCatalogController@index');
        Route::get('/{marketplace_product_catalog}', 'ProductCatalog\ProductCatalogController@show');
        Route::post('/', 'ProductCatalog\ProductCatalogController@store');
        Route::patch('/{marketplace_product_catalog}', 'ProductCatalog\ProductCatalogController@update');
        Route::delete('/{marketplace_product_catalog}', 'ProductCatalog\ProductCatalogController@destroy');
    });

Route::prefix('products')->group(function () {
        Route::get('/', 'Products\ProductsController@index');
        Route::get('/{marketplace_products}', 'Products\ProductsController@show');
        Route::post('/', 'Products\ProductsController@store');
        Route::patch('/{marketplace_products}', 'Products\ProductsController@update');
        Route::delete('/{marketplace_products}', 'Products\ProductsController@destroy');
    });

Route::prefix('subscriptions')->group(function () {
        Route::get('/', 'Subscriptions\SubscriptionsController@index');
        Route::get('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@show');
        Route::post('/', 'Subscriptions\SubscriptionsController@store');
        Route::patch('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@update');
        Route::delete('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@destroy');
    });

// EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n
});