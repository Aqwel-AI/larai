<?php

namespace AqwelAI\LarAI\Contracts;

/**
 * Simple vector store contract for RAG workflows.
 */
interface VectorStore
{
    /**
     * Upsert vector items with metadata.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function upsert(array $items): void;

    /**
     * Query similar items for a vector.
     *
     * @return array<int, array<string, mixed>>
     */
    public function query(array $vector, int $topK = 5): array;
}
