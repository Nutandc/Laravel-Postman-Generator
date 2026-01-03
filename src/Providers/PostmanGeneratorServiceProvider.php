<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Nutandc\PostmanGenerator\Builders\OpenApiBuilder;
use Nutandc\PostmanGenerator\Builders\PostmanCollectionBuilder;
use Nutandc\PostmanGenerator\Builders\PostmanEnvironmentBuilder;
use Nutandc\PostmanGenerator\Commands\PostmanGenerateCommand;
use Nutandc\PostmanGenerator\Contracts\EndpointScannerInterface;
use Nutandc\PostmanGenerator\Helpers\MetadataDefinitionMapper;
use Nutandc\PostmanGenerator\Helpers\ValidationRulesParser;
use Nutandc\PostmanGenerator\Services\EndpointMetadataResolver;
use Nutandc\PostmanGenerator\Services\GeneratorService;
use Nutandc\PostmanGenerator\Services\RouteScanner;

final class PostmanGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/postman-generator.php', 'postman-generator');

        $this->app->singleton(MetadataDefinitionMapper::class);
        $this->app->singleton(EndpointScannerInterface::class, function ($app): EndpointScannerInterface {
            return new RouteScanner(
                $app->make(Router::class),
                $app->make(EndpointMetadataResolver::class),
                (array) $app['config']->get('postman-generator', []),
            );
        });

        $this->app->singleton(ValidationRulesParser::class);
        $this->app->singleton(EndpointMetadataResolver::class, function ($app): EndpointMetadataResolver {
            $config = (array) $app['config']->get('postman-generator', []);
            $providers = [];

            foreach ((array) data_get($config, 'metadata_providers', []) as $providerClass) {
                $providers[] = $app->make($providerClass, ['config' => $config]);
            }

            return new EndpointMetadataResolver($providers);
        });
        $this->app->singleton(PostmanCollectionBuilder::class);
        $this->app->singleton(PostmanEnvironmentBuilder::class);
        $this->app->singleton(OpenApiBuilder::class);

        $this->app->singleton(GeneratorService::class, function ($app): GeneratorService {
            return new GeneratorService(
                $app->make(Filesystem::class),
                $app->make(EndpointScannerInterface::class),
                $app->make(PostmanCollectionBuilder::class),
                $app->make(PostmanEnvironmentBuilder::class),
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
