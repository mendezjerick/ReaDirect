<?php

namespace Database\Seeders;

use App\Models\AgentProfile;
use App\Support\AgentIdentity;
use Illuminate\Database\Seeder;

class AgentProfileSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'key' => AgentProfile::ASSESSMENT,
                'name' => AgentIdentity::displayName(AgentIdentity::MISS_VIVIAN),
                'agent_type' => 'assessment',
                'purpose' => 'Miss Vivian guides diagnostic and final reassessment using fixed scripts only.',
                'uses_llm' => false,
                'guardrails' => ['fixed_scripts_only', 'no_official_scoring_generation'],
            ],
            [
                'key' => AgentProfile::COACH_FEEDBACK,
                'name' => AgentIdentity::displayName(AgentIdentity::MISS_CIEL),
                'agent_type' => 'coach_feedback',
                'purpose' => 'Miss Ciel coaches module practice with scripted fallback and optional local Ollama feedback.',
                'uses_llm' => true,
                'guardrails' => ['no_official_scoring', 'no_progression_control', 'sanitized_context_only', 'child_safe_feedback'],
            ],
            [
                'key' => AgentProfile::EVALUATOR_RECOMMENDATION,
                'name' => AgentIdentity::displayName(AgentIdentity::MISS_ESTELLE),
                'agent_type' => 'evaluator_recommendation',
                'purpose' => 'Miss Estelle explains already-computed results using fixed scripts only.',
                'uses_llm' => false,
                'guardrails' => ['fixed_scripts_only', 'explain_computed_results_only', 'no_official_scoring_generation'],
            ],
        ];

        foreach ($agents as $agent) {
            AgentProfile::updateOrCreate(['key' => $agent['key']], $agent + ['is_fixed' => true]);
        }
    }
}
