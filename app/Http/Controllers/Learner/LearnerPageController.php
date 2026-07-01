<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Services\CielFocusModeService;
use App\Services\LearnerFlowService;
use App\Support\CurrentLearner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LearnerPageController extends Controller
{
    public function progress(LearnerFlowService $flow): Response|RedirectResponse
    {
        return $this->render('Learner/Progress', $flow);
    }

    public function rewards(LearnerFlowService $flow): Response|RedirectResponse
    {
        return $this->render('Learner/Rewards', $flow);
    }

    public function help(LearnerFlowService $flow): Response|RedirectResponse
    {
        return $this->render('Learner/Help', $flow);
    }

    private function render(string $page, LearnerFlowService $flow): Response|RedirectResponse
    {
        $learner = CurrentLearner::resolve(request(), true);

        if (! $learner) {
            return redirect()->route('learner.access')
                ->with('info', 'Enter your learner code to continue your reading journey.');
        }

        $latestAttempt = $learner->assessmentAttempts()
            ->where('attempt_type', 'diagnostic')
            ->where('status', LearnerFlowService::DIAGNOSTIC_COMPLETE)
            ->whereNotNull('completed_at')
            ->latest('id')
            ->first([
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
            ]);

        $focusMode = app(CielFocusModeService::class);

        return Inertia::render($page, [
            'learner' => $learner->only('public_id', 'first_name', 'learner_code', 'current_stage'),
            'latestAttempt' => $latestAttempt?->only(
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
            ),
            'flowState' => $this->safeFlowState($flow->state($learner)),
            'rewards' => [
                'stars' => $focusMode->starTotal($learner->id),
                'advanced_stars' => $focusMode->specialStarTotal($learner->id),
            ],
        ]);
    }

    private function safeFlowState(?array $flowState): ?array
    {
        if (! $flowState) {
            return null;
        }

        unset(
            $flowState['current_module_id'],
            $flowState['diagnostic']['attempt_id'],
            $flowState['module']['current_module_id'],
            $flowState['module']['active_attempt_id'],
            $flowState['final_reassessment']['attempt_id'],
        );

        return $flowState;
    }
}
