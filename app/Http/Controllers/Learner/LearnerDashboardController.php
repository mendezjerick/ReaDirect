<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Module;
use App\Services\LearnerFlowService;
use App\Support\CurrentLearner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LearnerDashboardController extends Controller
{
    public function __invoke(LearnerFlowService $flow): Response|RedirectResponse
    {
        $learner = CurrentLearner::resolve(request(), true);

        if (! $learner) {
            return redirect()->route('learner.access')
                ->with('info', 'Enter your learner code to continue your reading journey.');
        }

        $flowState = $learner ? $flow->state($learner) : null;
        $latestAttempt = $learner?->assessmentAttempts()
            ->where('attempt_type', 'diagnostic')
            ->where('status', LearnerFlowService::DIAGNOSTIC_COMPLETE)
            ->whereNotNull('completed_at')
            ->latest('id')
            ->first([
                'id',
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
                'assigned_module_id',
            ]);
        $latestFinalAttempt = $learner?->assessmentAttempts()
            ->where('attempt_type', 'final_reassessment')
            ->latest('id')
            ->first([
                'id',
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
                'completed_at',
            ]);

        return Inertia::render('Learner/Dashboard', [
            'learner' => $learner ? array_merge(
                $learner->only('public_id', 'first_name', 'learner_code', 'current_stage'),
                [
                    'current_module' => $learner->currentModule?->only('key', 'sequence', 'title', 'description'),
                ]
            ) : null,
            'modules' => Module::query()->orderBy('sequence')->get(['key', 'sequence', 'title', 'description']),
            'latestAttempt' => $latestAttempt?->only(
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
                'assigned_module_id',
            ),
            'latestFinalAttempt' => $latestFinalAttempt?->only(
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
                'completed_at',
            ),
            'flowState' => $this->safeFlowState($flowState),
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
