<?php

return [
    'scopes'    =>  [
        'global' => [
            '\NextDeveloper\IAM\Database\Scopes\AuthorizationScope',
            '\NextDeveloper\Commons\Database\GlobalScopes\LimitScope',
        ]
    ],
    // Exact redirect URI registered in the Ideasoft app. Defaults to the production API callback;
    // override with IDEASOFT_REDIRECT_URI (e.g. http://localhost:8000/... for local testing).
    'ideasoft_redirect_uri' => env('IDEASOFT_REDIRECT_URI', 'https://apiv4.plusclouds.com/public/marketplace/ideasoft/callback'),
    'schedule' => [
        'enabled' => env('MARKETPLACE_SCHEDULE_ENABLED', false),
        'cron' => env('MARKETPLACE_SCHEDULE_CRON', '*/30 * * * * *'),
    ],
    // Application-level eBay keyset. Used for the Browse API, which needs no
    // seller login. Per-seller connections live on marketplace_providers
    // (adapter = 'ebay'): the seller's tokens are stored on the provider row,
    // while this keyset stays platform-wide.
    'ebay' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'environment' => env('EBAY_ENVIRONMENT', 'production'),
        'marketplace_id' => env('EBAY_MARKETPLACE_ID', 'EBAY_US'),
        'timeout' => env('EBAY_TIMEOUT', 30),
        // RuName: eBay's stand-in for redirect_uri in the seller consent flow. Created in the
        // eBay developer portal (User Tokens); its accept URL must point at
        // /public/marketplace/ebay/callback. The sandbox keyset has its own RuName.
        'ru_name' => env('EBAY_RU_NAME'),
        'ru_name_sandbox' => env('EBAY_RU_NAME_SANDBOX'),
        // Space-separated OAuth scopes requested from the seller.
        'oauth_scopes' => env('EBAY_OAUTH_SCOPES', implode(' ', [
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly',
        ])),
        // Retry policy for 429 / 5xx answers from eBay (exponential backoff from the base delay).
        'retry_max' => env('EBAY_RETRY_MAX', 3),
        'retry_base_ms' => env('EBAY_RETRY_BASE_MS', 500),
        // Page size for the (later) inventory scan; eBay allows up to 200 on getInventoryItems.
        'scan_page_size' => env('EBAY_SCAN_PAGE_SIZE', 100),
    ],
];
