<?php

Route::prefix('marketplace')->group(
    function () {
        Route::prefix('accounts')->group(
            function () {
                Route::get('/', 'Accounts\AccountsController@index');
                Route::get('/actions', 'Accounts\AccountsController@getActions');

                Route::get('{marketplace_accounts}/tags ', 'Accounts\AccountsController@tags');
                Route::post('{marketplace_accounts}/tags ', 'Accounts\AccountsController@saveTags');
                Route::get('{marketplace_accounts}/addresses ', 'Accounts\AccountsController@addresses');
                Route::post('{marketplace_accounts}/addresses ', 'Accounts\AccountsController@saveAddresses');

                Route::get('/{marketplace_accounts}/{subObjects}', 'Accounts\AccountsController@relatedObjects');
                Route::get('/{marketplace_accounts}', 'Accounts\AccountsController@show');

                Route::post('/', 'Accounts\AccountsController@store');
                Route::post('/{marketplace_accounts}/do/{action}', 'Accounts\AccountsController@doAction');

                Route::patch('/{marketplace_accounts}', 'Accounts\AccountsController@update');
                Route::delete('/{marketplace_accounts}', 'Accounts\AccountsController@destroy');
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

        Route::prefix('providers')->group(
            function () {
                Route::get('/', 'Providers\ProvidersController@index');
                Route::get('/actions', 'Providers\ProvidersController@getActions');

                Route::get('{marketplace_providers}/tags ', 'Providers\ProvidersController@tags');
                Route::post('{marketplace_providers}/tags ', 'Providers\ProvidersController@saveTags');
                Route::get('{marketplace_providers}/addresses ', 'Providers\ProvidersController@addresses');
                Route::post('{marketplace_providers}/addresses ', 'Providers\ProvidersController@saveAddresses');

                Route::get('/{marketplace_providers}/{subObjects}', 'Providers\ProvidersController@relatedObjects');
                Route::get('/{marketplace_providers}', 'Providers\ProvidersController@show');

                Route::post('/', 'Providers\ProvidersController@store');
                Route::post('/{marketplace_providers}/do/{action}', 'Providers\ProvidersController@doAction');

                Route::patch('/{marketplace_providers}', 'Providers\ProvidersController@update');
                Route::delete('/{marketplace_providers}', 'Providers\ProvidersController@destroy');
            }
        );

        Route::prefix('orders')->group(
            function () {
                Route::get('/', 'Orders\OrdersController@index');
                Route::get('/actions', 'Orders\OrdersController@getActions');

                Route::get('{marketplace_orders}/tags ', 'Orders\OrdersController@tags');
                Route::post('{marketplace_orders}/tags ', 'Orders\OrdersController@saveTags');
                Route::get('{marketplace_orders}/addresses ', 'Orders\OrdersController@addresses');
                Route::post('{marketplace_orders}/addresses ', 'Orders\OrdersController@saveAddresses');

                Route::get('/{marketplace_orders}/{subObjects}', 'Orders\OrdersController@relatedObjects');
                Route::get('/{marketplace_orders}', 'Orders\OrdersController@show');

                Route::post('/', 'Orders\OrdersController@store');
                Route::post('/{marketplace_orders}/do/{action}', 'Orders\OrdersController@doAction');

                Route::patch('/{marketplace_orders}', 'Orders\OrdersController@update');
                Route::delete('/{marketplace_orders}', 'Orders\OrdersController@destroy');
            }
        );

        Route::prefix('order-items')->group(
            function () {
                Route::get('/', 'OrderItems\OrderItemsController@index');
                Route::get('/actions', 'OrderItems\OrderItemsController@getActions');

                Route::get('{marketplace_order_items}/tags ', 'OrderItems\OrderItemsController@tags');
                Route::post('{marketplace_order_items}/tags ', 'OrderItems\OrderItemsController@saveTags');
                Route::get('{marketplace_order_items}/addresses ', 'OrderItems\OrderItemsController@addresses');
                Route::post('{marketplace_order_items}/addresses ', 'OrderItems\OrderItemsController@saveAddresses');

                Route::get('/{marketplace_order_items}/{subObjects}', 'OrderItems\OrderItemsController@relatedObjects');
                Route::get('/{marketplace_order_items}', 'OrderItems\OrderItemsController@show');

                Route::post('/', 'OrderItems\OrderItemsController@store');
                Route::post('/{marketplace_order_items}/do/{action}', 'OrderItems\OrderItemsController@doAction');

                Route::patch('/{marketplace_order_items}', 'OrderItems\OrderItemsController@update');
                Route::delete('/{marketplace_order_items}', 'OrderItems\OrderItemsController@destroy');
            }
        );

        Route::prefix('product-mappings')->group(
            function () {
                Route::get('/', 'ProductMappings\ProductMappingsController@index');
                Route::get('/actions', 'ProductMappings\ProductMappingsController@getActions');

                Route::get('{marketplace_product_mappings}/tags ', 'ProductMappings\ProductMappingsController@tags');
                Route::post('{marketplace_product_mappings}/tags ', 'ProductMappings\ProductMappingsController@saveTags');
                Route::get('{marketplace_product_mappings}/addresses ', 'ProductMappings\ProductMappingsController@addresses');
                Route::post('{marketplace_product_mappings}/addresses ', 'ProductMappings\ProductMappingsController@saveAddresses');

                Route::get('/{marketplace_product_mappings}/{subObjects}', 'ProductMappings\ProductMappingsController@relatedObjects');
                Route::get('/{marketplace_product_mappings}', 'ProductMappings\ProductMappingsController@show');

                Route::post('/', 'ProductMappings\ProductMappingsController@store');
                Route::post('/{marketplace_product_mappings}/do/{action}', 'ProductMappings\ProductMappingsController@doAction');

                Route::patch('/{marketplace_product_mappings}', 'ProductMappings\ProductMappingsController@update');
                Route::delete('/{marketplace_product_mappings}', 'ProductMappings\ProductMappingsController@destroy');
            }
        );

        Route::prefix('product-catalog-mappings')->group(
            function () {
                Route::get('/', 'ProductCatalogMappings\ProductCatalogMappingsController@index');
                Route::get('/actions', 'ProductCatalogMappings\ProductCatalogMappingsController@getActions');

                Route::get('{mpcm}/tags ', 'ProductCatalogMappings\ProductCatalogMappingsController@tags');
                Route::post('{mpcm}/tags ', 'ProductCatalogMappings\ProductCatalogMappingsController@saveTags');
                Route::get('{mpcm}/addresses ', 'ProductCatalogMappings\ProductCatalogMappingsController@addresses');
                Route::post('{mpcm}/addresses ', 'ProductCatalogMappings\ProductCatalogMappingsController@saveAddresses');

                Route::get('/{mpcm}/{subObjects}', 'ProductCatalogMappings\ProductCatalogMappingsController@relatedObjects');
                Route::get('/{mpcm}', 'ProductCatalogMappings\ProductCatalogMappingsController@show');

                Route::post('/', 'ProductCatalogMappings\ProductCatalogMappingsController@store');
                Route::post('/{mpcm}/do/{action}', 'ProductCatalogMappings\ProductCatalogMappingsController@doAction');

                Route::patch('/{mpcm}', 'ProductCatalogMappings\ProductCatalogMappingsController@update');
                Route::delete('/{mpcm}', 'ProductCatalogMappings\ProductCatalogMappingsController@destroy');
            }
        );

        Route::prefix('status-mappings')->group(
            function () {
                Route::get('/', 'StatusMappings\StatusMappingsController@index');
                Route::get('/actions', 'StatusMappings\StatusMappingsController@getActions');

                Route::get('{marketplace_status_mappings}/tags ', 'StatusMappings\StatusMappingsController@tags');
                Route::post('{marketplace_status_mappings}/tags ', 'StatusMappings\StatusMappingsController@saveTags');
                Route::get('{marketplace_status_mappings}/addresses ', 'StatusMappings\StatusMappingsController@addresses');
                Route::post('{marketplace_status_mappings}/addresses ', 'StatusMappings\StatusMappingsController@saveAddresses');

                Route::get('/{marketplace_status_mappings}/{subObjects}', 'StatusMappings\StatusMappingsController@relatedObjects');
                Route::get('/{marketplace_status_mappings}', 'StatusMappings\StatusMappingsController@show');

                Route::post('/', 'StatusMappings\StatusMappingsController@store');
                Route::post('/{marketplace_status_mappings}/do/{action}', 'StatusMappings\StatusMappingsController@doAction');

                Route::patch('/{marketplace_status_mappings}', 'StatusMappings\StatusMappingsController@update');
                Route::delete('/{marketplace_status_mappings}', 'StatusMappings\StatusMappingsController@destroy');
            }
        );

        Route::prefix('order-status-history')->group(
            function () {
                Route::get('/', 'OrderStatusHistory\OrderStatusHistoryController@index');
                Route::get('/actions', 'OrderStatusHistory\OrderStatusHistoryController@getActions');

                Route::get('{marketplace_order_status_history}/tags ', 'OrderStatusHistory\OrderStatusHistoryController@tags');
                Route::post('{marketplace_order_status_history}/tags ', 'OrderStatusHistory\OrderStatusHistoryController@saveTags');
                Route::get('{marketplace_order_status_history}/addresses ', 'OrderStatusHistory\OrderStatusHistoryController@addresses');
                Route::post('{marketplace_order_status_history}/addresses ', 'OrderStatusHistory\OrderStatusHistoryController@saveAddresses');

                Route::get('/{marketplace_order_status_history}/{subObjects}', 'OrderStatusHistory\OrderStatusHistoryController@relatedObjects');
                Route::get('/{marketplace_order_status_history}', 'OrderStatusHistory\OrderStatusHistoryController@show');

                Route::post('/', 'OrderStatusHistory\OrderStatusHistoryController@store');
                Route::post('/{marketplace_order_status_history}/do/{action}', 'OrderStatusHistory\OrderStatusHistoryController@doAction');

                Route::patch('/{marketplace_order_status_history}', 'OrderStatusHistory\OrderStatusHistoryController@update');
                Route::delete('/{marketplace_order_status_history}', 'OrderStatusHistory\OrderStatusHistoryController@destroy');
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






























































