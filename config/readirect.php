<?php

return [
    'ciel' => [
        'decision_mode' => 'deterministic',
        'spec_path' => env('REA_CIEL_SPEC_PATH', base_path('../ReaDirect-IA')),
        'service_enabled' => filter_var(env('CIEL_AGENT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('CIEL_AGENT_BASE_URL', 'http://127.0.0.1:8003'),
        'decide_endpoint' => env('CIEL_AGENT_DECIDE_ENDPOINT', '/ia/ciel/decide'),
        'status_endpoint' => env('CIEL_AGENT_STATUS_ENDPOINT', '/ia/ciel/status'),
        'connect_timeout_seconds' => (int) env('CIEL_AGENT_CONNECT_TIMEOUT_SECONDS', 1),
        'timeout_seconds' => (int) env('CIEL_AGENT_TIMEOUT_SECONDS', 3),
    ],

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

    'tts' => [
        'enabled' => filter_var(env('TTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('TTS_PROVIDER', 'kokoro'),
        'base_url' => env('TTS_BASE_URL', 'http://127.0.0.1:8002'),
        'timeout_seconds' => (int) env('TTS_TIMEOUT_SECONDS', 10),
        'fallback_to_text' => filter_var(env('TTS_FALLBACK_TO_TEXT', true), FILTER_VALIDATE_BOOLEAN),
        'cache_enabled' => filter_var(env('TTS_CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'debug' => filter_var(env('TTS_DEBUG_LOGGING', env('TTS_DEBUG', false)), FILTER_VALIDATE_BOOLEAN),
        'agent_profiles_enabled' => filter_var(env('TTS_AGENT_PROFILES_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'voices' => [
            'miss_vivian' => 'af_bella',
            'miss_ciel' => 'af_heart',
            'miss_estelle' => str_contains(strtolower((string) env('TTS_AGENT_VOICE_ESTELLE', env('TTS_VOICE_ESTELLE', 'bf_isabella'))), 'isabella')
                ? env('TTS_AGENT_VOICE_ESTELLE', env('TTS_VOICE_ESTELLE', 'bf_isabella'))
                : 'bf_isabella',
        ],
        'speeds' => [
            'miss_vivian' => (float) env('TTS_AGENT_SPEED_VIVIAN', 0.97),
            'miss_ciel' => (float) env('TTS_AGENT_SPEED_CIEL', 0.94),
            'miss_estelle' => (float) env('TTS_AGENT_SPEED_ESTELLE', 0.93),
        ],
        'speed_ranges' => [
            'miss_vivian' => ['min' => 0.95, 'max' => 1.00],
            'miss_ciel' => ['min' => 0.92, 'max' => 0.96],
            'miss_estelle' => ['min' => 0.90, 'max' => 0.95],
        ],
        'humanization' => [
            'cache_version' => env('TTS_HUMANIZATION_CACHE_VERSION', 'humanized-v1'),
            'text_humanizer_enabled' => filter_var(env('TTS_TEXT_HUMANIZER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'text_humanizer_mode' => env('TTS_TEXT_HUMANIZER_MODE', 'friendly'),
            'text_humanizer_variation_enabled' => filter_var(env('TTS_TEXT_HUMANIZER_VARIATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'text_humanizer_logging' => filter_var(env('TTS_TEXT_HUMANIZER_LOGGING', true), FILTER_VALIDATE_BOOLEAN),
            'delivery_control_enabled' => filter_var(env('TTS_DELIVERY_CONTROL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'safe_chunking_enabled' => filter_var(env('TTS_SAFE_CHUNKING_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'min_friendly_tokens' => (int) env('TTS_MIN_FRIENDLY_TOKENS', 12),
            'max_coaching_sentences' => (int) env('TTS_MAX_COACHING_SENTENCES', 3),
            'audio_humanizer_enabled' => filter_var(env('TTS_HUMANIZER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'audio_normalize_enabled' => filter_var(env('TTS_AUDIO_NORMALIZE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'audio_fade_enabled' => filter_var(env('TTS_AUDIO_FADE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'pause_control_enabled' => filter_var(env('TTS_PAUSE_CONTROL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'breaths_enabled' => filter_var(env('TTS_BREATHS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'breaths_volume' => (float) env('TTS_BREATHS_VOLUME', 0.08),
            'breaths_min_text_length' => (int) env('TTS_BREATHS_MIN_TEXT_LENGTH', 80),
        ],
    ],
];
