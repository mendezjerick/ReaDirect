<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearnerModuleUsedTarget;
use App\Models\LearnerReward;
use App\Models\LearningContent;
use App\Models\AssessmentAttempt;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
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
        $this->seedModuleActivities($module, 'display_word_reading', 12, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);

        $first = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $second = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);

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

    public function test_lesson_score_four_of_five_marks_mastered_and_unlocks_next_lesson(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 5, false);
        $this->seedModuleActivities($module, 'split_word_reading', 5, false);
        $this->seedRule($module, 'display_word_reading', 5, 0);
        $this->seedRule($module, 'split_word_reading', 5, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $this->seedResolvedLessonResponses($attempt, $items, 4);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'display_word_reading']))
            ->assertRedirect(route('learner.modules.activity', [$module, 'split_word_reading']));

        $progress = $service->latestLessonProgress($attempt, 'display_word_reading');
        $this->assertSame('mastered', $progress?->status);
        $this->assertSame(4, $progress?->correct_count);
        $this->assertSame('split_word_reading', app(\App\Services\LearnerFlowService::class)->nextPracticeActivity($attempt, $module));
    }

    public function test_lesson_score_three_of_five_repeats_same_lesson_with_fresh_targets(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 12, false);
        $this->seedRule($module, 'display_word_reading', 5, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $firstItems = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $firstTargets = $firstItems->pluck('prompt_snapshot.payload.canonical_target')->all();
        $this->seedResolvedLessonResponses($attempt, $firstItems, 3);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'display_word_reading']))
            ->assertRedirect(route('learner.modules.activity', [$module, 'display_word_reading']));

        $progress = $service->latestLessonProgress($attempt, 'display_word_reading');
        $this->assertSame('retry', $progress?->status);
        $this->assertSame(3, $progress?->correct_count);

        $secondItems = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $secondTargets = $secondItems->pluck('prompt_snapshot.payload.canonical_target')->all();

        $this->assertSame(2, $service->latestLessonProgress($attempt, 'display_word_reading')?->lesson_attempt_number);
        $this->assertCount(5, $secondItems);
        $this->assertSame([], array_values(array_intersect($firstTargets, $secondTargets)));
    }

    public function test_used_target_cycle_refreshes_after_pool_exhaustion_and_still_fills_lesson(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $this->seedModuleActivities($module, 'display_word_reading', 7, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $firstItems = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $firstTargets = $firstItems->pluck('prompt_snapshot.payload.canonical_target')->all();
        $service->latestLessonProgress($attempt, 'display_word_reading')?->update(['status' => 'retry', 'completed_at' => now()]);

        $secondItems = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $secondTargets = $secondItems->pluck('prompt_snapshot.payload.canonical_target')->all();
        $allTargets = collect(range(1, 7))->map(fn (int $index): string => 'display_word_reading-'.$index)->all();
        $remainingTargets = array_values(array_diff($allTargets, $firstTargets));

        $this->assertCount(5, $secondItems);
        $this->assertCount(2, array_intersect($remainingTargets, $secondTargets));
        $this->assertSame(2, LearnerModuleUsedTarget::where('learner_id', $learner->id)->where('module_key', 'module_2')->max('cycle_number'));
    }

    public function test_new_module_letter_only_items_exclude_unreliable_isolated_letters(): void
    {
        [$learner, $module] = $this->moduleContext();
        $this->seedModuleLetterActivities($module, 'letter_pair_identification', false);
        $this->seedModuleLetterActivities($module, 'missing_first_letter', false);
        $this->seedModuleLetterActivities($module, 'highlighted_first_letter', false);
        $this->seedModuleLetterActivities($module, 'mastery_check', true);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);

        $hearAndRepeat = $service->selectPracticeItemsForAttempt($attempt, 'letter_pair_identification', 10);
        $soundDrill = $service->selectPracticeItemsForAttempt($attempt, 'missing_first_letter', 10);
        $seeLetter = $service->selectPracticeItemsForAttempt($attempt, 'highlighted_first_letter', 10);
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
        $this->seedModuleActivities($module, 'display_word_reading', 1, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 1)->first();
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
        $this->seedModuleActivities($module, 'display_word_reading', 1, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 1)->first();

        $this->expectException(\InvalidArgumentException::class);

        app(ModuleScoringService::class)->scoreAnswer($item, ' ');
    }

    public function test_module_activity_submission_with_missing_answer_is_rejected(): void
    {
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 5, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $responses = $items->map(fn ($item) => ['module_attempt_item_id' => $item->id, 'answer' => 'cat'])->all();
        $responses[0]['answer'] = '';

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'display_word_reading']), ['responses' => $responses])
            ->assertSessionHasErrors('responses.0.answer');

        $this->assertSame(0, ModuleActivityResponse::count());
    }

    public function test_module_activity_submission_with_stale_item_ids_is_rejected(): void
    {
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 5, false);
        $this->seedModuleActivities($module, 'split_word_reading', 5, false);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $staleItems = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 5);
        $service->selectPracticeItemsForAttempt($attempt, 'split_word_reading', 5);
        $responses = $staleItems->map(fn ($item) => ['module_attempt_item_id' => $item->id, 'answer' => 'cat'])->all();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'split_word_reading']), ['responses' => $responses])
            ->assertSessionHasErrors('responses');

        $this->assertSame(0, ModuleActivityResponse::count());
    }

    public function test_module_activity_item_retry_state_blocks_progress_until_correct_or_three_attempts(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 1, false);
        $this->seedRule($module, 'display_word_reading', 1, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 1)->first();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'display_word_reading']), [
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
            ->post(route('learner.modules.activity.store', [$module, 'display_word_reading']))
            ->assertRedirect(route('learner.modules.activity', [$module, 'display_word_reading']));

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'display_word_reading']), [
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
            ->post(route('learner.modules.activity.store', [$module, 'display_word_reading']))
            ->assertRedirect(route('learner.modules.mastery-check', $module));
    }

    public function test_two_wrong_attempts_on_same_module_item_triggers_ciel_teaching_focus_mode(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 1, false);
        $this->seedRule($module, 'display_word_reading', 1, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $item = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 1)->first();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'display_word_reading']), [
                'module_attempt_item_id' => $item->id,
                'answer' => 'dog',
                'transcript_source' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('retry_state.attempt_count', 1)
            ->assertJsonPath('ciel_focus_event', null);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.modules.activity.check', [$module, 'display_word_reading']), [
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
        $this->seedModuleActivities($module, 'display_word_reading', 3, false);
        $this->seedRule($module, 'display_word_reading', 3, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 3)->values();

        foreach ($items as $index => $item) {
            $response = $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
                ->postJson(route('learner.modules.activity.check', [$module, 'display_word_reading']), [
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
            'display_word_reading',
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
        $this->seedModuleActivities($module, 'display_word_reading', 6, false);
        $this->seedRule($module, 'display_word_reading', 6, 0);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $attempt->update(['is_sandbox' => true, 'status' => 'practice_started']);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'display_word_reading', 6)->values();

        foreach ($items as $index => $item) {
            $response = $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
                ->postJson(route('learner.modules.activity.check', [$module, 'display_word_reading']), [
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

        $feedback = app(ModuleFeedbackService::class)->feedbackForIncorrect('module_1', 'display_word_reading');
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
        $this->seedModuleActivities($module, 'letter_pair_identification', 5, false);
        $this->seedModuleActivities($module, 'mastery_check', 10, true);
        $this->seedRule($module, 'letter_pair_identification', 5, 0);
        $this->seedRule($module, 'mastery_check', 0, 10);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.overview', $module))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Modules/ModuleOverview')
                ->where('purpose', 'You will practice letters and sounds so you can say them clearly.')
                ->where('guideLineKey', 'ciel.module1.overview.intro')
                ->where('lessonBoxes.0.key', 'letter_pair_identification')
                ->where('lessonBoxes.0.title', 'Display Letter Pair')
                ->where('lessonBoxes.0.description', 'Say the letter shown as an uppercase and lowercase pair.')
                ->where('lessonBoxes.0.explanation', 'This lesson shows the big and small form together, like Aa. Look first, then say the letter name clearly.')
                ->where('lessonBoxes.0.line_key', 'ciel.module1.overview.letter_pair_identification')
                ->has('lessonBoxes', 1)
                ->missing('debug')
            );
    }

    public function test_module_three_single_lesson_mastery_unlocks_final_assessment(): void
    {
        [$learner, $module] = $this->moduleContext('module_3');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'simple_sentence_reading', 5, false);
        $this->seedModuleActivities($module, 'mastery_check', 10, true);
        $this->seedRule($module, 'simple_sentence_reading', 5, 0);
        $this->seedRule($module, 'mastery_check', 0, 10);
        $service = app(ModuleActivitySelectionService::class);
        $attempt = $service->startOrResumeModuleAttempt($learner, $module);
        $items = $service->selectPracticeItemsForAttempt($attempt, 'simple_sentence_reading', 5);
        $this->seedResolvedLessonResponses($attempt, $items, 5);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'simple_sentence_reading']))
            ->assertRedirect(route('learner.modules.mastery-check', $module));

        $this->assertNull(app(\App\Services\LearnerFlowService::class)->nextPracticeActivity($attempt, $module));

        $masteryItems = $service->selectMasteryItemsForAttempt($attempt, 10);
        $this->seedResolvedModuleResponses($attempt, $masteryItems, 10, true);
        $attempt->update(['status' => 'mastery_started']);
        $learner->update(['current_stage' => 'module_mastery_in_progress']);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.mastery-check.store', $module))
            ->assertRedirect(route('learner.modules.mastery-result', $module));

        $this->assertSame('proceed_to_reassessment', $attempt->refresh()->mastery_decision);
        $this->assertNull($learner->refresh()->current_module_id);
        $this->assertSame('final_reassessment_pending', $learner->current_stage);
    }

    public function test_advanced_module_unlocks_only_after_perfect_final_and_awards_special_star(): void
    {
        $school = School::create(['name' => 'Advanced Module School']);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('ADV-', false),
            'first_name' => 'Advanced',
            'grade_level' => 'Grade 1',
            'current_stage' => 'final_reassessment_completed',
        ]);
        foreach ([1 => ['module_1', 'Module 1'], 2 => ['module_2', 'Module 2'], 3 => ['module_3', 'Module 3'], 4 => ['advanced_module', 'Advanced Module']] as $sequence => [$key, $title]) {
            Module::create(['sequence' => $sequence, 'key' => $key, 'title' => $title, 'description' => $title, 'is_active' => true]);
        }
        $advanced = Module::where('key', 'advanced_module')->firstOrFail();
        $this->seedModuleActivities($advanced, 'comma_pause_reading', 1, false);
        $this->seedModuleActivities($advanced, 'mastery_check', 1, true);
        $this->seedRule($advanced, 'comma_pause_reading', 1, 0);
        $this->seedRule($advanced, 'mastery_check', 0, 1);
        $final = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'final_reassessment_completed',
            'task_1_score' => 10,
            'task_2a_score' => 10,
            'task_2b_score' => 10,
            'crla_total_score' => 30,
            'reading_accuracy' => 99,
            'comprehension_percentage' => 100,
            'final_reading_score' => 99.4,
            'completed_at' => now(),
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('advancedModule.unlocked', false)
                ->where('advancedModule.module', null)
                ->has('modules', 3)
            );

        $final->update([
            'reading_accuracy' => 100,
            'final_reading_score' => 100,
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('advancedModule.unlocked', true)
                ->where('advancedModule.module.key', 'advanced_module')
                ->where('rewards.advanced_stars', 0)
            );

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.start', $advanced))
            ->assertRedirect(route('learner.modules.overview', $advanced));

        $service = app(ModuleActivitySelectionService::class);
        $attempt = ModuleAttempt::where('learner_id', $learner->id)->where('module_id', $advanced->id)->firstOrFail();
        $items = $service->selectPracticeItemsForAttempt($attempt, 'comma_pause_reading', 1);
        $this->seedResolvedLessonResponses($attempt, $items, 1);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$advanced, 'comma_pause_reading']))
            ->assertRedirect(route('learner.modules.mastery-check', $advanced));

        $masteryItems = $service->selectMasteryItemsForAttempt($attempt, 1);
        $this->seedResolvedModuleResponses($attempt, $masteryItems, 1, true);
        $attempt->update(['status' => 'mastery_started']);

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.mastery-check.store', $advanced))
            ->assertRedirect(route('learner.modules.mastery-result', $advanced));

        $this->assertSame(0, LearnerReward::where('learner_id', $learner->id)->where('reward_type', 'star')->sum('amount'));
        $this->assertSame(1, LearnerReward::where('learner_id', $learner->id)->where('reward_type', 'advanced_star')->sum('amount'));

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('advancedModule.completed', true)
                ->where('rewards.advanced_stars', 1)
            );
    }

    public function test_continue_module_reuses_active_attempt_without_duplicate_attempt(): void
    {
        [$learner, $module] = $this->moduleContext('module_2');
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice_in_progress']);
        $this->seedModuleActivities($module, 'display_word_reading', 5, false);
        $this->seedRule($module, 'display_word_reading', 5, 0);
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
        $this->seedModuleActivities($module, 'display_word_reading', 5, false);
        $this->seedRule($module, 'display_word_reading', 5, 0);

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('learner.modules.activity.store', [$module, 'display_word_reading']), ['responses' => []])
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
                    'canonical_target' => $activityType.'-'.$index,
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

    private function seedResolvedLessonResponses(ModuleAttempt $attempt, $items, int $correctCount): void
    {
        $this->seedResolvedModuleResponses($attempt, $items, $correctCount, false);
    }

    private function seedResolvedModuleResponses(ModuleAttempt $attempt, $items, int $correctCount, bool $isMastery): void
    {
        foreach ($items->values() as $index => $item) {
            $isCorrect = $index < $correctCount;
            ModuleActivityResponse::create([
                'module_attempt_id' => $attempt->id,
                'module_activity_id' => $item->module_activity_id,
                'module_attempt_item_id' => $item->id,
                'response_text' => $isCorrect ? 'cat' : 'dog',
                'learner_answer' => $isCorrect ? 'cat' : 'dog',
                'expected_answer' => 'cat',
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? 1 : 0,
                'is_mastery_item' => $isMastery,
            ]);
            $item->update(['answered_at' => now()]);
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
                    'canonical_target' => $letter,
                    'target_letter' => $letter,
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
