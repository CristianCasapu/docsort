<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'personal#config', 'url' => '/api/config', 'verb' => 'GET'],
        ['name' => 'personal#setConfig', 'url' => '/api/config', 'verb' => 'POST'],
        ['name' => 'personal#scan', 'url' => '/api/scan', 'verb' => 'POST'],
        ['name' => 'personal#history', 'url' => '/api/history', 'verb' => 'GET'],
        ['name' => 'admin#rules', 'url' => '/api/admin/rules', 'verb' => 'GET'],
        ['name' => 'admin#setRules', 'url' => '/api/admin/rules', 'verb' => 'POST'],
        ['name' => 'admin#test', 'url' => '/api/admin/test', 'verb' => 'POST'],
    ],
];
