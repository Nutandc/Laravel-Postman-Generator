<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Metadata\Providers;

use Illuminate\Routing\Route;
use Nutandc\PostmanGenerator\Attributes\EndpointDoc;
use Nutandc\PostmanGenerator\Contracts\EndpointMetadataProviderInterface;
use Nutandc\PostmanGenerator\Helpers\MetadataDefinitionMapper;
use Nutandc\PostmanGenerator\ValueObjects\EndpointMetadata;
use ReflectionClass;

final class AttributeMetadataProvider implements EndpointMetadataProviderInterface
{
    public function __construct(
        private readonly MetadataDefinitionMapper $mapper,
    ) {
    }

    public function provide(Route $route): EndpointMetadata
    {
        $action = $route->getActionName();
        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return new EndpointMetadata();
        }

        [$class, $method] = explode('@', $action);
        if (! class_exists($class)) {
            return new EndpointMetadata();
        }

        $reflection = new ReflectionClass($class);
        if (! $reflection->hasMethod($method)) {
            return new EndpointMetadata();
        }

        $methodRef = $reflection->getMethod($method);
        $attributes = $methodRef->getAttributes(EndpointDoc::class);
        if ($attributes === []) {
            return new EndpointMetadata();
        }

        $instance = $attributes[0]->newInstance();

        return new EndpointMetadata(
            summary: $instance->summary,
            description: $instance->description,
            tags: $instance->tags,
            auth: $instance->auth,
            headers: $this->mapper->headers($instance->headers),
            queryParams: $this->mapper->parameters($instance->query),
            bodyParams: $this->mapper->parameters($instance->body),
            responses: $this->mapper->responses($instance->responses),
            deprecated: $instance->deprecated,
        );
    }
}
