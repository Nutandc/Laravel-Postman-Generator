<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Metadata\Providers;

use Illuminate\Routing\Route;
use Nutandc\PostmanGenerator\Contracts\EndpointMetadataProviderInterface;
use Nutandc\PostmanGenerator\Helpers\MetadataDefinitionMapper;
use Nutandc\PostmanGenerator\ValueObjects\EndpointMetadata;

final class OverridesMetadataProvider implements EndpointMetadataProviderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly MetadataDefinitionMapper $mapper,
        private readonly array $config,
    ) {
    }

    public function provide(Route $route): EndpointMetadata
    {
        $overrides = (array) ($this->config['overrides'] ?? []);
        $name = $route->getName();

        if (! $name || ! isset($overrides[$name]) || ! is_array($overrides[$name])) {
            return new EndpointMetadata();
        }

        $override = $overrides[$name];

        return new EndpointMetadata(
            summary: $override['summary'] ?? null,
            description: $override['description'] ?? null,
            tags: $override['tags'] ?? [],
            auth: $override['auth'] ?? null,
            headers: $this->mapper->headers($override['headers'] ?? []),
            queryParams: $this->mapper->parameters($override['query'] ?? []),
            bodyParams: $this->mapper->parameters($override['body'] ?? []),
            responses: $this->mapper->responses($override['responses'] ?? []),
            deprecated: $override['deprecated'] ?? null,
        );
    }
}
