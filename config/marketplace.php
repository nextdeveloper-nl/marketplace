<?php

return [
    'scopes'    =>  [
        'global' => [
            '\NextDeveloper\IAM\Database\Scopes\AuthorizationScope',
            '\NextDeveloper\Commons\Database\GlobalScopes\LimitScope',
        ]
    ],
    'schedule' => [
        'enabled' => env('MARKETPLACE_SCHEDULE_ENABLED', true),
        'cron' => env('MARKETPLACE_SCHEDULE_CRON', '*/30 * * * * *'),
    ]
];
