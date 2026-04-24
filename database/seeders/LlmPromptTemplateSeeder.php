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

        LlmPromptTemplate::updateOrCreate(
            ['key' => 'coach_feedback_hint', 'version' => 1],
            [
                'agent_profile_id' => $agent->id,
                'status' => 'active',
                'template' => 'Give a short, friendly Grade 1 reading hint using only the provided sanitized context. Do not score or place the learner. Context: {{ context }}',
                'variables' => ['context'],
            ]
        );
    }
}
