<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\ValueObjects;

final class Header
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly bool $required = false,
        public readonly ?string $description = null,
    ) {
    }
}
