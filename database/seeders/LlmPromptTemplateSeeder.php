<?php

namespace Database\Seeders;

use App\Models\AgentProfile;
use App\Models\LlmPromptTemplate;
use Illuminate\Database\Seeder;

class LlmPromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $agent = AgentProfile::where('key', AgentProfile::COACH_FEEDBACK)->firstOrFail();

        $systemPrompt = 'You are the Miss Ciel for ReaDirect, a Grade 1 oral reading practice system. Speak kindly and simply to a young learner. Use short sentences. Encourage effort. Do not shame the learner. Do not mention scores unless provided for display. Do not diagnose speech, health, or learning conditions. Do not change official scoring or module decisions. Only explain the given feedback context in child-friendly words.';

        foreach ([
            'coach_feedback_correct',
            'coach_feedback_incorrect',
            'coach_feedback_retry',
            'coach_module_intro',
            'coach_module_complete',
            'coach_encouragement',
        ] as $key) {
            LlmPromptTemplate::updateOrCreate(
                ['key' => $key, 'version' => 1],
                [
                    'agent_profile_id' => $agent->id,
                    'status' => 'active',
                    'template' => $systemPrompt,
                    'variables' => [
                        'module_key',
                        'activity_type',
                        'expected_answer',
                        'learner_response',
                        'is_correct',
                        'error_type',
                        'recommended_action',
                        'template_feedback',
                        'retry_instruction',
                    ],
                ]
            );
        }

        LlmPromptTemplate::updateOrCreate(
            ['key' => 'agent_answer_commentary', 'version' => 1],
            [
                'agent_profile_id' => $agent->id,
                'status' => 'active',
                'template' => 'You are a ReaDirect agent speaking to a Grade 1 learner. Your job is to respond kindly after the learner gives an answer. You must use only the provided result context. You do not decide scores or correctness. You do not change the system decision. If the mode is assessment_neutral, do not give hints, corrections, closeness, or correct answers. If the mode is module_coaching, you may explain what was close, what to try next, and encourage retry. Keep the message short, friendly, and appropriate for a young learner. Do not shame the learner. Do not diagnose conditions. Do not mention internal scoring rules.',
                'variables' => [
                    'mode',
                    'agent_type',
                    'activity_type',
                    'expected_answer',
                    'learner_answer',
                    'is_correct',
                    'score',
                    'error_type',
                    'similarity_label',
                    'recommended_action',
                    'template_feedback',
                ],
            ]
        );
    }
}
