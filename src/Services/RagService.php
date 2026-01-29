<?php

namespace AqwelAI\LarAI\Services;

use AqwelAI\LarAI\Contracts\VectorStore;
use AqwelAI\LarAI\LarAI;
use Illuminate\Support\Str;

/**
 * Retrieval-augmented generation helpers.
 */
class RagService
{
    /**
     * Create a new RAG service instance.
     */
    public function __construct(protected LarAI $larai)
    {
    }

    /**
     * Chunk text into overlapping segments.
     *
     * @return array<int, string>
     */
    public function chunk(string $text, int $maxChars = 1000, int $overlap = 100): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($length, $start + $maxChars);
            $chunk = trim(substr($text, $start, $end - $start));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $start = $end - $overlap;

            if ($start < 0) {
                $start = 0;
            }
        }

        return $chunks;
    }

    /**
     * Index a document by chunking and storing embeddings.
     *
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function index(string $text, VectorStore $store, array $options = []): array
    {
        $chunks = $this->chunk(
            $text,
            (int) ($options['chunk_size'] ?? 1000),
            (int) ($options['chunk_overlap'] ?? 100)
        );

        if ($chunks === []) {
            return [];
        }

        $response = $this->larai->embeddings($chunks, $options);
        $embeddings = $response['embeddings'] ?? [];

        $items = [];

        foreach ($chunks as $index => $chunk) {
            $embedding = $embeddings[$index]['embedding'] ?? $embeddings[$index] ?? null;

            if (!is_array($embedding)) {
                continue;
            }

            $items[] = [
                'id' => (string) Str::uuid(),
                'embedding' => array_map('floatval', $embedding),
                'metadata' => [
                    'text' => $chunk,
                    'index' => $index,
                ],
            ];
        }

        $store->upsert($items);

        return $items;
    }

    /**
     * Query a vector store for relevant chunks.
     *
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, VectorStore $store, array $options = []): array
    {
        $response = $this->larai->embeddings($query, $options);
        $embeddings = $response['embeddings'] ?? [];
        $embedding = $embeddings[0]['embedding'] ?? $embeddings[0] ?? null;

        if (!is_array($embedding)) {
            return [];
        }

        $topK = (int) ($options['top_k'] ?? 5);

        return $store->query(array_map('floatval', $embedding), $topK);
    }
}
