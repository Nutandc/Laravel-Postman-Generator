<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\ValueObjects;

final class Parameter
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $required,
        public readonly ?string $description = null,
    ) {
    }
}
