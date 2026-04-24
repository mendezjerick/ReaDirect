<?php

namespace Database\Seeders;

use App\Models\AgentProfile;
use Illuminate\Database\Seeder;

class AgentProfileSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'key' => AgentProfile::ASSESSMENT,
                'name' => 'Assessment Agent',
                'agent_type' => 'assessment',
                'purpose' => 'Runs diagnostic and final reassessment using fixed scripts and standardized assessment behavior.',
                'uses_llm' => false,
                'guardrails' => ['fixed_scripts_only', 'no_official_scoring_generation'],
            ],
            [
                'key' => AgentProfile::COACH_FEEDBACK,
                'name' => 'Coach + Feedback Agent',
                'agent_type' => 'coach_feedback',
                'purpose' => 'Guides module practice, encouragement, hints, and feedback through sanitized prompt templates.',
                'uses_llm' => true,
                'guardrails' => ['no_official_scoring', 'sanitized_context_only', 'child_safe_feedback'],
            ],
            [
                'key' => AgentProfile::EVALUATOR_RECOMMENDATION,
                'name' => 'Evaluator / Recommendation Agent',
                'agent_type' => 'evaluator_recommendation',
                'purpose' => 'Applies deterministic scoring, module placement, and mastery decision rules for auditability.',
                'uses_llm' => false,
                'guardrails' => ['rule_based_only', 'store_rule_applied', 'store_input_scores'],
            ],
        ];

        foreach ($agents as $agent) {
            AgentProfile::updateOrCreate(['key' => $agent['key']], $agent + ['is_fixed' => true]);
        }
    }
}
