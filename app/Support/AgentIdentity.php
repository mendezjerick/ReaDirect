<?php

namespace App\Support;

class AgentIdentity
{
    public const MISS_VIVIAN = 'assessment';

    public const MISS_CIEL = 'coach_feedback';

    public const MISS_ESTELLE = 'evaluator_recommendation';

    public static function all(): array
    {
        return [
            self::MISS_VIVIAN => [
                'id' => self::MISS_VIVIAN,
                'display_name' => 'Miss Vivian',
                'role' => 'Assessment Guide',
                'behavior' => 'fixed_scripts_only',
                'llm_enabled' => false,
            ],
            self::MISS_CIEL => [
                'id' => self::MISS_CIEL,
                'display_name' => 'Miss Ciel',
                'role' => 'Reading Coach',
                'behavior' => 'scripted_fallback_optional_ollama',
                'llm_enabled' => (bool) (config('readirect.ollama.enabled') && config('readirect.agent_feedback.miss_ciel_ollama_enabled')),
            ],
            self::MISS_ESTELLE => [
                'id' => self::MISS_ESTELLE,
                'display_name' => 'Miss Estelle',
                'role' => 'Results Guide',
                'behavior' => 'fixed_scripts_only',
                'llm_enabled' => false,
            ],
        ];
    }

    public static function get(string $id): array
    {
        return self::all()[$id] ?? self::all()[self::MISS_VIVIAN];
    }

    public static function displayName(string $id): string
    {
        return self::get($id)['display_name'];
    }
}
