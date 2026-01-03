<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Metadata\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Nutandc\PostmanGenerator\Contracts\EndpointMetadataProviderInterface;
use Nutandc\PostmanGenerator\Helpers\ValidationRulesParser;
use Nutandc\PostmanGenerator\ValueObjects\EndpointMetadata;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

final class FormRequestMetadataProvider implements EndpointMetadataProviderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly Container $container,
        private readonly ValidationRulesParser $rulesParser,
        private readonly array $config,
    ) {
    }

    public function provide(Route $route): EndpointMetadata
    {
        $enabled = (bool) data_get($this->config, 'scan.form_request.enabled', true);
        if (! $enabled) {
            return new EndpointMetadata();
        }

        $requestClass = $this->resolveFormRequestClass($route);
        if ($requestClass === null) {
            return new EndpointMetadata();
        }

        $rules = $this->resolveFormRequestRules($requestClass);
        if ($rules === []) {
            return new EndpointMetadata();
        }

        $params = $this->rulesParser->parametersFromRules($rules);
        if ($params === []) {
            return new EndpointMetadata();
        }

        if ($this->isQueryOnly($route->methods())) {
            return new EndpointMetadata(queryParams: $params);
        }

        return new EndpointMetadata(bodyParams: $params);
    }

    private function resolveFormRequestClass(Route $route): ?string
    {
        $action = $route->getActionName();
        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $action);
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        if (! $reflection->hasMethod($method)) {
            return null;
        }

        $methodRef = $reflection->getMethod($method);
        foreach ($methodRef->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if (is_subclass_of($typeName, FormRequest::class)) {
                return $typeName;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFormRequestRules(string $requestClass): array
    {
        $request = $this->createFormRequestInstance($requestClass);
        if ($request === null || ! method_exists($request, 'rules')) {
            return [];
        }

        $rules = $request->rules();
        if (! is_array($rules)) {
            return [];
        }

        return $rules;
    }

    private function createFormRequestInstance(string $requestClass): ?object
    {
        try {
            $request = new $requestClass();
        } catch (Throwable) {
            try {
                $request = (new ReflectionClass($requestClass))->newInstanceWithoutConstructor();
            } catch (Throwable) {
                return null;
            }
        }

        if ($request instanceof FormRequest && method_exists($request, 'setContainer')) {
            $request->setContainer($this->container);
        }

        return $request;
    }

    /**
     * @param string[] $methods
     */
    private function isQueryOnly(array $methods): bool
    {
        $methods = array_values(array_diff(array_map('strtoupper', $methods), ['HEAD']));

        return $methods === ['GET'];
    }
}
