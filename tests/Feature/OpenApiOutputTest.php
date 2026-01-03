<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Nutandc\PostmanGenerator\Services\GeneratorService;
use Tests\Support\TestController;
use Tests\TestCase;

final class OpenApiOutputTest extends TestCase
{
    public function testOpenApiOutputContainsRequiredFields(): void
    {
        $this->app['router']->get('api/users', [TestController::class, 'index'])->name('users.index');

        config([
            'postman-generator.base_url' => 'https://example.test',
            'postman-generator.output.path' => storage_path('app/postman-openapi'),
            'postman-generator.output.postman.enabled' => false,
            'postman-generator.output.openapi.enabled' => true,
            'postman-generator.openapi.title' => 'Test API',
        ]);

        File::deleteDirectory(storage_path('app/postman-openapi'));

        $results = $this->app->make(GeneratorService::class)->generate();

        $this->assertArrayHasKey('openapi', $results);
        $payload = json_decode(File::get($results['openapi']), true);

        $this->assertSame('3.0.3', $payload['openapi'] ?? null);
        $this->assertSame('Test API', $payload['info']['title'] ?? null);
        $this->assertSame('https://example.test', $payload['servers'][0]['url'] ?? null);
        $this->assertArrayHasKey('/api/users', $payload['paths'] ?? []);
    }
}
