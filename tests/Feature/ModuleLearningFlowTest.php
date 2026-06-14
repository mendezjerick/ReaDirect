<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearnerReward;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\School;
use App\Services\ModuleActivitySelectionService;
use App\Services\ModuleFeedbackService;
use App\Services\ModuleScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_new_module_letter_only_items_exclude_unreliable_isolated_letters(): void
    {
        [$learner, $module] = $this->moduleContext();
        $this->seedModuleLetterActivities($module, 'hear_and_repeat', false);
        $this->seedModuleLetterActivities($module, 'sound_drill', false);
        $this->seedModuleLetterActivities($module, 'see_letter_say_sound', false);
        $this->seedModuleLetterActivities($module, 'mastery_check', true);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);

        $hearAndRepeat = $service->selectPracticeItemsForAttempt($attempt, 'hear_and_repeat', 10);
        $soundDrill = $service->selectPracticeItemsForAttempt($attempt, 'sound_drill', 10);
        $seeLetter = $service->selectPracticeItemsForAttempt($attempt, 'see_letter_say_sound', 10);
        $mastery = $service->selectMasteryItemsForAttempt($attempt, 10);
        $selected = $hearAndRepeat
            ->merge($soundDrill)
            ->merge($seeLetter)
            ->merge($mastery)
            ->map(fn ($item) => $item->prompt_snapshot['payload']['expected_answer'] ?? null)
            ->filter()
            ->all();

        $this->assertCount(10, $hearAndRepeat);
        $this->assertCount(10, $soundDrill);
        $this->assertCount(10, $seeLetter);
        $this->assertCount(10, $mastery);
        $this->assertEmpty(array_intersect(['B', 'P', 'D', 'T'], $selected));
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

    public function test_module_activity_item_retry_state_blocks_progress_until_correct_or_three_attempts(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 1, false);
        $this->seedRule($module, 'read_word', 1, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 1)->first();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'read_word']), [
                'module_attempt_item_id' => $item->id,
                'answer' => 'dog',
                'transcript_source' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('retry_state.attempt_count', 1)
            ->assertJsonPath('retry_state.can_retry', true)
            ->assertJsonPath('retry_state.is_resolved', false)
            ->assertJsonPath('retry_state.attempts.0.status', 'incorrect')
            ->assertJsonPath('agent_cue.agent', 'ciel')
            ->assertJsonPath('agent_cue.action', 'advise')
            ->assertJsonPath('agent_cue.dialogue_key', 'ciel.module.close_retry.generic')
            ->assertJsonPath('agent_cue.official_progression_changed', false)
            ->assertJsonPath('ciel_agent.agent', 'ciel')
            ->assertJsonPath('ciel_agent.mode', 'soft_retry')
            ->assertJsonPath('ciel_agent.animation', 'c-confused')
            ->assertJsonPath('ciel_agent.official_progression_changed', false);

        $this->assertNull($item->refresh()->answered_at);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']))
            ->assertRedirect(route('learner.modules.activity', [$module, 'read_word']));

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'read_word']), [
                'module_attempt_item_id' => $item->id,
                'answer' => 'cat',
                'transcript_source' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('retry_state.attempt_count', 2)
            ->assertJsonPath('retry_state.is_correct', true)
            ->assertJsonPath('retry_state.is_resolved', true)
            ->assertJsonPath('retry_state.attempts.1.status', 'correct')
            ->assertJsonPath('agent_cue.agent', 'ciel')
            ->assertJsonPath('agent_cue.action', 'clap')
            ->assertJsonPath('agent_cue.dialogue_key', 'ciel.module.section_complete')
            ->assertJsonPath('agent_cue.official_progression_changed', false)
            ->assertJsonPath('ciel_agent.mode', 'correct_praise')
            ->assertJsonPath('ciel_agent.animation', 'c-clap');

        $this->assertNotNull($item->refresh()->answered_at);
        $this->assertSame(2, ModuleActivityResponse::firstOrFail()->retry_count);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']))
            ->assertRedirect(route('learner.modules.mastery-check', $module));
    }

    public function test_two_wrong_attempts_on_same_module_item_triggers_ciel_teaching_focus_mode(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 1, false);
        $this->seedRule($module, 'read_word', 1, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 1)->first();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'read_word']), [
                'module_attempt_item_id' => $item->id,
                'answer' => 'dog',
                'transcript_source' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('retry_state.attempt_count', 1)
            ->assertJsonPath('ciel_focus_event', null);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'read_word']), [
                'module_attempt_item_id' => $item->id,
                'answer' => 'dog',
                'transcript_source' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('retry_state.attempt_count', 2)
            ->assertJsonPath('retry_state.is_resolved', false)
            ->assertJsonPath('ciel_agent.mode', 'focus_teach')
            ->assertJsonPath('ciel_agent.animation', 'c-advise')
            ->assertJsonPath('ciel_agent.focus_mode.enabled', true)
            ->assertJsonPath('ciel_agent.focus_mode.layout', 'blank_screen')
            ->assertJsonPath('ciel_agent.focus_mode.target_position', 'center')
            ->assertJsonPath('ciel_agent.focus_mode.agent_position', 'bottom')
            ->assertJsonPath('ciel_agent.focus_mode.target_size', 'large')
            ->assertJsonPath('ciel_focus_event.enabled', true)
            ->assertJsonPath('ciel_focus_event.mode', 'teaching')
            ->assertJsonPath('ciel_focus_event.target_type', 'word')
            ->assertJsonPath('ciel_focus_event.target_text', 'cat')
            ->assertJsonPath('ciel_focus_event.reason', 'two_wrong_attempts')
            ->assertJsonPath('ciel_focus_event.reward', null)
            ->assertJsonPath('ciel_focus_event.dialogue_steps.0.action', 'talk');

        $this->assertNull($item->refresh()->answered_at);
        $response = ModuleActivityResponse::firstOrFail();
        $this->assertFalse($response->is_correct);
        $this->assertSame(0.0, $response->score);
    }

    public function test_three_correct_module_items_triggers_star_reward_focus_mode_and_dashboard_total(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 3, false);
        $this->seedRule($module, 'read_word', 3, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 3)->values();

        foreach ($items as $index => $item) {
            $response = $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
                ->postJson(route('learner.modules.activity.check', [$module, 'read_word']), [
                    'module_attempt_item_id' => $item->id,
                    'answer' => 'cat',
                    'transcript_source' => 'manual',
                ])
                ->assertOk()
                ->assertJsonPath('retry_state.is_correct', true);

            if ($index < 2) {
                $response->assertJsonPath('ciel_focus_event', null);
            } else {
                $response
                    ->assertJsonPath('ciel_focus_event.enabled', true)
                    ->assertJsonPath('ciel_focus_event.mode', 'reward')
                    ->assertJsonPath('ciel_focus_event.reason', 'three_correct_streak')
                    ->assertJsonPath('ciel_focus_event.reward.type', 'star')
                    ->assertJsonPath('ciel_focus_event.reward.amount', 1)
                    ->assertJsonPath('ciel_focus_event.dialogue_steps.1.action', 'clap');
            }
        }

        $this->assertSame(1, LearnerReward::where('learner_id', $learner->id)->where('reward_type', 'star')->sum('amount'));

        $duplicateReward = app(\App\Services\CielFocusModeService::class)->eventForModuleCheck(
            $attempt,
            $items[2],
            $module,
            'read_word',
            'cat',
            true,
            1,
            3,
        );

        $this->assertNull($duplicateReward);

        $this->assertSame(1, LearnerReward::where('learner_id', $learner->id)->where('reward_type', 'star')->sum('amount'));

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rewards.stars', 1)
            );
    }

    public function test_correct_streak_six_grants_a_second_star_reward(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 6, false);
        $this->seedRule($module, 'read_word', 6, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'read_word', 6)->values();

        foreach ($items as $index => $item) {
            $response = $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
                ->postJson(route('learner.modules.activity.check', [$module, 'read_word']), [
                    'module_attempt_item_id' => $item->id,
                    'answer' => 'cat',
                    'transcript_source' => 'manual',
                ])
                ->assertOk();

            if ($index === 2 || $index === 5) {
                $response->assertJsonPath('ciel_focus_event.mode', 'reward');
            }
        }

        $this->assertSame(2, LearnerReward::where('learner_id', $learner->id)->where('reward_type', 'star')->sum('amount'));
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

    public function test_module_mastery_item_resolves_after_three_incorrect_attempts_and_scores_zero(): void
    {
        [$learner, $module] = $this->moduleContext('module_1');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_mastery_in_progress']);
        $this->seedModuleActivities($module, 'mastery_check', 1, true);
        $this->seedRule($module, 'mastery_check', 0, 1);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'mastery_started']);
        $item = $service->selectMasteryItemsForAttempt($attempt, 1)->first();

        foreach ([1, 2, 3] as $attemptNumber) {
            $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
                ->postJson(route('learner.modules.mastery-check.check', $module), [
                    'module_attempt_item_id' => $item->id,
                    'answer' => 'dog',
                    'transcript_source' => 'manual',
                ])
                ->assertOk()
                ->assertJsonPath('retry_state.attempt_count', $attemptNumber)
                ->assertJsonPath('retry_state.attempts.'.($attemptNumber - 1).'.status', 'incorrect');
        }

        $response = ModuleActivityResponse::firstOrFail();
        $this->assertFalse($response->is_correct);
        $this->assertSame(0.0, $response->score);
        $this->assertSame(3, $response->retry_count);
        $this->assertNotNull($item->refresh()->answered_at);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.mastery-check.store', $module))
            ->assertRedirect(route('learner.modules.mastery-result', $module));

        $this->assertSame('completed', $attempt->refresh()->status);
        $this->assertSame(0.0, $attempt->score);
        $this->assertSame('repeat_module_1', $attempt->mastery_decision);
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

    public function test_module_overview_includes_lesson_boxes_and_safe_miss_ciel_copy(): void
    {
        [$learner, $module] = $this->moduleContext('module_1');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_assigned']);
        $this->seedModuleActivities($module, 'listen_and_say', 5, false);
        $this->seedModuleActivities($module, 'mastery_check', 10, true);
        $this->seedRule($module, 'listen_and_say', 5, 0);
        $this->seedRule($module, 'mastery_check', 0, 10);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.overview', $module))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Modules/ModuleOverview')
                ->where('purpose', 'You will practice letters and sounds so you can say them clearly.')
                ->where('lessonBoxes.0.key', 'listen_and_say')
                ->where('lessonBoxes.0.explanation', 'This lesson helps you listen closely and say the sound clearly after you hear it.')
                ->has('lessonBoxes', 1)
                ->missing('debug')
            );
    }

    public function test_continue_module_reuses_active_attempt_without_duplicate_attempt(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 5, false);
        $this->seedRule($module, 'read_word', 5, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.start', $module))
            ->assertRedirect(route('learner.modules.overview', $module));

        $this->assertSame(1, $learner->moduleAttempts()->where('module_id', $module->id)->count());
        $this->assertSame($attempt->id, $learner->moduleAttempts()->where('module_id', $module->id)->first()->id);
    }

    public function test_stale_module_activity_post_does_not_create_new_attempt(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'read_word', 5, false);
        $this->seedRule($module, 'read_word', 5, 0);

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']), ['responses' => []])
            ->assertRedirect(route('learner.modules.start', $module));

        $this->assertSame(0, $learner->moduleAttempts()->where('module_id', $module->id)->count());
    }

    public function test_dashboard_maps_legacy_extra_drills_stage_to_module_start_and_final_reassessment_actions(): void
    {
        [$learner, $module] = $this->moduleContext('module_1');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'extra_phoneme_drills']);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flowState.primary_action_label', 'Start Module')
                ->where('flowState.module.current_module_key', 'module_1')
            );

        $learner->update(['current_module_id' => null, 'current_stage' => 'final_reassessment_pending']);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flowState.primary_action_label', 'Start Final Reassessment')
                ->where('flowState.module.current_module_key', null)
                ->missing('flowState.current_module_id')
            );
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

    private function seedModuleLetterActivities(Module $module, string $activityType, bool $isMastery): void
    {
        foreach (range('A', 'Z') as $index => $letter) {
            $content = LearningContent::create([
                'content_type' => 'module_activity',
                'title' => $activityType.' '.$letter,
                'prompt' => $letter,
                'payload' => [
                    'source_csv_id' => 'LETTER-'.$activityType.'-'.$letter,
                    'module_key' => $module->key,
                    'activity_type' => $activityType,
                    'sequence' => $index + 1,
                    'expected_answer' => $letter,
                    'points' => 1,
                    'is_mastery_item' => $isMastery,
                ],
                'accepted_answers' => [$letter],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            ModuleActivity::create([
                'module_id' => $module->id,
                'learning_content_id' => $content->id,
                'sequence' => $index + 1,
                'activity_type' => $activityType,
                'title' => $content->prompt,
                'configuration' => $content->payload,
            ]);
        }
    }

    private function seedRule(Module $module, string $activityType, int $practiceCount, int $masteryCount): void
    {
        LearningContent::create([
            'content_type' => 'module_activity_selection_rule',
            'title' => $module->key.' '.$activityType.' rule',
            'prompt' => 'Selection rule',
            'payload' => [
                'module_key' => $module->key,
                'activity_type' => $activityType,
                'practice_item_count' => $practiceCount,
                'mastery_item_count' => $masteryCount,
                'source_csv_id' => $module->key.'-'.$activityType,
            ],
            'difficulty' => 'grade_1',
            'is_active' => true,
        ]);
    }
}
