<?php

declare(strict_types=1);

namespace Tests\Support;

use Nutandc\PostmanGenerator\Attributes\EndpointDoc;

final class TestController
{
    #[EndpointDoc(summary: 'List users', tags: ['Users'], auth: 'bearer', query: [
        ['name' => 'page', 'type' => 'integer', 'required' => false],
    ])]
    public function index(): array
    {
        return ['ok' => true];
    }

    #[EndpointDoc(summary: 'Create user', tags: ['Users'], auth: 'api_key', body: [
        ['name' => 'email', 'type' => 'string', 'required' => true],
    ])]
    public function store(): array
    {
        return ['ok' => true];
    }
}
