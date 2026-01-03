# Laravel Postman Generator

[![CI](https://github.com/Nutandc/Laravel-Postman-Generator/actions/workflows/ci.yml/badge.svg)](https://github.com/Nutandc/Laravel-Postman-Generator/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/nutandc/laravel-postman-generator)](https://packagist.org/packages/nutandc/laravel-postman-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/nutandc/laravel-postman-generator)](https://packagist.org/packages/nutandc/laravel-postman-generator)
[![License](https://img.shields.io/packagist/l/nutandc/laravel-postman-generator)](LICENSE)

Laravel Postman & OpenAPI Generator — auto-create Postman collections and OpenAPI 3.0 docs from Laravel routes with smart grouping, examples, and environment files.

## Features
- Auto-scan Laravel routes
- Postman v2.1 collection output
- OpenAPI 3.0 JSON output
- Route metadata via PHP attributes
- FormRequest rule parsing for body/query examples
- Group routes into folders (by URI, name, or tag)
- Auth support (bearer, api key, basic)
- Default headers and sample payloads
- Postman variables for base URL and tokens
- Postman environment output
- Response examples via attributes/overrides or auto from request rules
- Extensible metadata providers
- Configurable output paths

## Requirements
- PHP 8.2+
- Laravel 10/11/12

## Installation
```bash
composer require nutandc/laravel-postman-generator
```

Publish config:
```bash
php artisan vendor:publish --tag=postman-generator-config
```

Copy `.env.example` keys into your Laravel `.env` file and update as needed.

## Usage
Generate both outputs:
```bash
php artisan postman:generate
```

Generate only one format:
```bash
php artisan postman:generate --format=postman
php artisan postman:generate --format=openapi
```

## Attribute Example
```php
use Nutandc\PostmanGenerator\Attributes\EndpointDoc;

#[EndpointDoc(
    summary: 'List users',
    tags: ['Users'],
    auth: 'bearer',
    headers: [
        ['name' => 'X-Request-ID', 'value' => '{{request_id}}', 'required' => false],
    ],
    query: [
        ['name' => 'page', 'type' => 'integer', 'required' => false, 'example' => 2],
    ],
    responses: [
        ['status' => 200, 'description' => 'OK', 'body' => ['data' => ['id' => 1]]],
    ],
)]
public function index() {}
```

## Route Grouping
By default routes are grouped into folders by the first URI segment (e.g., `api/users` -> `users`).
You can change grouping strategy in config:
```php
'postman' => [
    'grouping' => [
        'strategy' => 'name', // uri | name | none
        'name_separator' => '.',
        'uri_depth' => 1,
        'strip_prefixes' => ['api'],
        'fallback' => 'General',
    ],
],
```

To remove debug routes (debugbar/clockwork/log-viewer), use scan filters:
```php
'scan' => [
    'include_prefixes' => ['api'],
    'exclude_prefixes' => ['_debugbar', '__clockwork', 'log-viewer'],
    'exclude_route_names' => ['debugbar.', 'clockwork.', 'log-viewer.'],
],
```

## Config Highlights
```php
return [
    'base_url' => env('POSTMAN_GENERATOR_BASE_URL', env('APP_URL', 'http://localhost')),
    'headers' => [
        'default' => [
            ['name' => env('POSTMAN_GENERATOR_HEADER_ACCEPT_NAME', 'Accept'), 'value' => env('POSTMAN_GENERATOR_HEADER_ACCEPT_VALUE', 'application/json')],
        ],
        'json' => [
            ['name' => env('POSTMAN_GENERATOR_HEADER_CONTENT_TYPE_NAME', 'Content-Type'), 'value' => env('POSTMAN_GENERATOR_HEADER_CONTENT_TYPE_VALUE', 'application/json'), 'required' => true],
        ],
    ],
    'output' => [
        'path' => storage_path('app/postman'),
    ],
    'scan' => [
        'form_request' => [
            'enabled' => env('POSTMAN_GENERATOR_FORM_REQUEST_ENABLED', true),
        ],
    ],
    'metadata_providers' => [
        \Nutandc\PostmanGenerator\Metadata\Providers\FormRequestMetadataProvider::class,
        \Nutandc\PostmanGenerator\Metadata\Providers\AttributeMetadataProvider::class,
        \Nutandc\PostmanGenerator\Metadata\Providers\OverridesMetadataProvider::class,
    ],
    'auth' => [
        'default' => 'bearer',
    ],
    'responses' => [
        'auto_from_request' => env('POSTMAN_GENERATOR_RESPONSE_AUTO_FROM_REQUEST', true),
        'default_status' => env('POSTMAN_GENERATOR_RESPONSE_DEFAULT_STATUS', 200),
        'default_description' => env('POSTMAN_GENERATOR_RESPONSE_DEFAULT_DESCRIPTION', 'OK'),
    ],
    'postman' => [
        'use_base_url_variable' => true,
        'variables' => [
            'base_url' => env('POSTMAN_GENERATOR_POSTMAN_BASE_URL'),
            'token' => env('POSTMAN_GENERATOR_POSTMAN_TOKEN'),
            'api_key' => env('POSTMAN_GENERATOR_POSTMAN_API_KEY'),
        ],
        'environments' => [
            'local' => [
                'base_url' => env('POSTMAN_GENERATOR_ENV_LOCAL_BASE_URL'),
                'token' => env('POSTMAN_GENERATOR_ENV_LOCAL_TOKEN'),
                'api_key' => env('POSTMAN_GENERATOR_ENV_LOCAL_API_KEY'),
            ],
        ],
    ],
];
```

## Environment Output
Environment files are generated alongside the collection:
```bash
storage/app/postman/environment.local.json
```

## Filters
```php
'scan' => [
    'include_tags' => ['Users'],
    'exclude_tags' => ['Internal'],
    'include_namespaces' => ['App\\Http\\Controllers\\Api'],
    'exclude_domains' => ['telescope'],
],
```

## Testing
```bash
composer test
composer analyse
composer fix:dry
```

## License
MIT
