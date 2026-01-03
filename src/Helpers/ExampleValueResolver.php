<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Helpers;

final class ExampleValueResolver
{
    public static function valueForType(string $type): mixed
    {
        return match (strtolower($type)) {
            'integer', 'int' => 1,
            'float', 'double' => 1.0,
            'boolean', 'bool' => true,
            'array' => [],
            default => 'string',
        };
    }

    public static function openApiType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'int' => 'integer',
            'float', 'double' => 'number',
            'boolean', 'bool' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }
}
