<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Commands;

use Illuminate\Console\Command;
use Nutandc\PostmanGenerator\Services\GeneratorService;

final class PostmanGenerateCommand extends Command
{
    protected $signature = 'postman:generate {--format=postman,openapi : Comma-separated formats to generate}';

    protected $description = 'Generate Postman and OpenAPI documentation files.';

    public function handle(GeneratorService $service): int
    {
        $formats = array_filter(array_map('trim', explode(',', (string) $this->option('format'))));
        if ($formats !== []) {
            config(['postman-generator.output.postman.enabled' => in_array('postman', $formats, true)]);
            config(['postman-generator.output.openapi.enabled' => in_array('openapi', $formats, true)]);
        }

        $results = $service->generate();

        foreach ($results as $type => $file) {
            $this->info(strtoupper($type) . ' generated: ' . $file);
        }

        if ($results === []) {
            $this->warn('No output generated. Check your configuration.');
        }

        return self::SUCCESS;
    }
}
