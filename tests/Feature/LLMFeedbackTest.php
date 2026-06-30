<?php

namespace Tests\Feature;

use App\Models\AgentProfile;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\LlmInteraction;
use App\Models\LlmPromptTemplate;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\School;
use App\Services\LLM\CoachFeedbackLLMService;
use App\Services\LLM\OpenAIClientService;
use App\Services\ModuleActivitySelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LLMFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_client_returns_null_when_disabled_or_missing_key(): void
    {
        Http::fake();
        config()->set('readirect.openai.enabled', false);
        config()->set('readirect.openai.api_key', 'sk-test-secret');

        $client = app(OpenAIClientService::class);

        $this->assertNull($client->generateText('system', 'user'));
        Http::assertNothingSent();

        config()->set('readirect.openai.enabled', true);
        config()->set('readirect.openai.api_key', '');

        $this->assertNull($client->generateText('system', 'user'));
        Http::assertNothingSent();
    }

    public function test_openai_client_handles_failed_response_and_timeout_safely(): void
    {
        config()->set('readirect.openai.enabled', true);
        config()->set('readirect.openai.api_key', 'sk-test-secret');

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'error' => ['message' => 'Invalid API key sk-test-secret'],
            ], 401),
        ]);

        $this->assertNull(app(OpenAIClientService::class)->generateText('system', 'user'));

        Http::fake(fn () => throw new ConnectionException('timeout with sk-test-secret'));

        $this->assertNull(app(OpenAIClientService::class)->generateText('system', 'user'));
    }

    public function test_coach_feedback_uses_template_fallback_when_openai_disabled(): void
    {
        $this->seedPromptTemplate();
        config()->set('readirect.openai.enabled', false);

        $feedback = app(CoachFeedbackLLMService::class)->generateFeedback($this->feedbackContext([
            'template_feedback' => 'Great effort!',
            'retry_instruction' => 'Try again when you are ready.',
        ]));

        $this->assertSame('Great effort! Try again when you are ready.', $feedback);
        $interaction = LlmInteraction::firstOrFail();
        $this->assertTrue($interaction->fallback_used);
        $this->assertSame('empty_output', $interaction->safety_status);
    }

    public function test_coach_feedback_returns_sanitized_openai_output_when_valid(): void
    {
        $this->seedPromptTemplate();
        config()->set('readirect.openai.enabled', true);
        config()->set('readirect.openai.api_key', 'sk-test-secret');

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => 'Nice try! Listen carefully, then say it again.',
            ]),
        ]);

        $feedback = app(CoachFeedbackLLMService::class)->generateFeedback($this->feedbackContext());

        $this->assertSame('Nice try! Listen carefully, then say it again.', $feedback);
        $interaction = LlmInteraction::firstOrFail();
        $this->assertFalse($interaction->fallback_used);
        $this->assertSame('safe', $interaction->safety_status);
        $this->assertStringNotContainsString('sk-test-secret', json_encode($interaction->toArray()));
    }

    public function test_coach_feedback_rejects_unsafe_or_too_long_output_and_falls_back(): void
    {
        $this->seedPromptTemplate();
        config()->set('readirect.openai.enabled', true);
        config()->set('readirect.openai.api_key', 'sk-test-secret');

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output_text' => 'That was wrong and bad.',
            ]),
        ]);

        $feedback = app(CoachFeedbackLLMService::class)->generateFeedback($this->feedbackContext([
            'template_feedback' => 'Great effort!',
            'retry_instruction' => 'Try again when you are ready.',
        ]));

        $this->assertSame('Great effort! Try again when you are ready.', $feedback);
        $this->assertTrue(LlmInteraction::firstOrFail()->fallback_used);
        $this->assertSame('unsafe_language', LlmInteraction::firstOrFail()->safety_status);
    }

    public function test_module_activity_submission_uses_deterministic_ciel_without_llm_runtime(): void
    {
        $this->seedPromptTemplate();
        config()->set('readirect.ollama.enabled', true);
        config()->set('readirect.agent_feedback.miss_ciel_ollama_enabled', true);

        Http::fake();

        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 5);
        $selection = app(ModuleActivitySelectionService::class);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $items = $selection->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $responses = $items->map(fn ($item) => [
            'module_attempt_item_id' => $item->id,
            'answer' => 'cat',
        ])->all();

        $this->withSession([
            'learner_id' => $learner->id,
            'module_attempt_id' => $attempt->id,
            'admin_testing_mode' => true,
        ])
            ->post(route('learner.modules.activity.store', [$module, 'display_word_reading']), ['responses' => $responses])
            ->assertRedirect();

        $response = ModuleActivityResponse::firstOrFail();

        $this->assertTrue($response->is_correct);
        $this->assertSame(1.0, (float) $response->score);
        $this->assertSame('laravel_deterministic_fallback', $response->agent_commentary_source);
        $this->assertNotEmpty($response->feedback_text);
        $this->assertDatabaseCount('llm_interactions', 0);
        Http::assertNothingSent();
    }

    private function seedPromptTemplate(): AgentProfile
    {
        $agent = AgentProfile::create([
            'key' => AgentProfile::COACH_FEEDBACK,
            'name' => 'Miss Ciel',
            'agent_type' => 'coach_feedback',
            'purpose' => 'Learning feedback',
            'uses_llm' => true,
            'is_fixed' => true,
        ]);

        foreach (['coach_feedback_correct', 'coach_feedback_incorrect'] as $key) {
            LlmPromptTemplate::create([
                'agent_profile_id' => $agent->id,
                'key' => $key,
                'version' => 1,
                'status' => 'active',
                'template' => 'You are the Miss Ciel. Use short, kind Grade 1 feedback only. Do not score.',
                'variables' => ['module_key', 'activity_type'],
            ]);
        }

        return $agent;
    }

    private function feedbackContext(array $overrides = []): array
    {
        return $overrides + [
            'learner_id' => null,
            'source_type' => 'module_activity_response',
            'source_id' => 123,
            'prompt_key' => 'coach_feedback_incorrect',
            'module_key' => 'module_1',
            'activity_type' => 'display_word_reading',
            'expected_answer' => 'cat',
            'learner_response' => 'cap',
            'is_correct' => false,
            'error_type' => 'incorrect_general',
            'recommended_action' => 'try_again',
            'template_feedback' => 'Great effort!',
            'retry_instruction' => 'Try again when you are ready.',
            'max_words' => 30,
        ];
    }

    private function moduleContext(): array
    {
        $school = School::create(['name' => 'LLM Test School']);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('LLM-', false),
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

    private function seedModuleActivities(Module $module, string $activityType, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $content = LearningContent::create([
                'content_type' => 'module_activity',
                'title' => $activityType.' '.$index,
                'prompt' => 'Read the word cat.',
                'payload' => [
                    'source_csv_id' => 'LLM-MOD-'.$index,
                    'module_key' => $module->key,
                    'activity_type' => $activityType,
                    'sequence' => $index,
                    'canonical_target' => $activityType.'-'.$index,
                    'expected_answer' => 'cat',
                    'points' => 1,
                    'is_mastery_item' => false,
                ],
                'accepted_answers' => ['cat'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            ModuleActivity::create([
                'module_id' => $module->id,
                'learning_content_id' => $content->id,
                'sequence' => $index,
                'activity_type' => $activityType,
                'title' => $content->prompt,
                'configuration' => $content->payload,
            ]);
        }
    }
}
