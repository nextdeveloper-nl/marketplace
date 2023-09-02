<?php

Route::prefix('marketplace')->group(function() {
Route::prefix('product-catalog')->group(function () {
        Route::get('/', 'MarketplaceProductCatalog\MarketplaceProductCatalogController@index');
        Route::get('/{marketplace_product_catalog}', 'MarketplaceProductCatalog\MarketplaceProductCatalogController@show');
        Route::post('/', 'MarketplaceProductCatalog\MarketplaceProductCatalogController@store');
        Route::patch('/{marketplace_product_catalog}', 'MarketplaceProductCatalog\MarketplaceProductCatalogController@update');
        Route::delete('/{marketplace_product_catalog}', 'MarketplaceProductCatalog\MarketplaceProductCatalogController@destroy');
    });

Route::prefix('products')->group(function () {
        Route::get('/', 'MarketplaceProduct\MarketplaceProductController@index');
        Route::get('/{marketplace_products}', 'MarketplaceProduct\MarketplaceProductController@show');
        Route::post('/', 'MarketplaceProduct\MarketplaceProductController@store');
        Route::patch('/{marketplace_products}', 'MarketplaceProduct\MarketplaceProductController@update');
        Route::delete('/{marketplace_products}', 'MarketplaceProduct\MarketplaceProductController@destroy');
    });

Route::prefix('subscriptions')->group(function () {
        Route::get('/', 'MarketplaceSubscription\MarketplaceSubscriptionController@index');
        Route::get('/{marketplace_subscriptions}', 'MarketplaceSubscription\MarketplaceSubscriptionController@show');
        Route::post('/', 'MarketplaceSubscription\MarketplaceSubscriptionController@store');
        Route::patch('/{marketplace_subscriptions}', 'MarketplaceSubscription\MarketplaceSubscriptionController@update');
        Route::delete('/{marketplace_subscriptions}', 'MarketplaceSubscription\MarketplaceSubscriptionController@destroy');
    });

// EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n
});