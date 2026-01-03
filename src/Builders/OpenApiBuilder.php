<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Builders;

use Nutandc\PostmanGenerator\Helpers\ExampleValueResolver;
use Nutandc\PostmanGenerator\ValueObjects\Endpoint;
use Nutandc\PostmanGenerator\ValueObjects\Header;
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
            $this->buildHeaderParameters($endpoint->headers),
            $this->buildParameterList($endpoint->pathParams, 'path'),
            $this->buildParameterList($endpoint->queryParams, 'query'),
        );

        $operation = [
            'summary' => $endpoint->summary ?? $endpoint->name,
            'description' => $endpoint->description ?? '',
            'tags' => $endpoint->tags,
            'deprecated' => $endpoint->deprecated,
            'parameters' => $parameters,
            'responses' => $this->buildResponses($config, $endpoint),
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
            $entry = [
                'name' => $param->name,
                'in' => $in,
                'required' => $param->required,
                'description' => $param->description ?? '',
                'schema' => [
                    'type' => ExampleValueResolver::openApiType($param->type),
                ],
            ];

            if ($param->example !== null) {
                $entry['example'] = $param->example;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param Header[] $headers
     * @return array<int, array<string, mixed>>
     */
    private function buildHeaderParameters(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            $entry = [
                'name' => $header->name,
                'in' => 'header',
                'required' => $header->required,
                'description' => $header->description ?? '',
                'schema' => [
                    'type' => 'string',
                ],
            ];

            if ($header->value !== '') {
                $entry['example'] = $header->value;
            }

            $result[] = $entry;
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
                'type' => ExampleValueResolver::openApiType($param->type),
                'description' => $param->description ?? '',
            ];

            if ($param->example !== null) {
                $properties[$param->name]['example'] = $param->example;
            }
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
            $example[$param->name] = $this->exampleValue($param);
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

    private function exampleValue(Parameter $param): mixed
    {
        return $param->example ?? ExampleValueResolver::valueForType($param->type);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int|string, array<string, mixed>>
     */
    private function buildResponses(array $config, Endpoint $endpoint): array
    {
        $responses = $endpoint->responses;
        if ($responses === [] && (bool) data_get($config, 'responses.auto_from_request', true)) {
            $auto = $this->autoResponseFromRequest($config, $endpoint);
            if ($auto !== null) {
                $responses = [$auto];
            }
        }

        if ($responses === []) {
            return [
                '200' => [
                    'description' => 'Successful response',
                ],
            ];
        }

        $result = [];
        foreach ($responses as $response) {
            $entry = [
                'description' => $response->description !== '' ? $response->description : $this->statusName($response->status),
            ];

            if ($response->headers !== []) {
                $entry['headers'] = $this->buildResponseHeaders($response->headers);
            }

            if ($response->body !== null) {
                $mediaType = $response->mediaType ?? 'application/json';
                $entry['content'] = [
                    $mediaType => [
                        'schema' => $this->schemaFromBody($response->body),
                        'example' => $response->body,
                    ],
                ];
            }

            $result[(string) $response->status] = $entry;
        }

        return $result;
    }

    /**
     * @param \Nutandc\PostmanGenerator\ValueObjects\Header[] $headers
     * @return array<string, array<string, mixed>>
     */
    private function buildResponseHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            $result[$header->name] = [
                'description' => $header->description ?? '',
                'schema' => [
                    'type' => 'string',
                ],
                'example' => $header->value,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFromBody(mixed $body): array
    {
        if (is_array($body)) {
            if (array_is_list($body)) {
                return [
                    'type' => 'array',
                    'items' => $body !== [] ? $this->schemaFromBody($body[0]) : ['type' => 'string'],
                ];
            }

            return [
                'type' => 'object',
            ];
        }

        if (is_numeric($body)) {
            return [
                'type' => 'number',
            ];
        }

        if (is_bool($body)) {
            return [
                'type' => 'boolean',
            ];
        }

        return [
            'type' => 'string',
        ];
    }

    private function statusName(int $status): string
    {
        return match ($status) {
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Server Error',
            default => 'Status ' . $status,
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function autoResponseFromRequest(array $config, Endpoint $endpoint): ?\Nutandc\PostmanGenerator\ValueObjects\ResponseDefinition
    {
        $example = null;
        if ($endpoint->bodyParams !== []) {
            $example = $this->buildBodyExample($endpoint->bodyParams);
        } elseif ($endpoint->queryParams !== []) {
            $example = $this->buildBodyExample($endpoint->queryParams);
        }

        if ($example === null) {
            return null;
        }

        return new \Nutandc\PostmanGenerator\ValueObjects\ResponseDefinition(
            status: (int) data_get($config, 'responses.default_status', 200),
            description: (string) data_get($config, 'responses.default_description', 'OK'),
            headers: [],
            body: $example,
            mediaType: 'application/json',
        );
    }
}
