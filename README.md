# Laravel Postman Generator

[![CI](https://github.com/Nutandc/Laravel-Postman-Generator/actions/workflows/ci.yml/badge.svg)](https://github.com/Nutandc/Laravel-Postman-Generator/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/nutandc/laravel-postman-generator)](https://packagist.org/packages/nutandc/laravel-postman-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/nutandc/laravel-postman-generator)](https://packagist.org/packages/nutandc/laravel-postman-generator)
[![License](https://img.shields.io/packagist/l/nutandc/laravel-postman-generator)](LICENSE)

Laravel package to generate Postman v2.1 collections and OpenAPI 3.0 specs from your routes.

## Features
- Auto-scan Laravel routes
- Postman v2.1 collection output
- OpenAPI 3.0 JSON output
- Route metadata via PHP attributes
- Group routes into folders (by URI, name, or tag)
- Auth support (bearer, api key, basic)
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
    query: [
        ['name' => 'page', 'type' => 'integer', 'required' => false],
    ]
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
    'output' => [
        'path' => storage_path('app/postman'),
    ],
    'auth' => [
        'default' => 'bearer',
    ],
];
```

## Testing
```bash
composer test
composer analyse
composer fix:dry
```

## License
MIT
