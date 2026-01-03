<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Support\TestController;
use Tests\TestCase;

final class GenerateCommandTest extends TestCase
{
    public function testCommandGeneratesFiles(): void
    {
        $this->app['router']->get('api/users', [TestController::class, 'index'])->name('users.index');
        $this->app['router']->post('api/users', [TestController::class, 'store'])->name('users.store');

        config([
            'postman-generator.base_url' => 'https://example.test',
            'postman-generator.output.path' => storage_path('app/postman-test'),
        ]);

        File::deleteDirectory(storage_path('app/postman-test'));

        $this->artisan('postman:generate')->assertExitCode(0);

        $this->assertTrue(File::exists(storage_path('app/postman-test/collection.json')));
        $this->assertTrue(File::exists(storage_path('app/postman-test/openapi.json')));
    }
}
