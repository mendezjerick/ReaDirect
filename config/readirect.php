<?php

return [
    'speech_to_text' => [
        'provider' => env('READIRECT_STT_PROVIDER', 'mock'),
        'timeout_seconds' => (int) env('READIRECT_STT_TIMEOUT', 30),
        'mock_transcript' => env('READIRECT_STT_MOCK_TRANSCRIPT'),
    ],
];
