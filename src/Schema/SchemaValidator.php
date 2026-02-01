<?php

namespace AqwelAI\LarAI\Schema;

use AqwelAI\LarAI\Exceptions\LarAIException;

/**
 * Minimal JSON schema validator for objects/arrays/primitives.
 */
class SchemaValidator
{
    /**
     * @param array<string, mixed> $schema
     * @param mixed $value
     */
    public function validate(array $schema, mixed $value): void
    {
        $type = $schema['type'] ?? null;

        if ($type === 'object') {
            if (!is_array($value)) {
                throw new LarAIException('Schema validation failed: expected object.');
            }

            $required = $schema['required'] ?? [];
            foreach ($required as $key) {
                if (!array_key_exists($key, $value)) {
                    throw new LarAIException("Schema validation failed: missing [$key].");
                }
            }

            $properties = $schema['properties'] ?? [];
            foreach ($properties as $key => $propertySchema) {
                if (array_key_exists($key, $value) && is_array($propertySchema)) {
                    $this->validate($propertySchema, $value[$key]);
                }
            }

            return;
        }

        if ($type === 'array') {
            if (!is_array($value)) {
                throw new LarAIException('Schema validation failed: expected array.');
            }

            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $item) {
                    $this->validate($schema['items'], $item);
                }
            }

            return;
        }

        if ($type === 'string' && !is_string($value)) {
            throw new LarAIException('Schema validation failed: expected string.');
        }

        if ($type === 'number' && !is_numeric($value)) {
            throw new LarAIException('Schema validation failed: expected number.');
        }

        if ($type === 'boolean' && !is_bool($value)) {
            throw new LarAIException('Schema validation failed: expected boolean.');
        }
    }
}
