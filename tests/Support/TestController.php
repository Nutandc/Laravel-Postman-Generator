<?php

declare(strict_types=1);

namespace Tests\Support;

use Nutandc\PostmanGenerator\Attributes\EndpointDoc;

final class TestController
{
    /**
     * @return array<string, bool>
     */
    #[EndpointDoc(summary: 'List users', tags: ['Users'], auth: 'bearer', query: [
        ['name' => 'page', 'type' => 'integer', 'required' => false, 'example' => 2],
    ], headers: [
        ['name' => 'X-Request-ID', 'value' => '{{request_id}}', 'required' => false, 'description' => 'Trace request id.'],
    ])]
    public function index(): array
    {
        return ['ok' => true];
    }

    /**
     * @return array<string, bool>
     */
    #[EndpointDoc(summary: 'Create user', tags: ['Users'], auth: 'api_key', body: [
        ['name' => 'email', 'type' => 'string', 'required' => true, 'example' => 'user@example.com'],
    ], headers: [
        ['name' => 'X-Client-ID', 'value' => 'client-id', 'required' => true],
    ])]
    public function store(): array
    {
        return ['ok' => true];
    }
}
