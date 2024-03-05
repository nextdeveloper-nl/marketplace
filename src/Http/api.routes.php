<?php

Route::prefix('marketplace')->group(
    function () {
        Route::prefix('subscriptions')->group(
            function () {
                Route::get('/', 'Subscriptions\SubscriptionsController@index');

                Route::get('{marketplace_subscriptions}/tags ', 'Subscriptions\SubscriptionsController@tags');
                Route::post('{marketplace_subscriptions}/tags ', 'Subscriptions\SubscriptionsController@saveTags');
                Route::get('{marketplace_subscriptions}/addresses ', 'Subscriptions\SubscriptionsController@addresses');
                Route::post('{marketplace_subscriptions}/addresses ', 'Subscriptions\SubscriptionsController@saveAddresses');

                Route::get('/{marketplace_subscriptions}/{subObjects}', 'Subscriptions\SubscriptionsController@relatedObjects');
                Route::get('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@show');

                Route::post('/', 'Subscriptions\SubscriptionsController@store');
                Route::patch('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@update');
                Route::delete('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@destroy');
            }
        );

        Route::prefix('products')->group(
            function () {
                Route::get('/', 'Products\ProductsController@index');

                Route::get('{marketplace_products}/tags ', 'Products\ProductsController@tags');
                Route::post('{marketplace_products}/tags ', 'Products\ProductsController@saveTags');
                Route::get('{marketplace_products}/addresses ', 'Products\ProductsController@addresses');
                Route::post('{marketplace_products}/addresses ', 'Products\ProductsController@saveAddresses');

                Route::get('/{marketplace_products}/{subObjects}', 'Products\ProductsController@relatedObjects');
                Route::get('/{marketplace_products}', 'Products\ProductsController@show');

                Route::post('/', 'Products\ProductsController@store');
                Route::patch('/{marketplace_products}', 'Products\ProductsController@update');
                Route::delete('/{marketplace_products}', 'Products\ProductsController@destroy');
            }
        );

        Route::prefix('product-catalogs')->group(
            function () {
                Route::get('/', 'ProductCatalogs\ProductCatalogsController@index');

                Route::get('{marketplace_product_catalogs}/tags ', 'ProductCatalogs\ProductCatalogsController@tags');
                Route::post('{marketplace_product_catalogs}/tags ', 'ProductCatalogs\ProductCatalogsController@saveTags');
                Route::get('{marketplace_product_catalogs}/addresses ', 'ProductCatalogs\ProductCatalogsController@addresses');
                Route::post('{marketplace_product_catalogs}/addresses ', 'ProductCatalogs\ProductCatalogsController@saveAddresses');

                Route::get('/{marketplace_product_catalogs}/{subObjects}', 'ProductCatalogs\ProductCatalogsController@relatedObjects');
                Route::get('/{marketplace_product_catalogs}', 'ProductCatalogs\ProductCatalogsController@show');

                Route::post('/', 'ProductCatalogs\ProductCatalogsController@store');
                Route::patch('/{marketplace_product_catalogs}', 'ProductCatalogs\ProductCatalogsController@update');
                Route::delete('/{marketplace_product_catalogs}', 'ProductCatalogs\ProductCatalogsController@destroy');
            }
        );

        Route::prefix('markets')->group(
            function () {
                Route::get('/', 'Markets\MarketsController@index');

                Route::get('{marketplace_markets}/tags ', 'Markets\MarketsController@tags');
                Route::post('{marketplace_markets}/tags ', 'Markets\MarketsController@saveTags');
                Route::get('{marketplace_markets}/addresses ', 'Markets\MarketsController@addresses');
                Route::post('{marketplace_markets}/addresses ', 'Markets\MarketsController@saveAddresses');

                Route::get('/{marketplace_markets}/{subObjects}', 'Markets\MarketsController@relatedObjects');
                Route::get('/{marketplace_markets}', 'Markets\MarketsController@show');

                Route::post('/', 'Markets\MarketsController@store');
                Route::patch('/{marketplace_markets}', 'Markets\MarketsController@update');
                Route::delete('/{marketplace_markets}', 'Markets\MarketsController@destroy');
            }
        );

        // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE



















    }
);


























