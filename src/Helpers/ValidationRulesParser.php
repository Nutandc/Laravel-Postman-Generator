<?php

declare(strict_types=1);

namespace Nutandc\PostmanGenerator\Helpers;

use Nutandc\PostmanGenerator\ValueObjects\Parameter;

final class ValidationRulesParser
{
    /**
     * @param array<string, mixed> $rules
     * @return Parameter[]
     */
    public function parametersFromRules(array $rules): array
    {
        $params = [];
        foreach ($rules as $name => $definition) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $ruleList = $this->normalizeRules($definition);
            if ($ruleList === []) {
                continue;
            }

            $required = $this->isRequired($ruleList);
            $type = $this->resolveType($ruleList);
            $example = $this->resolveExample($ruleList, $type);

            $params[] = new Parameter(
                name: $name,
                type: $type,
                required: $required,
                description: null,
                example: $example,
            );
        }

        return $params;
    }

    /**
     * @return string[]
     */
    private function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return array_values(array_filter(array_map('trim', explode('|', $rules))));
        }

        if (is_array($rules)) {
            $flattened = [];
            foreach ($rules as $rule) {
                $flattened = array_merge($flattened, $this->normalizeRules($rule));
            }

            return $flattened;
        }

        if (is_object($rules) && method_exists($rules, '__toString')) {
            return $this->normalizeRules((string) $rules);
        }

        return [];
    }

    /**
     * @param string[] $rules
     */
    private function isRequired(array $rules): bool
    {
        $required = false;
        $nullable = false;

        foreach ($rules as $rule) {
            $name = strtolower($this->ruleName($rule));

            if ($name === 'nullable') {
                $nullable = true;
                continue;
            }

            if (str_starts_with($name, 'required') || $name === 'present') {
                $required = true;
            }
        }

        return $required && ! $nullable;
    }

    /**
     * @param string[] $rules
     */
    private function resolveType(array $rules): string
    {
        foreach ($rules as $rule) {
            $name = strtolower($this->ruleName($rule));

            if (in_array($name, ['integer', 'int'], true)) {
                return 'integer';
            }

            if (in_array($name, ['numeric', 'decimal', 'float', 'double'], true)) {
                return 'float';
            }

            if (in_array($name, ['boolean', 'bool'], true)) {
                return 'boolean';
            }

            if ($name === 'array') {
                return 'array';
            }
        }

        return 'string';
    }

    /**
     * @param string[] $rules
     */
    private function resolveExample(array $rules, string $type): mixed
    {
        $inValue = $this->resolveInValue($rules);
        if ($inValue !== null) {
            return $inValue;
        }

        if ($this->hasRule($rules, 'email')) {
            return 'user@example.com';
        }

        if ($this->hasRule($rules, 'uuid')) {
            return '00000000-0000-0000-0000-000000000000';
        }

        $dateFormat = $this->resolveDateFormat($rules);
        if ($dateFormat !== null) {
            return $this->formatDateExample($dateFormat);
        }

        if ($this->hasRule($rules, 'date') || $this->hasRule($rules, 'datetime')) {
            return '2024-01-01';
        }

        if ($this->hasRule($rules, 'file') || $this->hasRule($rules, 'image')) {
            return 'file.txt';
        }

        return ExampleValueResolver::valueForType($type);
    }

    /**
     * @param string[] $rules
     */
    private function resolveInValue(array $rules): ?string
    {
        foreach ($rules as $rule) {
            $name = strtolower($this->ruleName($rule));
            if ($name !== 'in') {
                continue;
            }

            $parts = explode(':', $rule, 2);
            if (! isset($parts[1])) {
                continue;
            }

            $values = array_values(array_filter(array_map('trim', explode(',', $parts[1]))));
            if ($values === []) {
                continue;
            }

            return $values[0];
        }

        return null;
    }

    /**
     * @param string[] $rules
     */
    private function resolveDateFormat(array $rules): ?string
    {
        foreach ($rules as $rule) {
            $name = strtolower($this->ruleName($rule));
            if ($name !== 'date_format') {
                continue;
            }

            $parts = explode(':', $rule, 2);
            if (! isset($parts[1])) {
                continue;
            }

            return trim($parts[1]);
        }

        return null;
    }

    private function formatDateExample(string $format): string
    {
        return match ($format) {
            'Y-m-d' => '2024-01-01',
            'Y-m-d H:i:s' => '2024-01-01 10:00:00',
            'c' => '2024-01-01T10:00:00+00:00',
            default => '2024-01-01',
        };
    }

    /**
     * @param string[] $rules
     */
    private function hasRule(array $rules, string $needle): bool
    {
        foreach ($rules as $rule) {
            if (strtolower($this->ruleName($rule)) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function ruleName(string $rule): string
    {
        return explode(':', $rule, 2)[0];
    }
}
