<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Nutandc\PostmanGenerator\Builders\OpenApiBuilder;
use Nutandc\PostmanGenerator\Builders\PostmanCollectionBuilder;
use Nutandc\PostmanGenerator\Commands\PostmanGenerateCommand;
use Nutandc\PostmanGenerator\Contracts\EndpointScannerInterface;
use Nutandc\PostmanGenerator\Helpers\ValidationRulesParser;
use Nutandc\PostmanGenerator\Services\GeneratorService;
use Nutandc\PostmanGenerator\Services\RouteScanner;

final class PostmanGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/postman-generator.php', 'postman-generator');

        $this->app->singleton(EndpointScannerInterface::class, function ($app): EndpointScannerInterface {
            return new RouteScanner(
                $app->make(Router::class),
                $app,
                $app->make(ValidationRulesParser::class),
                (array) $app['config']->get('postman-generator', []),
            );
        });

        $this->app->singleton(ValidationRulesParser::class);
        $this->app->singleton(PostmanCollectionBuilder::class);
        $this->app->singleton(OpenApiBuilder::class);

        $this->app->singleton(GeneratorService::class, function ($app): GeneratorService {
            return new GeneratorService(
                $app->make(Filesystem::class),
                $app->make(EndpointScannerInterface::class),
                $app->make(PostmanCollectionBuilder::class),
                $app->make(OpenApiBuilder::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/postman-generator.php' => config_path('postman-generator.php'),
        ], 'postman-generator-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PostmanGenerateCommand::class,
            ]);
        }
    }
}
