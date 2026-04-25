<?php

return [
    'provider' => env('STT_PROVIDER', env('READIRECT_STT_PROVIDER', 'mock')),
    'timeout_seconds' => (int) env('STT_TIMEOUT_SECONDS', env('READIRECT_STT_TIMEOUT', 30)),
    'sample_rate_hz' => (int) env('STT_SAMPLE_RATE_HZ', 16000),
    'language' => env('STT_LANGUAGE', 'en'),

    'mock' => [
        'transcript' => env('STT_MOCK_TRANSCRIPT', env('READIRECT_STT_MOCK_TRANSCRIPT')),
    ],

    'whisper_cpp' => [
        'enabled' => filter_var(env('STT_WHISPER_CPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'binary_path' => env('STT_WHISPER_CPP_BINARY_PATH', 'whisper-cli'),
        'model_path' => env('STT_WHISPER_CPP_MODEL_PATH'),
        'ffmpeg_path' => env('STT_FFMPEG_PATH', 'ffmpeg'),
        'convert_to_wav' => filter_var(env('STT_CONVERT_TO_WAV', false), FILTER_VALIDATE_BOOLEAN),
        'extra_args' => env('STT_WHISPER_CPP_EXTRA_ARGS', ''),
    ],
];
