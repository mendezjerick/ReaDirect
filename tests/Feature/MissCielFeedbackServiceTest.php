<?php

namespace Tests\Feature;

use App\Models\AgentProfile;
use App\Models\Learner;
use App\Models\Module;
use App\Models\School;
use App\Services\Agents\MissCielFeedbackService;
use App\Support\AgentIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MissCielFeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_identity_uses_final_learner_facing_names(): void
    {
        $this->assertSame('Miss Vivian', AgentIdentity::displayName(AgentIdentity::MISS_VIVIAN));
        $this->assertSame('Miss Ciel', AgentIdentity::displayName(AgentIdentity::MISS_CIEL));
        $this->assertSame('Miss Estelle', AgentIdentity::displayName(AgentIdentity::MISS_ESTELLE));
        $this->assertFalse(AgentIdentity::get(AgentIdentity::MISS_VIVIAN)['llm_enabled']);
        $this->assertFalse(AgentIdentity::get(AgentIdentity::MISS_ESTELLE)['llm_enabled']);
    }

    public function test_miss_ciel_uses_scripted_fallback_when_ollama_disabled(): void
    {
        $this->seedAgent();
        config()->set('readirect.ollama.enabled', false);
        Http::fake();

        $feedback = app(MissCielFeedbackService::class)->feedback($this->context(['is_correct' => true]));

        $this->assertSame('Miss Ciel', $feedback['display_name']);
        $this->assertSame('scripted', $feedback['source']);
        $this->assertTrue($feedback['fallback_used']);
        $this->assertStringContainsString('Great job', $feedback['message']);
        Http::assertNothingSent();
    }

    public function test_miss_ciel_accepts_safe_short_ollama_output(): void
    {
        $this->seedAgent();
        $this->enableOllama();
        Http::fake(['127.0.0.1:11434/api/generate' => Http::response(['response' => 'Good try! Let us read it slowly.'])]);

        $feedback = app(MissCielFeedbackService::class)->feedback($this->context());

        $this->assertSame('ollama', $feedback['source']);
        $this->assertFalse($feedback['fallback_used']);
        $this->assertSame('Good try! Let us read it slowly.', $feedback['message']);
    }

    public function test_miss_ciel_falls_back_on_connection_invalid_empty_long_and_unsafe_outputs(): void
    {
        $this->seedAgent();
        $this->enableOllama();

        foreach ([
            fn () => Http::fake(fn () => throw new ConnectionException('connection refused')),
            fn () => Http::fake(['*' => Http::response('not json', 200)]),
            fn () => Http::fake(['*' => Http::response(['response' => ''], 200)]),
            fn () => Http::fake(['*' => Http::response(['response' => str_repeat('practice ', 40)], 200)]),
            fn () => Http::fake(['*' => Http::response(['response' => 'The ASR transcript model says you failed.'], 200)]),
            fn () => Http::fake(['*' => Http::response(['error' => 'model not found'], 500)]),
        ] as $fake) {
            $fake();
            $feedback = app(MissCielFeedbackService::class)->feedback($this->context());

            $this->assertSame('scripted', $feedback['source']);
            $this->assertTrue($feedback['fallback_used']);
            $this->assertStringNotContainsString('ASR', $feedback['message']);
            $this->assertStringNotContainsString('failed', strtolower($feedback['message']));
        }
    }

    public function test_miss_ciel_feedback_does_not_change_official_learner_state(): void
    {
        $this->seedAgent();
        $this->enableOllama();
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

        Http::fake(['*' => Http::response(['response' => 'Great effort! Keep practicing.'], 200)]);

        app(MissCielFeedbackService::class)->feedback($this->context(['learner_id' => $learner->id]));

        $learner->refresh();
        $this->assertSame('module_practice_in_progress', $learner->current_stage);
        $this->assertSame($module->id, $learner->current_module_id);
    }

    private function enableOllama(): void
    {
        config()->set('readirect.ollama.enabled', true);
        config()->set('readirect.agent_feedback.miss_ciel_ollama_enabled', true);
    }

    private function seedAgent(): void
    {
        AgentProfile::create([
            'key' => AgentProfile::COACH_FEEDBACK,
            'name' => 'Miss Ciel',
            'agent_type' => 'coach_feedback',
            'purpose' => 'Learning feedback',
            'uses_llm' => true,
            'is_fixed' => true,
        ]);
    }

    private function context(array $overrides = []): array
    {
        return $overrides + [
            'module_key' => 'module_2',
            'activity_type' => 'read_word',
            'expected_answer' => 'cat',
            'learner_answer' => 'cap',
            'is_correct' => false,
            'error_type' => 'final_sound_error',
            'recommended_action' => 'try_again',
            'source_type' => 'module_activity_response',
            'source_id' => 1,
        ];
    }
}
