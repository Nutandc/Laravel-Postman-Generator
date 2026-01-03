<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Contracts;

use Illuminate\Routing\Route;
use Nutandc\PostmanGenerator\ValueObjects\EndpointMetadata;

interface EndpointMetadataProviderInterface
{
    public function provide(Route $route): EndpointMetadata;
}
