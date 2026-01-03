<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Builders;

use Nutandc\PostmanGenerator\Helpers\ExampleValueResolver;
use Nutandc\PostmanGenerator\ValueObjects\Endpoint;
use Nutandc\PostmanGenerator\ValueObjects\Header;
use Nutandc\PostmanGenerator\ValueObjects\Parameter;
use Nutandc\PostmanGenerator\ValueObjects\ResponseDefinition;

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

        $collection = [
            'info' => $info,
            'item' => $this->buildItems($config, $endpoints),
        ];

        $variables = $this->buildVariables($config);
        if ($variables !== []) {
            $collection['variable'] = $variables;
        }

        return $collection;
    }

    /**
     * @param array<string, mixed> $config
     * @param Endpoint[] $endpoints
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $config, array $endpoints): array
    {
        $grouped = $this->groupEndpoints($config, $endpoints);
        if ($grouped !== null) {
            return $this->buildGroupedItems($config, $grouped);
        }

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
     * @param array<string, Endpoint[]> $grouped
     * @return array<int, array<string, mixed>>
     */
    private function buildGroupedItems(array $config, array $grouped): array
    {
        $items = [];
        foreach ($grouped as $group => $endpoints) {
            $children = [];
            foreach ($endpoints as $endpoint) {
                foreach ($endpoint->methods as $method) {
                    $children[] = $this->buildItem($config, $endpoint, $method);
                }
            }

            $items[] = [
                'name' => $group,
                'item' => $children,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildItem(array $config, Endpoint $endpoint, string $method): array
    {
        $baseUrl = $this->resolveBaseUrl($config);
        $path = '/' . ltrim($endpoint->uri, '/');
        $rawUrl = rtrim($baseUrl, '/') . $path;
        $hasBody = $this->shouldIncludeBody($endpoint, $method);

        $request = [
            'method' => $method,
            'header' => $this->buildHeaders($config, $endpoint, $hasBody),
            'url' => $this->buildUrl($config, $rawUrl, $endpoint),
            'description' => $this->buildDescription($endpoint),
        ];

        $auth = $this->buildAuth($config, $endpoint);
        if ($auth !== null) {
            $request['auth'] = $auth;
        }

        if ($hasBody) {
            $request['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($this->buildBodyExample($endpoint->bodyParams), JSON_PRETTY_PRINT),
            ];
        }

        $item = [
            'name' => $endpoint->summary ?? $endpoint->name,
            'request' => $request,
        ];

        $responses = $this->buildResponses($config, $endpoint, $request);
        if ($responses !== []) {
            $item['response'] = $responses;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $config
     * @param Endpoint[] $endpoints
     * @return array<string, Endpoint[]>|null
     */
    private function groupEndpoints(array $config, array $endpoints): ?array
    {
        $enabled = (bool) data_get($config, 'postman.grouping.enabled', true);
        if (! $enabled) {
            return null;
        }

        $groups = [];
        foreach ($endpoints as $endpoint) {
            $group = $endpoint->group ?? (string) data_get($config, 'postman.grouping.fallback', 'General');
            $groups[$group][] = $endpoint;
        }

        ksort($groups);

        return $groups;
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
    private function buildUrl(array $config, string $rawUrl, Endpoint $endpoint): array
    {
        $pathSegments = array_values(array_filter(explode('/', trim($endpoint->uri, '/'))));
        $variables = [];
        foreach ($endpoint->pathParams as $param) {
            $variables[] = [
                'key' => $param->name,
                'value' => $this->exampleValue($param),
            ];
        }

        $query = [];
        foreach ($endpoint->queryParams as $param) {
            $query[] = [
                'key' => $param->name,
                'value' => $this->exampleValue($param),
                'disabled' => ! $param->required,
                'description' => $param->description ?? '',
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
        if ($value === '' && $this->hasVariable($config, 'api_key')) {
            $value = $this->variablePlaceholder('api_key');
        }

        $query[] = [
            'key' => $key,
            'value' => $value,
            'disabled' => false,
        ];

        return $query;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    private function buildHeaders(array $config, Endpoint $endpoint, bool $hasBody): array
    {
        $defaults = $this->buildHeaderObjects((array) data_get($config, 'headers.default', []));
        $jsonHeaders = $hasBody ? $this->buildHeaderObjects((array) data_get($config, 'headers.json', [])) : [];

        $headers = $this->mergeHeaders($defaults, $jsonHeaders, $endpoint->headers);

        return $this->formatHeaders($headers);
    }

    /**
     * @param array<int, array{name?: string, value?: string, required?: bool, description?: string}> $definitions
     * @return Header[]
     */
    private function buildHeaderObjects(array $definitions): array
    {
        $headers = [];
        foreach ($definitions as $definition) {
            if (! isset($definition['name'], $definition['value'])) {
                continue;
            }

            $headers[] = new Header(
                name: (string) $definition['name'],
                value: (string) $definition['value'],
                required: (bool) ($definition['required'] ?? false),
                description: $definition['description'] ?? null,
            );
        }

        return $headers;
    }

    /**
     * @param Header[] ...$groups
     * @return Header[]
     */
    private function mergeHeaders(array ...$groups): array
    {
        $merged = [];
        foreach ($groups as $group) {
            foreach ($group as $header) {
                $merged[strtolower($header->name)] = $header;
            }
        }

        return array_values($merged);
    }

    /**
     * @param Header[] $headers
     * @return array<int, array<string, mixed>>
     */
    private function formatHeaders(array $headers): array
    {
        $items = [];
        foreach ($headers as $header) {
            $item = [
                'key' => $header->name,
                'value' => $header->value,
                'disabled' => ! $header->required,
            ];

            if ($header->description !== null) {
                $item['description'] = $header->description;
            }

            $items[] = $item;
        }

        return $items;
    }

    private function buildDescription(Endpoint $endpoint): string
    {
        if ($endpoint->summary && $endpoint->description) {
            return $endpoint->summary . "\n\n" . $endpoint->description;
        }

        return $endpoint->description ?? $endpoint->summary ?? '';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    private function buildAuth(array $config, Endpoint $endpoint): ?array
    {
        $auth = $endpoint->auth ?? data_get($config, 'auth.default', 'none');

        return match ($auth) {
            'bearer' => $this->buildBearerAuth($config),
            'api_key' => $this->buildApiKeyAuth($config),
            'basic' => $this->buildBasicAuth($config),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildBearerAuth(array $config): array
    {
        $token = (string) data_get($config, 'auth.bearer.token', '');
        if ($token === '' && $this->hasVariable($config, 'token')) {
            $token = $this->variablePlaceholder('token');
        }

        return [
            'type' => 'bearer',
            'bearer' => [
                [
                    'key' => 'token',
                    'value' => $token,
                    'type' => 'string',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildApiKeyAuth(array $config): array
    {
        $key = (string) data_get($config, 'auth.api_key.key', 'X-API-KEY');
        $value = (string) data_get($config, 'auth.api_key.value', '');
        $location = (string) data_get($config, 'auth.api_key.in', 'header');

        if ($value === '' && $this->hasVariable($config, 'api_key')) {
            $value = $this->variablePlaceholder('api_key');
        }

        return [
            'type' => 'apikey',
            'apikey' => [
                [
                    'key' => 'key',
                    'value' => $key,
                    'type' => 'string',
                ],
                [
                    'key' => 'value',
                    'value' => $value,
                    'type' => 'string',
                ],
                [
                    'key' => 'in',
                    'value' => $location,
                    'type' => 'string',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildBasicAuth(array $config): array
    {
        $user = (string) data_get($config, 'auth.basic.username', '');
        $pass = (string) data_get($config, 'auth.basic.password', '');

        return [
            'type' => 'basic',
            'basic' => [
                [
                    'key' => 'username',
                    'value' => $user,
                    'type' => 'string',
                ],
                [
                    'key' => 'password',
                    'value' => $pass,
                    'type' => 'string',
                ],
            ],
        ];
    }

    private function exampleValue(Parameter $param): mixed
    {
        return $param->example ?? ExampleValueResolver::valueForType($param->type);
    }

    private function shouldIncludeBody(Endpoint $endpoint, string $method): bool
    {
        if ($endpoint->bodyParams === []) {
            return false;
        }

        return in_array($method, ['POST', 'PUT', 'PATCH'], true);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, array<string, string>>
     */
    private function buildVariables(array $config): array
    {
        $input = (array) data_get($config, 'postman.variables', []);
        $useBaseUrlVariable = (bool) data_get($config, 'postman.use_base_url_variable', false);
        if ($useBaseUrlVariable) {
            $baseUrl = (string) data_get($config, 'base_url', '');
            if (! array_key_exists('base_url', $input) || $input['base_url'] === '') {
                $input['base_url'] = $baseUrl;
            }
        }

        $variables = [];
        foreach ($input as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $variables[] = [
                'key' => $key,
                'value' => is_scalar($value) ? (string) $value : '',
            ];
        }

        return $variables;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveBaseUrl(array $config): string
    {
        $useVariable = (bool) data_get($config, 'postman.use_base_url_variable', false);
        if ($useVariable && $this->hasVariable($config, 'base_url')) {
            return $this->variablePlaceholder('base_url');
        }

        return (string) data_get($config, 'base_url', '');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasVariable(array $config, string $key): bool
    {
        $variables = (array) data_get($config, 'postman.variables', []);

        return array_key_exists($key, $variables);
    }

    private function variablePlaceholder(string $key): string
    {
        return '{{' . $key . '}}';
    }

    /**
     * @param array<string, mixed> $request
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $request
     * @return array<int, array<string, mixed>>
     */
    private function buildResponses(array $config, Endpoint $endpoint, array $request): array
    {
        $responses = $endpoint->responses;
        if ($responses === [] && (bool) data_get($config, 'responses.auto_from_request', true)) {
            $auto = $this->autoResponseFromRequest($config, $endpoint);
            if ($auto !== null) {
                $responses = [$auto];
            }
        }

        if ($responses === []) {
            return [];
        }

        $result = [];
        foreach ($responses as $response) {
            $body = $response->body;
            if (is_array($body)) {
                $body = json_encode($body, JSON_PRETTY_PRINT);
            }

            $result[] = [
                'name' => $response->description !== '' ? $response->description : $this->statusName($response->status),
                'originalRequest' => $request,
                'status' => $this->statusName($response->status),
                'code' => $response->status,
                'header' => $this->formatHeaders($response->headers),
                'body' => $body ?? '',
                '_postman_previewlanguage' => $this->previewLanguage($response->mediaType),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function autoResponseFromRequest(array $config, Endpoint $endpoint): ?ResponseDefinition
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

        return new ResponseDefinition(
            status: (int) data_get($config, 'responses.default_status', 200),
            description: (string) data_get($config, 'responses.default_description', 'OK'),
            headers: [],
            body: $example,
            mediaType: 'application/json',
        );
    }

    private function previewLanguage(?string $mediaType): string
    {
        return match ($mediaType) {
            'application/json' => 'json',
            'application/xml', 'text/xml' => 'xml',
            default => 'text',
        };
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
}
