<?php

return [
    'scopes'    =>  [
        'global' => [
            '\NextDeveloper\IAM\Database\Scopes\AuthorizationScope',
            '\NextDeveloper\Commons\Database\GlobalScopes\LimitScope',
        ]
    ],
    'schedule' => [
        'enabled' => env('MARKETPLACE_SCHEDULE_ENABLED', false),
        'cron' => env('MARKETPLACE_SCHEDULE_CRON', '*/30 * * * * *'),
    ],
    // Application-level eBay keyset. Used for the Browse API, which needs no
    // seller login; per-seller connections will live on marketplace_providers.
    'ebay' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'environment' => env('EBAY_ENVIRONMENT', 'production'),
        'marketplace_id' => env('EBAY_MARKETPLACE_ID', 'EBAY_US'),
        'timeout' => env('EBAY_TIMEOUT', 30),
    ],
];
