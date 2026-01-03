<?php

declare(strict_types=1);

namespace Tests\Unit;

use Nutandc\PostmanGenerator\Services\RouteScanner;
use Tests\Support\TestController;
use Tests\TestCase;

final class RouteScannerTest extends TestCase
{
    public function testScannerBuildsEndpoints(): void
    {
        $this->app['router']->get('api/users', [TestController::class, 'index'])->name('users.index');
        $this->app['router']->get('_debugbar/open', [TestController::class, 'index'])->name('debugbar.openhandler');

        $scanner = new RouteScanner($this->app['router'], config('postman-generator'));
        $endpoints = $scanner->scan();

        $this->assertCount(1, $endpoints);
        $this->assertSame('users.index', $endpoints[0]->name);
        $this->assertSame(['GET'], $endpoints[0]->methods);
        $this->assertSame('List users', $endpoints[0]->summary);
        $this->assertSame('Users', $endpoints[0]->group);
        $this->assertSame('X-Request-ID', $endpoints[0]->headers[0]->name);
        $this->assertSame(2, $endpoints[0]->queryParams[0]->example);
    }
}
