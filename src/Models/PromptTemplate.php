<?php

namespace AqwelAI\LarAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prompt template registry model.
 */
class PromptTemplate extends Model
{
    protected $table = 'larai_prompts';

    protected $fillable = [
        'name',
        'version',
        'content',
        'tags',
        'is_active',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
    ];
}
