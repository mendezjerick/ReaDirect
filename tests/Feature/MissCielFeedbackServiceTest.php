<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\Module;
use App\Models\School;
use App\Services\Agents\MissCielFeedbackService;
use App\Support\AgentIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MissCielFeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_identity_reports_ciel_as_deterministic(): void
    {
        $identity = AgentIdentity::get(AgentIdentity::MISS_CIEL);

        $this->assertSame('Miss Ciel', $identity['display_name']);
        $this->assertSame('deterministic_policy_and_approved_dialogue', $identity['behavior']);
        $this->assertFalse($identity['llm_enabled']);
    }

    public function test_miss_ciel_feedback_is_scripted_and_never_calls_an_llm(): void
    {
        Http::fake();

        $feedback = app(MissCielFeedbackService::class)->feedback([
            'is_correct' => true,
            'recommended_action' => 'continue',
        ]);

        $this->assertSame('Miss Ciel', $feedback['display_name']);
        $this->assertSame('deterministic_script', $feedback['source']);
        $this->assertFalse($feedback['fallback_used']);
        $this->assertStringContainsString('Great job', $feedback['message']);
        Http::assertNothingSent();
    }

    public function test_miss_ciel_feedback_does_not_change_official_learner_state(): void
    {
        $school = School::create(['name' => 'Miss Ciel Safety School']);
        $module = Module::create([
            'key' => 'module_2',
            'title' => 'Word Reading',
            'description' => 'Read words.',
            'sequence' => 2,
            'is_active' => true,
        ]);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('CIEL-', false),
            'first_name' => 'Safe',
            'grade_level' => 'Grade 1',
            'current_stage' => 'module_practice_in_progress',
            'current_module_id' => $module->id,
        ]);

        app(MissCielFeedbackService::class)->feedback([
            'learner_id' => $learner->id,
            'is_correct' => false,
            'error_type' => 'final_sound_error',
        ]);

        $learner->refresh();
        $this->assertSame('module_practice_in_progress', $learner->current_stage);
        $this->assertSame($module->id, $learner->current_module_id);
    }
}
