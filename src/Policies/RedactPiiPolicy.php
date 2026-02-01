<?php

namespace AqwelAI\LarAI\Policies;

/**
 * Basic PII redaction for emails and phone numbers.
 */
class RedactPiiPolicy implements Policy
{
    public function apply(string $method, array $args, array $options): array
    {
        $redactor = function (string $value): string {
            $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $value);
            $value = preg_replace('/\+?\d[\d\s().-]{7,}\d/', '[redacted-phone]', $value);

            return $value ?? '';
        };

        $args = $this->walk($args, $redactor);

        return [
            'args' => $args,
            'options' => $options,
        ];
    }

    /**
     * @param array<int, mixed> $data
     * @return array<int, mixed>
     */
    protected function walk(array $data, callable $redactor): array
    {
        foreach ($data as $index => $value) {
            if (is_string($value)) {
                $data[$index] = $redactor($value);
                continue;
            }

            if (is_array($value)) {
                $data[$index] = $this->walk($value, $redactor);
            }
        }

        return $data;
    }
}
