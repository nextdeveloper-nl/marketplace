<?php

Route::prefix('marketplace')->group(
    function () {
        Route::prefix('subscriptions')->group(
            function () {
                Route::get('/', 'Subscriptions\SubscriptionsController@index');
                Route::get('/actions', 'Subscriptions\SubscriptionsController@getActions');

                Route::get('{marketplace_subscriptions}/tags ', 'Subscriptions\SubscriptionsController@tags');
                Route::post('{marketplace_subscriptions}/tags ', 'Subscriptions\SubscriptionsController@saveTags');
                Route::get('{marketplace_subscriptions}/addresses ', 'Subscriptions\SubscriptionsController@addresses');
                Route::post('{marketplace_subscriptions}/addresses ', 'Subscriptions\SubscriptionsController@saveAddresses');

                Route::get('/{marketplace_subscriptions}/{subObjects}', 'Subscriptions\SubscriptionsController@relatedObjects');
                Route::get('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@show');

                Route::post('/', 'Subscriptions\SubscriptionsController@store');
                Route::post('/{marketplace_subscriptions}/do/{action}', 'Subscriptions\SubscriptionsController@doAction');

                Route::patch('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@update');
                Route::delete('/{marketplace_subscriptions}', 'Subscriptions\SubscriptionsController@destroy');
            }
        );

        Route::prefix('product-catalogs')->group(
            function () {
                Route::get('/', 'ProductCatalogs\ProductCatalogsController@index');
                Route::get('/actions', 'ProductCatalogs\ProductCatalogsController@getActions');

                Route::get('{marketplace_product_catalogs}/tags ', 'ProductCatalogs\ProductCatalogsController@tags');
                Route::post('{marketplace_product_catalogs}/tags ', 'ProductCatalogs\ProductCatalogsController@saveTags');
                Route::get('{marketplace_product_catalogs}/addresses ', 'ProductCatalogs\ProductCatalogsController@addresses');
                Route::post('{marketplace_product_catalogs}/addresses ', 'ProductCatalogs\ProductCatalogsController@saveAddresses');

                Route::get('/{marketplace_product_catalogs}/{subObjects}', 'ProductCatalogs\ProductCatalogsController@relatedObjects');
                Route::get('/{marketplace_product_catalogs}', 'ProductCatalogs\ProductCatalogsController@show');

                Route::post('/', 'ProductCatalogs\ProductCatalogsController@store');
                Route::post('/{marketplace_product_catalogs}/do/{action}', 'ProductCatalogs\ProductCatalogsController@doAction');

                Route::patch('/{marketplace_product_catalogs}', 'ProductCatalogs\ProductCatalogsController@update');
                Route::delete('/{marketplace_product_catalogs}', 'ProductCatalogs\ProductCatalogsController@destroy');
            }
        );

        Route::prefix('products')->group(
            function () {
                Route::get('/', 'Products\ProductsController@index');
                Route::get('/actions', 'Products\ProductsController@getActions');

                Route::get('{marketplace_products}/tags ', 'Products\ProductsController@tags');
                Route::post('{marketplace_products}/tags ', 'Products\ProductsController@saveTags');
                Route::get('{marketplace_products}/addresses ', 'Products\ProductsController@addresses');
                Route::post('{marketplace_products}/addresses ', 'Products\ProductsController@saveAddresses');

                Route::get('/{marketplace_products}/{subObjects}', 'Products\ProductsController@relatedObjects');
                Route::get('/{marketplace_products}', 'Products\ProductsController@show');

                Route::post('/', 'Products\ProductsController@store');
                Route::post('/{marketplace_products}/do/{action}', 'Products\ProductsController@doAction');

                Route::patch('/{marketplace_products}', 'Products\ProductsController@update');
                Route::delete('/{marketplace_products}', 'Products\ProductsController@destroy');
            }
        );

        Route::prefix('markets')->group(
            function () {
                Route::get('/', 'Markets\MarketsController@index');
                Route::get('/actions', 'Markets\MarketsController@getActions');

                Route::get('{marketplace_markets}/tags ', 'Markets\MarketsController@tags');
                Route::post('{marketplace_markets}/tags ', 'Markets\MarketsController@saveTags');
                Route::get('{marketplace_markets}/addresses ', 'Markets\MarketsController@addresses');
                Route::post('{marketplace_markets}/addresses ', 'Markets\MarketsController@saveAddresses');

                Route::get('/{marketplace_markets}/{subObjects}', 'Markets\MarketsController@relatedObjects');
                Route::get('/{marketplace_markets}', 'Markets\MarketsController@show');

                Route::post('/', 'Markets\MarketsController@store');
                Route::post('/{marketplace_markets}/do/{action}', 'Markets\MarketsController@doAction');

                Route::patch('/{marketplace_markets}', 'Markets\MarketsController@update');
                Route::delete('/{marketplace_markets}', 'Markets\MarketsController@destroy');
            }
        );

        Route::prefix('product-catalogs-perspective')->group(
            function () {
                Route::get('/', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@index');
                Route::get('/actions', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@getActions');

                Route::get('{mpcp}/tags ', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@tags');
                Route::post('{mpcp}/tags ', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@saveTags');
                Route::get('{mpcp}/addresses ', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@addresses');
                Route::post('{mpcp}/addresses ', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@saveAddresses');

                Route::get('/{mpcp}/{subObjects}', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@relatedObjects');
                Route::get('/{mpcp}', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@show');

                Route::post('/', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@store');
                Route::post('/{mpcp}/do/{action}', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@doAction');

                Route::patch('/{mpcp}', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@update');
                Route::delete('/{mpcp}', 'ProductCatalogsPerspective\ProductCatalogsPerspectiveController@destroy');
            }
        );

        Route::prefix('markets-perspective')->group(
            function () {
                Route::get('/', 'MarketsPerspective\MarketsPerspectiveController@index');
                Route::get('/actions', 'MarketsPerspective\MarketsPerspectiveController@getActions');

                Route::get('{marketplace_markets_perspective}/tags ', 'MarketsPerspective\MarketsPerspectiveController@tags');
                Route::post('{marketplace_markets_perspective}/tags ', 'MarketsPerspective\MarketsPerspectiveController@saveTags');
                Route::get('{marketplace_markets_perspective}/addresses ', 'MarketsPerspective\MarketsPerspectiveController@addresses');
                Route::post('{marketplace_markets_perspective}/addresses ', 'MarketsPerspective\MarketsPerspectiveController@saveAddresses');

                Route::get('/{marketplace_markets_perspective}/{subObjects}', 'MarketsPerspective\MarketsPerspectiveController@relatedObjects');
                Route::get('/{marketplace_markets_perspective}', 'MarketsPerspective\MarketsPerspectiveController@show');

                Route::post('/', 'MarketsPerspective\MarketsPerspectiveController@store');
                Route::post('/{marketplace_markets_perspective}/do/{action}', 'MarketsPerspective\MarketsPerspectiveController@doAction');

                Route::patch('/{marketplace_markets_perspective}', 'MarketsPerspective\MarketsPerspectiveController@update');
                Route::delete('/{marketplace_markets_perspective}', 'MarketsPerspective\MarketsPerspectiveController@destroy');
            }
        );

        Route::prefix('products-perspective')->group(
            function () {
                Route::get('/', 'ProductsPerspective\ProductsPerspectiveController@index');
                Route::get('/actions', 'ProductsPerspective\ProductsPerspectiveController@getActions');

                Route::get('{marketplace_products_perspective}/tags ', 'ProductsPerspective\ProductsPerspectiveController@tags');
                Route::post('{marketplace_products_perspective}/tags ', 'ProductsPerspective\ProductsPerspectiveController@saveTags');
                Route::get('{marketplace_products_perspective}/addresses ', 'ProductsPerspective\ProductsPerspectiveController@addresses');
                Route::post('{marketplace_products_perspective}/addresses ', 'ProductsPerspective\ProductsPerspectiveController@saveAddresses');

                Route::get('/{marketplace_products_perspective}/{subObjects}', 'ProductsPerspective\ProductsPerspectiveController@relatedObjects');
                Route::get('/{marketplace_products_perspective}', 'ProductsPerspective\ProductsPerspectiveController@show');

                Route::post('/', 'ProductsPerspective\ProductsPerspectiveController@store');
                Route::post('/{marketplace_products_perspective}/do/{action}', 'ProductsPerspective\ProductsPerspectiveController@doAction');

                Route::patch('/{marketplace_products_perspective}', 'ProductsPerspective\ProductsPerspectiveController@update');
                Route::delete('/{marketplace_products_perspective}', 'ProductsPerspective\ProductsPerspectiveController@destroy');
            }
        );

        // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE












































































































































    }
);






















































