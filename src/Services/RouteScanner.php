<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Nutandc\PostmanGenerator\Contracts\EndpointScannerInterface;
use Nutandc\PostmanGenerator\ValueObjects\Endpoint;
use Nutandc\PostmanGenerator\ValueObjects\EndpointMetadata;
use Nutandc\PostmanGenerator\ValueObjects\Parameter;

final class RouteScanner implements EndpointScannerInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly EndpointMetadataResolver $metadataResolver,
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

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $methods = $this->filterMethods($route->methods());
            if ($methods === []) {
                continue;
            }

            $metadata = $this->metadataResolver->resolve($route);

            if (! $this->isAllowed($route, $metadata, $methods)) {
                continue;
            }

            $endpoints[] = $this->buildEndpoint($route, $metadata, $methods);
        }

        return $endpoints;
    }

    /**
     * @param string[] $methods
     */
    private function isAllowed(Route $route, EndpointMetadata $metadata, array $methods): bool
    {
        $uri = ltrim($route->uri(), '/');

        foreach ($this->configValue('scan.exclude_prefixes', []) as $prefix) {
            if ($prefix !== '' && str_starts_with($uri, trim((string) $prefix, '/'))) {
                return false;
            }
        }

        $name = $route->getName();
        foreach ($this->configValue('scan.exclude_route_names', []) as $prefix) {
            if ($name && $prefix !== '' && str_starts_with($name, (string) $prefix)) {
                return false;
            }
        }

        $includePrefixes = $this->configValue('scan.include_prefixes', []);
        if ($includePrefixes !== []) {
            $matched = false;
            foreach ($includePrefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($uri, trim((string) $prefix, '/'))) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        $onlyMiddleware = $this->configValue('scan.only_middleware', []);
        if ($onlyMiddleware !== []) {
            $middleware = $route->middleware();
            $allowed = array_values(array_intersect($onlyMiddleware, $middleware));
            if ($allowed === []) {
                return false;
            }
        }

        foreach ($this->configValue('scan.exclude_middleware', []) as $middleware) {
            if (in_array($middleware, $route->middleware(), true)) {
                return false;
            }
        }

        if (! $this->passesTagFilter($metadata)) {
            return false;
        }

        if (! $this->passesNamespaceFilter($route)) {
            return false;
        }

        if (! $this->passesDomainFilter($route)) {
            return false;
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

    /**
     * @param string[] $methods
     */
    private function buildEndpoint(Route $route, EndpointMetadata $metadata, array $methods): Endpoint
    {
        $name = $route->getName() ?: $route->uri();
        $action = $route->getActionName();
        $uri = $route->uri();

        $pathParams = [];
        foreach ($route->parameterNames() as $param) {
            $pathParams[] = new Parameter($param, 'string', true);
        }

        $group = $this->resolveGroup($route, $metadata->tags);

        return new Endpoint(
            uri: $uri,
            name: (string) $name,
            methods: $methods,
            action: (string) $action,
            summary: $metadata->summary,
            description: $metadata->description,
            tags: $metadata->tags,
            auth: $metadata->auth,
            pathParams: $pathParams,
            queryParams: $metadata->queryParams,
            bodyParams: $metadata->bodyParams,
            deprecated: (bool) ($metadata->deprecated ?? false),
            group: $group,
            headers: $metadata->headers,
            responses: $metadata->responses,
        );
    }

    /**
     * @param string[] $tags
     */
    private function resolveGroup(Route $route, array $tags): ?string
    {
        if ($tags !== []) {
            return (string) $tags[0];
        }

        $strategy = (string) $this->configValue('postman.grouping.strategy', 'uri');
        if ($strategy === 'none') {
            return null;
        }

        if ($strategy === 'name') {
            $name = $route->getName();
            if ($name) {
                $separator = (string) $this->configValue('postman.grouping.name_separator', '.');
                return explode($separator, $name)[0] ?: $name;
            }
        }

        $uri = ltrim($route->uri(), '/');
        foreach ($this->configValue('postman.grouping.strip_prefixes', []) as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix !== '' && str_starts_with($uri, $prefix . '/')) {
                $uri = substr($uri, strlen($prefix) + 1);
            }
        }

        $segments = array_values(array_filter(explode('/', $uri)));
        if ($segments === []) {
            return (string) $this->configValue('postman.grouping.fallback', 'General');
        }

        $depth = (int) $this->configValue('postman.grouping.uri_depth', 1);
        $depth = max(1, $depth);

        return implode('/', array_slice($segments, 0, $depth));
    }

    private function passesTagFilter(EndpointMetadata $metadata): bool
    {
        $includeTags = (array) $this->configValue('scan.include_tags', []);
        if ($includeTags !== []) {
            $matched = false;
            foreach ($metadata->tags as $tag) {
                if (in_array($tag, $includeTags, true)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        $excludeTags = (array) $this->configValue('scan.exclude_tags', []);
        foreach ($metadata->tags as $tag) {
            if (in_array($tag, $excludeTags, true)) {
                return false;
            }
        }

        return true;
    }

    private function passesNamespaceFilter(Route $route): bool
    {
        $namespace = $this->resolveControllerNamespace($route);

        $include = (array) $this->configValue('scan.include_namespaces', []);
        if ($include !== [] && $namespace !== null) {
            $matched = false;
            foreach ($include as $prefix) {
                if ($prefix !== '' && str_starts_with($namespace, (string) $prefix)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        $exclude = (array) $this->configValue('scan.exclude_namespaces', []);
        foreach ($exclude as $prefix) {
            if ($namespace && $prefix !== '' && str_starts_with($namespace, (string) $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function passesDomainFilter(Route $route): bool
    {
        $domain = (string) ($route->getDomain() ?? '');

        $include = (array) $this->configValue('scan.include_domains', []);
        if ($include !== []) {
            $matched = false;
            foreach ($include as $pattern) {
                if ($pattern !== '' && str_contains($domain, (string) $pattern)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        $exclude = (array) $this->configValue('scan.exclude_domains', []);
        foreach ($exclude as $pattern) {
            if ($pattern !== '' && $domain !== '' && str_contains($domain, (string) $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function resolveControllerNamespace(Route $route): ?string
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return null;
        }

        [$class] = explode('@', $action, 2);
        if (! str_contains($class, '\\')) {
            return null;
        }

        $parts = explode('\\', $class);
        array_pop($parts);

        return implode('\\', $parts);
    }

    private function configValue(string $key, mixed $default): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
