<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Nutandc\PostmanGenerator\Builders\OpenApiBuilder;
use Nutandc\PostmanGenerator\Builders\PostmanCollectionBuilder;
use Nutandc\PostmanGenerator\Contracts\EndpointScannerInterface;
use Nutandc\PostmanGenerator\Exceptions\GeneratorException;

final class GeneratorService
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly EndpointScannerInterface $scanner,
        private readonly PostmanCollectionBuilder $postmanBuilder,
        private readonly OpenApiBuilder $openApiBuilder,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function generate(): array
    {
        $config = (array) Config::get('postman-generator', []);
        $outputPath = (string) data_get($config, 'output.path', '');
        if ($outputPath === '') {
            throw new GeneratorException('Output path is not configured.');
        }

        $this->files->ensureDirectoryExists($outputPath, 0755, true);

        $endpoints = $this->scanner->scan();
        $results = [];

        if ((bool) data_get($config, 'output.postman.enabled', true)) {
            $collection = $this->postmanBuilder->build($config, $endpoints);
            $file = $this->writeJson($outputPath, (string) data_get($config, 'output.postman.filename', 'collection.json'), $collection);
            $results['postman'] = $file;
        }

        if ((bool) data_get($config, 'output.openapi.enabled', true)) {
            $schema = $this->openApiBuilder->build($config, $endpoints);
            $file = $this->writeJson($outputPath, (string) data_get($config, 'output.openapi.filename', 'openapi.json'), $schema);
            $results['openapi'] = $file;
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, string $filename, array $payload): string
    {
        if ($filename === '') {
            throw new GeneratorException('Output filename cannot be empty.');
        }

        $fullPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($filename, DIRECTORY_SEPARATOR);

        $this->files->put($fullPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $fullPath;
    }
}
