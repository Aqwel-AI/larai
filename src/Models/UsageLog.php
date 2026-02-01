<?php

namespace AqwelAI\LarAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted usage logs.
 */
class UsageLog extends Model
{
    protected $table = 'larai_usage_logs';

    protected $fillable = [
        'provider',
        'method',
        'usage',
        'meta',
    ];

    protected $casts = [
        'usage' => 'array',
        'meta' => 'array',
    ];
}
