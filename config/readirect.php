<?php

return [
    'speech_to_text' => [
        'provider' => env('STT_PROVIDER', env('READIRECT_STT_PROVIDER', 'mock')),
        'timeout_seconds' => (int) env('STT_TIMEOUT_SECONDS', env('READIRECT_STT_TIMEOUT', 30)),
        'mock_transcript' => env('STT_MOCK_TRANSCRIPT', env('READIRECT_STT_MOCK_TRANSCRIPT')),
    ],

    'openai' => [
        'enabled' => filter_var(env('OPENAI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 120),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.4),
    ],
];
