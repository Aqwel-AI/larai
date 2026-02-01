<?php

return [
    'default' => env('LARAI_PROVIDER', 'openai'),

    'timeout' => env('LARAI_TIMEOUT', 60),

    'logging' => [
        'enabled' => env('LARAI_LOGGING', true),
        'channel' => env('LARAI_LOG_CHANNEL', null),
    ],

    'queue' => [
        'enabled' => env('LARAI_QUEUE', false),
        'connection' => env('LARAI_QUEUE_CONNECTION', null),
        'queue' => env('LARAI_QUEUE_NAME', null),
        'rate_limits' => [
            'enabled' => env('LARAI_QUEUE_RATE_LIMITS', false),
            'providers' => [
                'openai' => [
                    'per_minute' => env('LARAI_QUEUE_OPENAI_PER_MINUTE', 0),
                ],
            ],
        ],
    ],

    'retry' => [
        'enabled' => env('LARAI_RETRY', true),
        'times' => env('LARAI_RETRY_TIMES', 3),
        'sleep' => env('LARAI_RETRY_SLEEP', 200),
        'max_sleep' => env('LARAI_RETRY_MAX_SLEEP', 2000),
        'jitter' => env('LARAI_RETRY_JITTER', true),
        'statuses' => [429, 500, 502, 503, 504],
    ],

    'cache' => [
        'enabled' => env('LARAI_CACHE', false),
        'ttl' => env('LARAI_CACHE_TTL', 300),
        'store' => env('LARAI_CACHE_STORE', null),
        'prefix' => env('LARAI_CACHE_PREFIX', 'larai:'),
    ],

    'usage' => [
        'events' => env('LARAI_USAGE_EVENTS', true),
        'include_response' => env('LARAI_USAGE_INCLUDE_RESPONSE', false),
        'include_options' => env('LARAI_USAGE_INCLUDE_OPTIONS', false),
    ],

    'hooks' => [
        'enabled' => env('LARAI_HOOKS', true),
    ],

    'dto' => [
        'enabled' => env('LARAI_DTO', false),
    ],

    'observability' => [
        'enabled' => env('LARAI_OBSERVABILITY', true),
    ],

    'middlewares' => [
        \AqwelAI\LarAI\Middleware\TraceIdMiddleware::class,
        \AqwelAI\LarAI\Middleware\RedactMiddleware::class,
    ],

    'policies' => [
        \AqwelAI\LarAI\Policies\RedactPiiPolicy::class,
    ],

    'policies_denylist' => [
        // 'secret',
    ],

    'fallback' => [
        'enabled' => env('LARAI_FALLBACK', true),
        'providers' => [
            'openai',
        ],
    ],

    'routing' => [
        'enabled' => env('LARAI_ROUTING', false),
        'strategy' => env('LARAI_ROUTING_STRATEGY', 'cost'),
        'providers' => [
            'openai' => [
                'cost' => 2,
                'latency' => 2,
            ],
            'llama' => [
                'cost' => 1,
                'latency' => 3,
            ],
        ],
    ],

    'dashboard' => [
        'enabled' => env('LARAI_DASHBOARD', false),
        'path' => env('LARAI_DASHBOARD_PATH', 'larai'),
        'middleware' => ['web'],
        'store_usage' => env('LARAI_DASHBOARD_STORE_USAGE', false),
    ],

    'prompts' => [
        'summarize' => "Summarize the following text:\n\n{text}",
        'chat_system' => 'You are a helpful assistant.',
    ],

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o-mini'),
            'transcribe_model' => env('OPENAI_TRANSCRIBE_MODEL', 'whisper-1'),
            'speech_model' => env('OPENAI_SPEECH_MODEL', 'gpt-4o-mini-tts'),
            'voice' => env('OPENAI_SPEECH_VOICE', 'alloy'),
            'speech_format' => env('OPENAI_SPEECH_FORMAT', 'mp3'),
        ],
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20240620'),
            'anthropic_version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
        'llama' => [
            'api_key' => env('LLAMA_API_KEY'),
            'base_url' => env('LLAMA_BASE_URL', 'https://api.together.xyz/v1'),
            'model' => env('LLAMA_MODEL', 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo'),
        ],
    ],
];
