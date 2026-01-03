<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Services;

use Illuminate\Routing\Route;
use Nutandc\PostmanGenerator\Contracts\EndpointMetadataProviderInterface;
use Nutandc\PostmanGenerator\ValueObjects\EndpointMetadata;

final class EndpointMetadataResolver
{
    /**
     * @param EndpointMetadataProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {
    }

    public function resolve(Route $route): EndpointMetadata
    {
        $metadata = new EndpointMetadata();
        foreach ($this->providers as $provider) {
            $metadata = $metadata->merge($provider->provide($route));
        }

        return $metadata;
    }
}
