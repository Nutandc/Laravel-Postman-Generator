<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Helpers;

use Nutandc\PostmanGenerator\ValueObjects\Header;
use Nutandc\PostmanGenerator\ValueObjects\Parameter;
use Nutandc\PostmanGenerator\ValueObjects\ResponseDefinition;

final class MetadataDefinitionMapper
{
    /**
     * @param array<int, array{name?: string, type?: string, required?: bool, description?: string, example?: mixed}> $definitions
     * @return Parameter[]
     */
    public function parameters(array $definitions): array
    {
        $params = [];
        foreach ($definitions as $definition) {
            if (! isset($definition['name'], $definition['type'], $definition['required'])) {
                continue;
            }

            $params[] = new Parameter(
                name: (string) $definition['name'],
                type: (string) $definition['type'],
                required: (bool) $definition['required'],
                description: $definition['description'] ?? null,
                example: $definition['example'] ?? null,
            );
        }

        return $params;
    }

    /**
     * @param array<int, array{name?: string, value?: string, required?: bool, description?: string}> $definitions
     * @return Header[]
     */
    public function headers(array $definitions): array
    {
        $headers = [];
        foreach ($definitions as $definition) {
            if (! isset($definition['name'], $definition['value'])) {
                continue;
            }

            $headers[] = new Header(
                name: (string) $definition['name'],
                value: (string) $definition['value'],
                required: (bool) ($definition['required'] ?? false),
                description: $definition['description'] ?? null,
            );
        }

        return $headers;
    }

    /**
     * @param array<int, array{status?: int, description?: string, body?: mixed, example?: mixed, headers?: array<int, array{name?: string, value?: string, required?: bool, description?: string}>, media_type?: string}> $definitions
     * @return ResponseDefinition[]
     */
    public function responses(array $definitions): array
    {
        $responses = [];
        foreach ($definitions as $definition) {
            if (! isset($definition['status'])) {
                continue;
            }

            $body = $definition['body'] ?? $definition['example'] ?? null;
            $headers = $this->headers($definition['headers'] ?? []);
            $responses[] = new ResponseDefinition(
                status: (int) $definition['status'],
                description: (string) ($definition['description'] ?? ''),
                headers: $headers,
                body: $body,
                mediaType: $definition['media_type'] ?? 'application/json',
            );
        }

        return $responses;
    }
}
