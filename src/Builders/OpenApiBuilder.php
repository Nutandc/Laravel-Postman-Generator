<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Builders;

use Nutandc\PostmanGenerator\ValueObjects\Endpoint;
use Nutandc\PostmanGenerator\ValueObjects\Parameter;

final class OpenApiBuilder
{
    /**
     * @param array<string, mixed> $config
     * @param Endpoint[] $endpoints
     * @return array<string, mixed>
     */
    public function build(array $config, array $endpoints): array
    {
        $paths = [];
        foreach ($endpoints as $endpoint) {
            foreach ($endpoint->methods as $method) {
                $paths['/' . ltrim($endpoint->uri, '/')][strtolower($method)] = $this->buildOperation($config, $endpoint, $method);
            }
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => (string) data_get($config, 'openapi.title', 'API Documentation'),
                'version' => (string) data_get($config, 'openapi.version', '1.0.0'),
                'description' => (string) data_get($config, 'openapi.description', ''),
            ],
            'servers' => [
                ['url' => (string) data_get($config, 'base_url', '')],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => $this->buildSecuritySchemes($config),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildOperation(array $config, Endpoint $endpoint, string $method): array
    {
        $parameters = array_merge(
            $this->buildParameterList($endpoint->pathParams, 'path'),
            $this->buildParameterList($endpoint->queryParams, 'query'),
        );

        $operation = [
            'summary' => $endpoint->summary ?? $endpoint->name,
            'description' => $endpoint->description ?? '',
            'tags' => $endpoint->tags,
            'deprecated' => $endpoint->deprecated,
            'parameters' => $parameters,
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                ],
            ],
        ];

        if ($endpoint->bodyParams !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => $this->buildSchemaProperties($endpoint->bodyParams),
                        ],
                        'example' => $this->buildBodyExample($endpoint->bodyParams),
                    ],
                ],
            ];
        }

        $auth = $endpoint->auth ?? data_get($config, 'auth.default', 'none');
        if ($auth !== 'none') {
            $operation['security'] = [[
                $this->securityKey($auth) => [],
            ]];
        }

        return $operation;
    }

    /**
     * @param Parameter[] $params
     * @return array<int, array<string, mixed>>
     */
    private function buildParameterList(array $params, string $in): array
    {
        $result = [];
        foreach ($params as $param) {
            $result[] = [
                'name' => $param->name,
                'in' => $in,
                'required' => $param->required,
                'description' => $param->description ?? '',
                'schema' => [
                    'type' => $this->mapType($param->type),
                ],
            ];
        }

        return $result;
    }

    /**
     * @param Parameter[] $params
     * @return array<string, array<string, mixed>>
     */
    private function buildSchemaProperties(array $params): array
    {
        $properties = [];
        foreach ($params as $param) {
            $properties[$param->name] = [
                'type' => $this->mapType($param->type),
                'description' => $param->description ?? '',
            ];
        }

        return $properties;
    }

    /**
     * @param Parameter[] $params
     * @return array<string, mixed>
     */
    private function buildBodyExample(array $params): array
    {
        $example = [];
        foreach ($params as $param) {
            $example[$param->name] = $this->exampleForType($param->type);
        }

        return $example;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildSecuritySchemes(array $config): array
    {
        return [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
            ],
            'basicAuth' => [
                'type' => 'http',
                'scheme' => 'basic',
            ],
            'apiKeyAuth' => [
                'type' => 'apiKey',
                'name' => (string) data_get($config, 'auth.api_key.key', 'X-API-KEY'),
                'in' => (string) data_get($config, 'auth.api_key.in', 'header'),
            ],
        ];
    }

    private function securityKey(string $auth): string
    {
        return match ($auth) {
            'basic' => 'basicAuth',
            'api_key' => 'apiKeyAuth',
            default => 'bearerAuth',
        };
    }

    private function mapType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'int' => 'integer',
            'float', 'double' => 'number',
            'boolean', 'bool' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    private function exampleForType(string $type): mixed
    {
        return match (strtolower($type)) {
            'integer', 'int' => 1,
            'float', 'double' => 1.0,
            'boolean', 'bool' => true,
            'array' => [],
            default => 'string',
        };
    }
}
