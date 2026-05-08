<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\School;
use App\Services\ModuleActivitySelectionService;
use App\Services\ModuleFeedbackService;
use App\Services\ModuleScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleLearningFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_practice_items_are_selected_locked_and_reused(): void
    {
        [$learner, $module] = $this->moduleContext();
        $this->seedModuleActivities($module, 'read_word', 12, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);

        $first = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 5);
        $second = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 5);

        $this->assertCount(5, $first);
        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
        $this->assertFalse($first->first()->is_mastery_item);
        $this->assertNotNull($first->first()->prompt_snapshot);
    }

    public function test_mastery_items_are_selected_locked_and_reused(): void
    {
        [$learner, $module] = $this->moduleContext();
        $this->seedModuleActivities($module, 'mastery_check', 12, true);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);

        $first = $service->selectMasteryItemsForAttempt($attempt, 10);
        $second = $service->selectMasteryItemsForAttempt($attempt, 10);

        $this->assertCount(10, $first);
        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
        $this->assertTrue($first->first()->is_mastery_item);
    }

    public function test_module_scoring_accepts_correct_answers_and_scores_attempted_misses_zero(): void
    {
        [$learner, $module] = $this->moduleContext();
        $this->seedModuleActivities($module, 'read_word', 1, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 1)->first();
        $scoring = app(ModuleScoringService::class);

        $correct = $scoring->scoreAnswer($item, 'cat');
        $incorrect = $scoring->scoreAnswer($item, 'dog');

        $this->assertTrue($correct['is_correct']);
        $this->assertSame(1.0, $correct['score']);
        $this->assertFalse($incorrect['is_correct']);
        $this->assertSame(0, $incorrect['score']);
    }

    public function test_missing_answer_is_rejected_before_scoring(): void
    {
        [$learner, $module] = $this->moduleContext();
        $this->seedModuleActivities($module, 'read_word', 1, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 1)->first();

        $this->expectException(\InvalidArgumentException::class);

        app(ModuleScoringService::class)->scoreAnswer($item, ' ');
    }

    public function test_module_activity_submission_with_missing_answer_is_rejected(): void
    {
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 5, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 5);
        $responses = $items->map(fn ($item) => ['module_attempt_item_id' => $item->id, 'answer' => 'cat'])->all();
        $responses[0]['answer'] = '';

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']), ['responses' => $responses])
            ->assertSessionHasErrors('responses.0.answer');

        $this->assertSame(0, ModuleActivityResponse::count());
    }

    public function test_module_activity_submission_with_stale_item_ids_is_rejected(): void
    {
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 5, false);
        $this->seedModuleActivities($module, 'word_family_drill', 5, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $staleItems = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 5);
        $service->selectPracticeItemsForAttempt($attempt, 'word_family_drill', 5);
        $responses = $staleItems->map(fn ($item) => ['module_attempt_item_id' => $item->id, 'answer' => 'cat'])->all();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'word_family_drill']), ['responses' => $responses])
            ->assertSessionHasErrors('responses');

        $this->assertSame(0, ModuleActivityResponse::count());
    }

    public function test_module_mastery_submission_with_missing_answer_is_rejected(): void
    {
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'mastery_check', 10, true);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $items = $service->selectMasteryItemsForAttempt($attempt, 10);
        $responses = $items->map(fn ($item) => ['module_attempt_item_id' => $item->id, 'answer' => 'cat'])->all();
        $responses[0]['answer'] = ' ';

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.mastery-check.store', $module), ['responses' => $responses])
            ->assertSessionHasErrors('responses.0.answer');

        $this->assertSame(0, ModuleActivityResponse::count());
    }

    public function test_mastery_score_is_computed_from_mastery_responses(): void
    {
        [$learner, $module] = $this->moduleContext();
        $this->seedModuleActivities($module, 'mastery_check', 10, true);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $items = $service->selectMasteryItemsForAttempt($attempt, 10);

        foreach ($items as $index => $item) {
            ModuleActivityResponse::create([
                'module_attempt_id' => $attempt->id,
                'module_activity_id' => $item->module_activity_id,
                'module_attempt_item_id' => $item->id,
                'response_text' => $index < 9 ? 'cat' : 'dog',
                'learner_answer' => $index < 9 ? 'cat' : 'dog',
                'expected_answer' => 'cat',
                'is_correct' => $index < 9,
                'score' => $index < 9 ? 1 : 0,
                'is_mastery_item' => true,
            ]);
        }

        $this->assertSame(90.0, app(ModuleScoringService::class)->calculateMasteryScore($attempt));
    }

    public function test_feedback_templates_are_child_friendly(): void
    {
        LearningContent::create([
            'content_type' => 'module_feedback_template',
            'title' => 'MFB-TEST',
            'prompt' => 'Great effort! Try this one again.',
            'payload' => [
                'module_key' => 'all',
                'activity_type' => 'all',
                'error_type' => 'incorrect_general',
                'retry_instruction' => 'Try again when you are ready.',
                'success_text' => 'Nice reading!',
            ],
            'difficulty' => 'grade_1',
            'is_active' => true,
        ]);

        $feedback = app(ModuleFeedbackService::class)->feedbackForIncorrect('module_1', 'read_word');
        $combined = strtolower(implode(' ', $feedback));

        $this->assertStringContainsString('great effort', strtolower($feedback['feedback_text']));
        $this->assertStringNotContainsString('wrong', $combined);
        $this->assertStringNotContainsString('failed', $combined);
        $this->assertStringNotContainsString('bad', $combined);
        $this->assertStringNotContainsString('poor', $combined);
    }

    public function test_learner_cannot_access_unassigned_module(): void
    {
        [$learner, $moduleOne] = $this->moduleContext('module_1');
        $moduleTwo = Module::create(['sequence' => 2, 'key' => 'module_2', 'title' => 'Module 2', 'description' => 'Words', 'is_active' => true]);
        $learner->update(['current_module_id' => $moduleOne->id, 'current_stage' => 'module_assigned']);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.start', $moduleTwo))
            ->assertRedirect(route('learner.dashboard'));
    }

    private function moduleContext(string $moduleKey = 'module_2'): array
    {
        $school = School::create(['name' => 'Module Test School']);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('RD-', false),
            'first_name' => 'Test',
            'grade_level' => 'Grade 1',
        ]);
        $module = Module::create([
            'sequence' => $moduleKey === 'module_1' ? 1 : 2,
            'key' => $moduleKey,
            'title' => 'Module Test',
            'description' => 'Practice module',
            'is_active' => true,
        ]);

        return [$learner, $module];
    }

    private function seedModuleActivities(Module $module, string $activityType, int $count, bool $isMastery): void
    {
        foreach (range(1, $count) as $index) {
            $content = LearningContent::create([
                'content_type' => 'module_activity',
                'title' => $activityType.' '.$index,
                'prompt' => 'Read the word cat.',
                'payload' => [
                    'source_csv_id' => 'MOD-'.$index,
                    'module_key' => $module->key,
                    'activity_type' => $activityType,
                    'sequence' => $index,
                    'expected_answer' => 'cat',
                    'points' => 1,
                    'is_mastery_item' => $isMastery,
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
