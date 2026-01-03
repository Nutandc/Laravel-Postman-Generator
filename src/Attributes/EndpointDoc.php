<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class EndpointDoc
{
    /**
     * @param string[] $tags
     * @param array<int, array{name: string, value: string, required?: bool, description?: string}> $headers
     * @param array<int, array{name: string, type: string, required: bool, description?: string, example?: mixed}> $query
     * @param array<int, array{name: string, type: string, required: bool, description?: string, example?: mixed}> $body
     * @param array<int, array{status: int, description?: string, body?: mixed, example?: mixed, headers?: array<int, array{name: string, value: string, required?: bool, description?: string}>, media_type?: string}> $responses
     */
    public function __construct(
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly array $tags = [],
        public readonly ?string $auth = null,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $responses = [],
        public readonly bool $deprecated = false,
    ) {
    }
}
