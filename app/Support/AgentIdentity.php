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
                'behavior' => 'deterministic_policy_and_approved_dialogue',
                'llm_enabled' => false,
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
