<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Builders;

use Nutandc\PostmanGenerator\Helpers\ExampleValueResolver;
use Nutandc\PostmanGenerator\ValueObjects\Endpoint;
use Nutandc\PostmanGenerator\ValueObjects\Parameter;

final class PostmanCollectionBuilder
{
    /**
     * @param array<string, mixed> $config
     * @param Endpoint[] $endpoints
     * @return array<string, mixed>
     */
    public function build(array $config, array $endpoints): array
    {
        $info = [
            'name' => (string) data_get($config, 'postman.name', 'API Collection'),
            'description' => (string) data_get($config, 'postman.description', ''),
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ];

        return [
            'info' => $info,
            'item' => $this->buildItems($config, $endpoints),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param Endpoint[] $endpoints
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $config, array $endpoints): array
    {
        $items = [];
        foreach ($endpoints as $endpoint) {
            foreach ($endpoint->methods as $method) {
                $items[] = $this->buildItem($config, $endpoint, $method);
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildItem(array $config, Endpoint $endpoint, string $method): array
    {
        $baseUrl = (string) data_get($config, 'base_url', '');
        $path = '/' . ltrim($endpoint->uri, '/');
        $rawUrl = rtrim($baseUrl, '/') . $path;

        $request = [
            'method' => $method,
            'header' => $this->buildAuthHeaders($config, $endpoint),
            'url' => $this->buildUrl($config, $rawUrl, $endpoint),
            'description' => $endpoint->description ?? $endpoint->summary ?? '',
        ];

        if ($endpoint->bodyParams !== []) {
            $request['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($this->buildBodyExample($endpoint->bodyParams), JSON_PRETTY_PRINT),
            ];
        }

        return [
            'name' => $endpoint->summary ?? $endpoint->name,
            'request' => $request,
        ];
    }

    /**
     * @param Parameter[] $params
     * @return array<string, mixed>
     */
    private function buildBodyExample(array $params): array
    {
        $example = [];
        foreach ($params as $param) {
            $example[$param->name] = ExampleValueResolver::valueForType($param->type);
        }

        return $example;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildUrl(array $config, string $rawUrl, Endpoint $endpoint): array
    {
        $pathSegments = array_values(array_filter(explode('/', trim($endpoint->uri, '/'))));
        $variables = [];
        foreach ($endpoint->pathParams as $param) {
            $variables[] = [
                'key' => $param->name,
                'value' => ExampleValueResolver::valueForType($param->type),
            ];
        }

        $query = [];
        foreach ($endpoint->queryParams as $param) {
            $query[] = [
                'key' => $param->name,
                'value' => ExampleValueResolver::valueForType($param->type),
                'disabled' => ! $param->required,
            ];
        }

        $query = $this->appendApiKeyQuery($config, $endpoint, $query);

        return [
            'raw' => $rawUrl,
            'path' => $pathSegments,
            'variable' => $variables,
            'query' => $query,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, array<string, string>>
     */
    private function buildAuthHeaders(array $config, Endpoint $endpoint): array
    {
        $auth = $endpoint->auth ?? data_get($config, 'auth.default', 'none');

        return match ($auth) {
            'bearer' => $this->buildBearerHeaders($config),
            'api_key' => $this->buildApiKeyHeaders($config),
            'basic' => $this->buildBasicHeaders($config),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, array<string, string>>
     */
    private function buildBearerHeaders(array $config): array
    {
        $token = (string) data_get($config, 'auth.bearer.token', '');
        if ($token === '') {
            return [];
        }

        return [[
            'key' => 'Authorization',
            'value' => 'Bearer ' . $token,
        ]];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, array<string, string>>
     */
    private function buildApiKeyHeaders(array $config): array
    {
        $key = (string) data_get($config, 'auth.api_key.key', 'X-API-KEY');
        $value = (string) data_get($config, 'auth.api_key.value', '');
        $location = (string) data_get($config, 'auth.api_key.in', 'header');

        if ($value === '' || $location !== 'header') {
            return [];
        }

        return [[
            'key' => $key,
            'value' => $value,
        ]];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, array<string, string>>
     */
    private function buildBasicHeaders(array $config): array
    {
        $user = (string) data_get($config, 'auth.basic.username', '');
        $pass = (string) data_get($config, 'auth.basic.password', '');
        if ($user === '' && $pass === '') {
            return [];
        }

        $token = base64_encode($user . ':' . $pass);

        return [[
            'key' => 'Authorization',
            'value' => 'Basic ' . $token,
        ]];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, array<string, mixed>> $query
     * @return array<int, array<string, mixed>>
     */
    private function appendApiKeyQuery(array $config, Endpoint $endpoint, array $query): array
    {
        $auth = $endpoint->auth ?? data_get($config, 'auth.default', 'none');
        if ($auth !== 'api_key') {
            return $query;
        }

        $location = (string) data_get($config, 'auth.api_key.in', 'header');
        if ($location !== 'query') {
            return $query;
        }

        $key = (string) data_get($config, 'auth.api_key.key', 'api_key');
        $value = (string) data_get($config, 'auth.api_key.value', '');

        $query[] = [
            'key' => $key,
            'value' => $value === '' ? 'api-key' : $value,
            'disabled' => false,
        ];

        return $query;
    }
}
