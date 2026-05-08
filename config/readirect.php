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

    'developer_qa' => [
        'enabled' => filter_var(env('READIRECT_DEVELOPER_QA_MODE', false), FILTER_VALIDATE_BOOLEAN),
        'assessment_debug' => filter_var(env('READIRECT_ASSESSMENT_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        'manual_fallback' => filter_var(env('READIRECT_LEARNER_MANUAL_FALLBACK', false), FILTER_VALIDATE_BOOLEAN),
        'jump_controls' => filter_var(env('READIRECT_QA_ALLOW_JUMP_CONTROLS', false), FILTER_VALIDATE_BOOLEAN),
        'flow_bypass' => filter_var(env('READIRECT_QA_ALLOW_FLOW_BYPASS', false), FILTER_VALIDATE_BOOLEAN),
        'auto_transcribe_on_stop' => filter_var(env('READIRECT_QA_AUTO_TRANSCRIBE_ON_STOP', false), FILTER_VALIDATE_BOOLEAN),
        'show_ai_debug' => filter_var(env('READIRECT_QA_SHOW_AI_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        'force_learner_stage' => filter_var(env('READIRECT_QA_FORCE_LEARNER_STAGE', false), FILTER_VALIDATE_BOOLEAN),
        'reset_learner_flow' => filter_var(env('READIRECT_QA_RESET_LEARNER_FLOW', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
