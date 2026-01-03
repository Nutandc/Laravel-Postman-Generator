<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class EndpointDoc
{
    /**
     * @param string[] $tags
     * @param array<int, array{name: string, type: string, required: bool, description?: string}> $query
     * @param array<int, array{name: string, type: string, required: bool, description?: string}> $body
     */
    public function __construct(
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly array $tags = [],
        public readonly ?string $auth = null,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly bool $deprecated = false,
    ) {
    }
}
