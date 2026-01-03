<?php

declare(strict_types=1);

return [
    'base_url' => env('POSTMAN_GENERATOR_BASE_URL', env('APP_URL', 'http://localhost')),

    'output' => [
        'path' => env('POSTMAN_GENERATOR_OUTPUT_PATH', storage_path('app/postman')),
        'postman' => [
            'enabled' => env('POSTMAN_GENERATOR_POSTMAN_ENABLED', true),
            'filename' => env('POSTMAN_GENERATOR_POSTMAN_FILENAME', 'collection.json'),
        ],
        'openapi' => [
            'enabled' => env('POSTMAN_GENERATOR_OPENAPI_ENABLED', true),
            'filename' => env('POSTMAN_GENERATOR_OPENAPI_FILENAME', 'openapi.json'),
        ],
    ],

    'scan' => [
        'only_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'include_prefixes' => [],
        'exclude_prefixes' => ['_ignition', 'telescope', 'horizon', '_debugbar', 'debugbar', '__clockwork', 'clockwork', 'log-viewer'],
        'exclude_route_names' => ['debugbar.', 'log-viewer.', 'clockwork.', 'telescope.', 'horizon.', 'ignition.'],
        'only_middleware' => [],
        'exclude_middleware' => ['web'],
    ],

    'auth' => [
        'default' => env('POSTMAN_GENERATOR_AUTH', 'bearer'),
        'bearer' => [
            'token' => env('POSTMAN_GENERATOR_BEARER_TOKEN', ''),
        ],
        'api_key' => [
            'key' => env('POSTMAN_GENERATOR_API_KEY_NAME', 'X-API-KEY'),
            'value' => env('POSTMAN_GENERATOR_API_KEY_VALUE', ''),
            'in' => env('POSTMAN_GENERATOR_API_KEY_IN', 'header'),
        ],
        'basic' => [
            'username' => env('POSTMAN_GENERATOR_BASIC_USER', ''),
            'password' => env('POSTMAN_GENERATOR_BASIC_PASS', ''),
        ],
    ],

    'overrides' => [
        // 'route.name' => [
        //     'summary' => 'List users',
        //     'description' => 'Returns paginated users.',
        //     'tags' => ['Users'],
        //     'auth' => 'bearer',
        //     'query' => [
        //         ['name' => 'page', 'type' => 'integer', 'required' => false],
        //     ],
        //     'body' => [
        //         ['name' => 'email', 'type' => 'string', 'required' => true],
        //     ],
        // ],
    ],

    'openapi' => [
        'title' => env('POSTMAN_GENERATOR_OPENAPI_TITLE', 'API Documentation'),
        'version' => env('POSTMAN_GENERATOR_OPENAPI_VERSION', '1.0.0'),
        'description' => env('POSTMAN_GENERATOR_OPENAPI_DESCRIPTION', ''),
    ],

    'postman' => [
        'name' => env('POSTMAN_GENERATOR_POSTMAN_NAME', 'API Collection'),
        'description' => env('POSTMAN_GENERATOR_POSTMAN_DESCRIPTION', ''),
        'grouping' => [
            'enabled' => filter_var(env('POSTMAN_GENERATOR_GROUPING_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'strategy' => env('POSTMAN_GENERATOR_GROUPING_STRATEGY', 'uri'), // uri | name | none
            'name_separator' => env('POSTMAN_GENERATOR_GROUPING_NAME_SEPARATOR', '.'),
            'uri_depth' => (int) env('POSTMAN_GENERATOR_GROUPING_URI_DEPTH', 1),
            'strip_prefixes' => ['api'],
            'fallback' => env('POSTMAN_GENERATOR_GROUPING_FALLBACK', 'General'),
        ],
    ],
];
