<?php

namespace Tests\Feature;

use App\Models\AgentProfile;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\LlmPromptTemplate;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\School;
use App\Services\Agents\AgentCommentaryService;
use App\Services\ModuleActivitySelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentCommentaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_neutral_does_not_reveal_answer_or_hint(): void
    {
        $this->seedAgentsAndTemplate();
        config()->set('readirect.ollama.enabled', true);
        config()->set('readirect.agent_feedback.miss_ciel_ollama_enabled', true);
        Http::fake();

        $commentary = app(AgentCommentaryService::class)->generateCommentary([
            'mode' => 'assessment_neutral',
            'agent_type' => AgentProfile::ASSESSMENT,
            'expected_answer' => 'A',
            'learner_answer' => 'B',
            'is_correct' => false,
            'attempt_number' => 1,
        ]);

        $this->assertSame('assessment_neutral', $commentary['commentary_mode']);
        $this->assertStringNotContainsString('A', $commentary['message']);
        $this->assertStringNotContainsString('close', strtolower($commentary['message']));
        $this->assertStringNotContainsString('correct', strtolower($commentary['message']));
        Http::assertNothingSent();
    }

    public function test_module_coaching_uses_similarity_label_with_fallback_when_disabled(): void
    {
        $this->seedAgentsAndTemplate();
        config()->set('readirect.ollama.enabled', false);

        $commentary = app(AgentCommentaryService::class)->generateCommentary([
            'mode' => 'module_coaching',
            'agent_type' => AgentProfile::COACH_FEEDBACK,
            'expected_answer' => 'cat',
            'learner_answer' => 'cap',
            'is_correct' => false,
            'template_feedback' => 'Great effort!',
        ]);

        $this->assertSame('very_close', $commentary['similarity_label']);
        $this->assertStringContainsString('very close', strtolower($commentary['message']));
        $this->assertTrue($commentary['fallback_used']);
    }

    public function test_module_coaching_uses_safe_llm_output(): void
    {
        $this->seedAgentsAndTemplate();
        config()->set('readirect.ollama.enabled', true);
        config()->set('readirect.agent_feedback.miss_ciel_ollama_enabled', true);

        Http::fake(['127.0.0.1:11434/api/generate' => Http::response(['response' => 'Good try! That was close.'])]);

        $safe = app(AgentCommentaryService::class)->generateCommentary($this->moduleContext());

        $this->assertSame('Good try! That was close.', $safe['message']);
        $this->assertFalse($safe['fallback_used']);
    }

    public function test_module_coaching_rejects_unsafe_output(): void
    {
        $this->seedAgentsAndTemplate();
        config()->set('readirect.ollama.enabled', true);
        config()->set('readirect.agent_feedback.miss_ciel_ollama_enabled', true);
        Http::fake(['*' => Http::response(['response' => 'That was wrong and bad.'])]);

        $unsafe = app(AgentCommentaryService::class)->generateCommentary($this->moduleContext());

        $this->assertTrue($unsafe['fallback_used']);
        $this->assertSame('blocked_term', $unsafe['safety_status']);
    }

    public function test_evaluator_summary_explains_next_step_without_changing_decision(): void
    {
        $this->seedAgentsAndTemplate();
        config()->set('readirect.ollama.enabled', false);

        $commentary = app(AgentCommentaryService::class)->generateCommentary([
            'mode' => 'evaluator_summary',
            'agent_type' => 'evaluator',
            'recommended_action' => 'move_to_module_2',
            'template_feedback' => 'Move to Module 2.',
        ]);

        $this->assertSame('evaluator_summary', $commentary['commentary_mode']);
        $this->assertStringContainsString('Module 2', $commentary['message']);
    }

    public function test_module_activity_response_stores_agent_commentary_without_changing_score(): void
    {
        $this->seedAgentsAndTemplate();
        config()->set('readirect.ollama.enabled', false);
        [$learner, $module] = $this->moduleSetup();
        $learner->update(['current_module_id' => $module->id]);
        $this->seedModuleActivities($module);
        $selection = app(ModuleActivitySelectionService::class);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $items = $selection->selectPracticeItemsForAttempt($attempt, 'read_word', 5);

        $this->withSession([
            'learner_id' => $learner->id,
            'module_attempt_id' => $attempt->id,
            'admin_testing_mode' => true,
        ])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']), [
                'responses' => $items->map(fn ($item) => ['module_attempt_item_id' => $item->id, 'answer' => 'cap'])->all(),
            ])
            ->assertRedirect();

        $response = ModuleActivityResponse::firstOrFail();

        $this->assertFalse($response->is_correct);
        $this->assertSame(0.0, (float) $response->score);
        $this->assertNotNull($response->agent_commentary_text);
        $this->assertSame('coach_feedback', $response->agent_type);
    }

    private function seedAgentsAndTemplate(): void
    {
        foreach ([
            AgentProfile::ASSESSMENT => 'Miss Vivian',
            AgentProfile::COACH_FEEDBACK => 'Miss Ciel',
            AgentProfile::EVALUATOR_RECOMMENDATION => 'Miss Estelle',
        ] as $key => $name) {
            AgentProfile::create([
                'key' => $key,
                'name' => $name,
                'agent_type' => $key,
                'purpose' => $name,
                'uses_llm' => $key === AgentProfile::COACH_FEEDBACK,
                'is_fixed' => true,
            ]);
        }

        LlmPromptTemplate::create([
            'agent_profile_id' => AgentProfile::where('key', AgentProfile::COACH_FEEDBACK)->first()->id,
            'key' => 'agent_answer_commentary',
            'version' => 1,
            'status' => 'active',
            'template' => 'You are a ReaDirect agent. Use the provided result only. Keep it short and kind.',
            'variables' => ['mode'],
        ]);
    }

    private function moduleContext(): array
    {
        return [
            'mode' => 'module_coaching',
            'agent_type' => AgentProfile::COACH_FEEDBACK,
            'expected_answer' => 'cat',
            'learner_answer' => 'cap',
            'is_correct' => false,
            'score' => 0,
            'error_type' => 'final_sound_error',
            'recommended_action' => 'try_again',
            'template_feedback' => 'Good try!',
            'retry_instruction' => 'Try again.',
        ];
    }

    private function moduleSetup(): array
    {
        $school = School::create(['name' => 'Commentary Test School']);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('COM-', false),
            'first_name' => 'Test',
            'grade_level' => 'Grade 1',
        ]);
        $module = Module::create([
            'sequence' => 2,
            'key' => 'module_2',
            'title' => 'Word Reading',
            'description' => 'Read words',
            'is_active' => true,
        ]);

        return [$learner, $module];
    }

    private function seedModuleActivities(Module $module): void
    {
        foreach (range(1, 5) as $index) {
            $content = LearningContent::create([
                'content_type' => 'module_activity',
                'title' => 'Read word '.$index,
                'prompt' => 'Read the word cat.',
                'payload' => [
                    'source_csv_id' => 'COM-'.$index,
                    'module_key' => $module->key,
                    'activity_type' => 'read_word',
                    'sequence' => $index,
                    'expected_answer' => 'cat',
                    'points' => 1,
                ],
                'accepted_answers' => ['cat'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            ModuleActivity::create([
                'module_id' => $module->id,
                'learning_content_id' => $content->id,
                'sequence' => $index,
                'activity_type' => 'read_word',
                'title' => $content->prompt,
                'configuration' => $content->payload,
            ]);
        }
    }
}
