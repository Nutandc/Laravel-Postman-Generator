<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Nutandc\PostmanGenerator\Attributes\EndpointDoc;
use Nutandc\PostmanGenerator\ValueObjects\Endpoint;
use Nutandc\PostmanGenerator\ValueObjects\Parameter;
use ReflectionClass;
use ReflectionMethod;

final class RouteScanner
{
    public function __construct(
        private readonly Router $router,
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {
    }

    /**
     * @return Endpoint[]
     */
    public function scan(): array
    {
        $endpoints = [];

        foreach ($this->router->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            if (! $this->isAllowed($route)) {
                continue;
            }

            $endpoints[] = $this->buildEndpoint($route);
        }

        return $endpoints;
    }

    private function isAllowed(Route $route): bool
    {
        $uri = ltrim($route->uri(), '/');
        $methods = $this->filterMethods($route->methods());

        if ($methods === []) {
            return false;
        }

        foreach ($this->configValue('scan.exclude_prefixes', []) as $prefix) {
            if ($prefix !== '' && str_starts_with($uri, trim($prefix, '/'))) {
                return false;
            }
        }

        $includePrefixes = $this->configValue('scan.include_prefixes', []);
        if ($includePrefixes !== []) {
            $matched = false;
            foreach ($includePrefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($uri, trim($prefix, '/'))) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        foreach ($this->configValue('scan.exclude_middleware', []) as $middleware) {
            if (in_array($middleware, $route->middleware(), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string[] $methods
     * @return string[]
     */
    private function filterMethods(array $methods): array
    {
        $allowed = array_map('strtoupper', (array) $this->configValue('scan.only_methods', []));
        $methods = array_map('strtoupper', $methods);

        if ($allowed === []) {
            return array_values(array_diff($methods, ['HEAD']));
        }

        return array_values(array_intersect($methods, $allowed));
    }

    private function buildEndpoint(Route $route): Endpoint
    {
        $name = $route->getName() ?: $route->uri();
        $action = $route->getActionName();
        $methods = $this->filterMethods($route->methods());
        $uri = $route->uri();

        $pathParams = [];
        foreach ($route->parameterNames() as $param) {
            $pathParams[] = new Parameter($param, 'string', true);
        }

        $summary = null;
        $description = null;
        $tags = [];
        $auth = null;
        $queryParams = [];
        $bodyParams = [];
        $deprecated = false;

        $meta = $this->resolveMetadata($route);
        if ($meta !== []) {
            $summary = $meta['summary'] ?? null;
            $description = $meta['description'] ?? null;
            $tags = $meta['tags'] ?? [];
            $auth = $meta['auth'] ?? null;
            $queryParams = $this->buildParams($meta['query'] ?? []);
            $bodyParams = $this->buildParams($meta['body'] ?? []);
            $deprecated = (bool) ($meta['deprecated'] ?? false);
        }

        return new Endpoint(
            uri: $uri,
            name: (string) $name,
            methods: $methods,
            action: (string) $action,
            summary: $summary,
            description: $description,
            tags: $tags,
            auth: $auth,
            pathParams: $pathParams,
            queryParams: $queryParams,
            bodyParams: $bodyParams,
            deprecated: $deprecated,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetadata(Route $route): array
    {
        $overrides = $this->configValue('overrides', []);
        $name = $route->getName();
        if ($name && isset($overrides[$name]) && is_array($overrides[$name])) {
            return $overrides[$name];
        }

        $action = $route->getActionName();
        if ($action === 'Closure') {
            return [];
        }

        if (! str_contains($action, '@')) {
            return [];
        }

        [$class, $method] = explode('@', $action);
        if (! class_exists($class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);
        if (! $reflection->hasMethod($method)) {
            return [];
        }

        $methodRef = $reflection->getMethod($method);

        return $this->resolveAttribute($methodRef);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAttribute(ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(EndpointDoc::class);
        if ($attributes === []) {
            return [];
        }

        $instance = $attributes[0]->newInstance();

        return [
            'summary' => $instance->summary,
            'description' => $instance->description,
            'tags' => $instance->tags,
            'auth' => $instance->auth,
            'query' => $instance->query,
            'body' => $instance->body,
            'deprecated' => $instance->deprecated,
        ];
    }

    /**
     * @param array<int, array{name: string, type: string, required: bool, description?: string}> $definitions
     * @return Parameter[]
     */
    private function buildParams(array $definitions): array
    {
        $params = [];
        foreach ($definitions as $definition) {
            $params[] = new Parameter(
                name: (string) ($definition['name'] ?? ''),
                type: (string) ($definition['type'] ?? 'string'),
                required: (bool) ($definition['required'] ?? false),
                description: $definition['description'] ?? null,
            );
        }

        return $params;
    }

    private function configValue(string $key, mixed $default): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
