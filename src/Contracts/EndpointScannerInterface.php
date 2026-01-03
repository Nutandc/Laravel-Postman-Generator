<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Contracts;

use Nutandc\PostmanGenerator\ValueObjects\Endpoint;

interface EndpointScannerInterface
{
    /**
     * @return Endpoint[]
     */
    public function scan(): array;
}
