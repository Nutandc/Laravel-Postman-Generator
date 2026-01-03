<?php

declare(strict_types=1);

namespace Tests\Unit;

use Nutandc\PostmanGenerator\Services\RouteScanner;
use Nutandc\PostmanGenerator\Helpers\ValidationRulesParser;
use Tests\Support\TestController;
use Tests\TestCase;

final class RouteScannerTest extends TestCase
{
    public function testScannerBuildsEndpoints(): void
    {
        $this->app['router']->get('api/users', [TestController::class, 'index'])->name('users.index');
        $this->app['router']->get('_debugbar/open', [TestController::class, 'index'])->name('debugbar.openhandler');
        $this->app['router']->post('api/users/request', [TestController::class, 'storeWithRequest'])->name('users.request');

        $scanner = new RouteScanner($this->app['router'], $this->app, new ValidationRulesParser(), config('postman-generator'));
        $endpoints = $scanner->scan();

        $this->assertCount(2, $endpoints);
        $endpointMap = [];
        foreach ($endpoints as $endpoint) {
            $endpointMap[$endpoint->name] = $endpoint;
        }

        $listEndpoint = $endpointMap['users.index'];
        $this->assertSame(['GET'], $listEndpoint->methods);
        $this->assertSame('List users', $listEndpoint->summary);
        $this->assertSame('Users', $listEndpoint->group);
        $this->assertSame('X-Request-ID', $listEndpoint->headers[0]->name);
        $this->assertSame(2, $listEndpoint->queryParams[0]->example);

        $formRequestEndpoint = $endpointMap['users.request'];
        $this->assertSame('users.request', $formRequestEndpoint->name);
        $this->assertSame('email', $formRequestEndpoint->bodyParams[0]->name);
        $this->assertSame('user@example.com', $formRequestEndpoint->bodyParams[0]->example);
        $this->assertSame('status', $formRequestEndpoint->bodyParams[2]->name);
        $this->assertSame('active', $formRequestEndpoint->bodyParams[2]->example);
    }
}
