<?php

namespace AqwelAI\LarAI\Services;

use AqwelAI\LarAI\Models\PromptTemplate;

/**
 * Database-backed prompt registry with versioning.
 */
class PromptRegistry
{
    public function create(string $name, string $content, array $tags = []): PromptTemplate
    {
        $latest = PromptTemplate::where('name', $name)->max('version') ?? 0;

        return PromptTemplate::create([
            'name' => $name,
            'version' => $latest + 1,
            'content' => $content,
            'tags' => $tags,
            'is_active' => true,
        ]);
    }

    public function latest(string $name): ?PromptTemplate
    {
        return PromptTemplate::where('name', $name)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
    }

    public function render(string $name, array $vars = []): string
    {
        $template = $this->latest($name);

        if (!$template) {
            return '';
        }

        $content = $template->content;

        foreach ($vars as $key => $value) {
            $content = str_replace('{' . $key . '}', (string) $value, $content);
        }

        return $content;
    }
}
