<?php

return [
    'enabled' => filter_var(env('READIRECT_AI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'base_url' => env('READIRECT_AI_BASE_URL', 'http://127.0.0.1:8001'),
    'api_token' => env('READIRECT_AI_API_TOKEN'),
    'timeout_seconds' => (int) env('READIRECT_AI_TIMEOUT_SECONDS', 60),

    'endpoints' => [
        'health' => env('READIRECT_AI_HEALTH_ENDPOINT', '/health'),
        'version' => env('READIRECT_AI_VERSION_ENDPOINT', '/version'),
        'analyze_audio' => env('READIRECT_AI_ANALYZE_AUDIO_ENDPOINT', '/analyze-audio'),
        'analyze_text' => env('READIRECT_AI_ANALYZE_TEXT_ENDPOINT', '/analyze-text'),
        'recommend_next' => env('READIRECT_AI_RECOMMEND_NEXT_ENDPOINT', '/recommend-next'),
        'content_item' => env('READIRECT_AI_CONTENT_ITEM_ENDPOINT', '/content-item'),
    ],

    'fallback' => [
        'use_existing_stt_if_ai_offline' => filter_var(env('READIRECT_AI_FALLBACK_TO_STT', true), FILTER_VALIDATE_BOOLEAN),
        'use_manual_transcript_if_available' => filter_var(env('READIRECT_AI_USE_MANUAL_TRANSCRIPT', true), FILTER_VALIDATE_BOOLEAN),
        'do_not_penalize_ai_failure' => true,
    ],

    'debug' => [
        'show_admin_debug' => filter_var(env('READIRECT_AI_ADMIN_DEBUG', true), FILTER_VALIDATE_BOOLEAN),
        'show_student_debug' => false,
        'enable_developer_assessment_reset' => filter_var(env('ENABLE_DEVELOPER_ASSESSMENT_RESET', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
