<?php

namespace AqwelAI\LarAI\VectorStores;

use AqwelAI\LarAI\Contracts\VectorStore;

/**
 * Simple in-memory vector store for local development/testing.
 */
class InMemoryVectorStore implements VectorStore
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $items = [];

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function upsert(array $items): void
    {
        foreach ($items as $item) {
            if (!isset($item['id'], $item['embedding'])) {
                continue;
            }

            $this->items[$item['id']] = $item;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function query(array $vector, int $topK = 5): array
    {
        $results = [];

        foreach ($this->items as $item) {
            $embedding = $item['embedding'] ?? null;

            if (!is_array($embedding)) {
                continue;
            }

            $results[] = [
                'id' => $item['id'],
                'score' => $this->cosineSimilarity($vector, $embedding),
                'metadata' => $item['metadata'] ?? [],
            ];
        }

        usort($results, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topK);
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
