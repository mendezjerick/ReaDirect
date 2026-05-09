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

    'ollama' => [
        'enabled' => filter_var(env('OLLAMA_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3:4b'),
        'fallback_model' => env('OLLAMA_FALLBACK_MODEL', 'phi4-mini'),
        'timeout_seconds' => (int) env('OLLAMA_TIMEOUT_SECONDS', 10),
        'fallback_to_scripted' => filter_var(env('OLLAMA_FALLBACK_TO_SCRIPTED', true), FILTER_VALIDATE_BOOLEAN),
        'debug' => filter_var(env('OLLAMA_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'agent_feedback' => [
        'miss_ciel_ollama_enabled' => filter_var(env('MISS_CIEL_OLLAMA_ENABLED', env('OLLAMA_ENABLED', false)), FILTER_VALIDATE_BOOLEAN),
    ],

    'tts' => [
        'enabled' => filter_var(env('TTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('TTS_PROVIDER', 'kokoro'),
        'base_url' => env('TTS_BASE_URL', 'http://127.0.0.1:8002'),
        'timeout_seconds' => (int) env('TTS_TIMEOUT_SECONDS', 10),
        'fallback_to_browser' => filter_var(env('TTS_FALLBACK_TO_BROWSER', true), FILTER_VALIDATE_BOOLEAN),
        'fallback_to_text' => filter_var(env('TTS_FALLBACK_TO_TEXT', true), FILTER_VALIDATE_BOOLEAN),
        'cache_enabled' => filter_var(env('TTS_CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'debug' => filter_var(env('TTS_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        'voices' => [
            'miss_vivian' => env('TTS_VOICE_VIVIAN', 'af_bella'),
            'miss_ciel' => env('TTS_VOICE_CIEL', 'af_heart'),
            'miss_estelle' => env('TTS_VOICE_ESTELLE', 'bf_isabella'),
        ],
        'speeds' => [
            'miss_vivian' => (float) env('TTS_SPEED_VIVIAN', 0.95),
            'miss_ciel' => (float) env('TTS_SPEED_CIEL', 1.00),
            'miss_estelle' => (float) env('TTS_SPEED_ESTELLE', 0.95),
        ],
    ],
];
