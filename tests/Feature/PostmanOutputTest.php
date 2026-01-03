<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Nutandc\PostmanGenerator\Services\GeneratorService;
use Tests\Support\TestController;
use Tests\TestCase;

final class PostmanOutputTest extends TestCase
{
    public function testPostmanOutputIncludesAuthHeadersAndExamples(): void
    {
        $this->app['router']->post('api/users', [TestController::class, 'store'])->name('users.store');

        config([
            'postman-generator.base_url' => 'https://api.test',
            'postman-generator.output.path' => storage_path('app/postman-collection'),
            'postman-generator.output.postman.enabled' => true,
            'postman-generator.output.openapi.enabled' => false,
            'postman-generator.postman.grouping.enabled' => false,
            'postman-generator.postman.use_base_url_variable' => true,
        ]);

        File::deleteDirectory(storage_path('app/postman-collection'));

        $results = $this->app->make(GeneratorService::class)->generate();
        $payload = json_decode(File::get($results['postman']), true);

        $requests = $this->flattenRequests($payload['item'] ?? []);
        $request = $requests['Create user']['request'] ?? [];
        $responses = $requests['Create user']['response'] ?? [];

        $this->assertSame('POST', $request['method'] ?? null);
        $this->assertSame('{{base_url}}/api/users', $request['url']['raw'] ?? null);
        $this->assertSame('apikey', $request['auth']['type'] ?? null);
        $this->assertTrue($this->hasHeader($request['header'] ?? [], 'Accept'));
        $this->assertTrue($this->hasHeader($request['header'] ?? [], 'Content-Type'));
        $this->assertTrue($this->hasHeader($request['header'] ?? [], 'X-Client-ID'));

        $body = json_decode((string) ($request['body']['raw'] ?? ''), true);
        $this->assertSame('user@example.com', $body['email'] ?? null);

        $this->assertSame(201, $responses[0]['code'] ?? null);
        $responseBody = json_decode((string) ($responses[0]['body'] ?? ''), true);
        $this->assertSame(1, $responseBody['data']['id'] ?? null);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    private function flattenRequests(array $items): array
    {
        $requests = [];
        foreach ($items as $item) {
            if (isset($item['request'])) {
                $requests[(string) $item['name']] = $item;
                continue;
            }

            foreach (($item['item'] ?? []) as $child) {
                if (isset($child['request'])) {
                    $requests[(string) $child['name']] = $child;
                }
            }
        }

        return $requests;
    }

    /**
     * @param array<int, array<string, mixed>> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $header) {
            if (($header['key'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
}
