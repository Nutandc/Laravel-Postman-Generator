<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Builders;

final class PostmanEnvironmentBuilder
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, array<string, mixed>>
     */
    public function buildAll(array $config): array
    {
        $definitions = (array) data_get($config, 'postman.environments', []);
        if ($definitions === []) {
            $defaultName = (string) data_get($config, 'postman.environment.name', 'local');
            $variables = (array) data_get($config, 'postman.variables', []);
            if ($variables !== []) {
                $definitions = [$defaultName => $variables];
            }
        }

        $environments = [];
        foreach ($definitions as $name => $values) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $environments[$name] = $this->buildEnvironment($name, (array) $values);
        }

        return $environments;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function buildEnvironment(string $name, array $values): array
    {
        $vars = [];
        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $vars[] = [
                'key' => $key,
                'value' => is_scalar($value) ? (string) $value : '',
                'enabled' => true,
            ];
        }

        return [
            'name' => $name,
            'values' => $vars,
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => gmdate('c'),
            '_postman_exported_using' => 'Nutandc Laravel Postman Generator',
        ];
    }
}
