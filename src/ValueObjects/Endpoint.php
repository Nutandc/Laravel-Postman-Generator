<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\ValueObjects;

final class Endpoint
{
    /**
     * @param string[] $methods
     * @param string[] $tags
     * @param Parameter[] $pathParams
     * @param Parameter[] $queryParams
     * @param Parameter[] $bodyParams
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly array $methods,
        public readonly string $action,
        public readonly ?string $summary,
        public readonly ?string $description,
        public readonly array $tags,
        public readonly ?string $auth,
        public readonly array $pathParams,
        public readonly array $queryParams,
        public readonly array $bodyParams,
        public readonly bool $deprecated,
    ) {
    }
}
