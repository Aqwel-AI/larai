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
