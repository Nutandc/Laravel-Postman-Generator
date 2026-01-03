<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\ValueObjects;

final class ResponseDefinition
{
    /**
     * @param Header[] $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $description,
        public readonly array $headers = [],
        public readonly mixed $body = null,
        public readonly ?string $mediaType = 'application/json',
    ) {
    }
}
