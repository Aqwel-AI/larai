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

    'prompts' => [
        'summarize' => "Summarize the following text:\n\n{text}",
        'chat_system' => 'You are a helpful assistant.',
    ],

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
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
