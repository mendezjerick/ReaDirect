<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Services\Assessment\FinalAssessmentComparisonService;
use App\Services\LearnerFlowService;
use App\Support\CurrentLearner;
use App\Support\LearnerStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnerCompletionController extends Controller
{
    private const MISS_VIVIAN_MESSAGE = 'You did a wonderful job completing your reading assessments. Thank you for trying your best.';

    private const MISS_CIEL_MESSAGE = 'I am proud of your practice. You worked hard and kept going. Great job!';

    private const MISS_ESTELLE_MESSAGE = 'Great job finishing your final reading check. You completed your reading journey.';

    public function show(Request $request, LearnerFlowService $flow, FinalAssessmentComparisonService $comparison): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        $attempt = $this->completedFinalAttempt($request, $flow, $learner);

        if (! $attempt) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'Finish your final reading check before viewing completion.');
        }

        $request->session()->put('final_assessment_attempt_id', $attempt->id);
        $attempt->loadMissing('baselineAssessment');
        $comparisonSummary = $attempt->comparison_summary ?: $comparison->compareAttempts($attempt->baselineAssessment, $attempt);

        return Inertia::render('Learner/Completion', [
            'learner' => $learner->only('first_name', 'learner_code', 'current_stage'),
            'resultSummary' => $this->resultSummary($attempt, $comparisonSummary),
            'agentMessages' => $this->agentMessages($comparisonSummary),
            'thankYouUrl' => route('learner.completion.thank-you'),
            'homeUrl' => route('welcome'),
        ]);
    }

    public function thankYou(Request $request, LearnerFlowService $flow): RedirectResponse
    {
        $learner = $this->learner($request);
        $stage = LearnerStage::normalize($learner->current_stage);
        $attempt = $this->completedFinalAttempt($request, $flow, $learner);

        if (! $attempt || ! in_array($stage, [LearnerStage::FINAL_REASSESSMENT_COMPLETED, LearnerStage::COMPLETED], true)) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'Finish your final reading check before completing your journey.');
        }

        if ($stage !== LearnerStage::COMPLETED) {
            $learner->update(['current_stage' => LearnerStage::COMPLETED]);
        }

        $request->session()->forget(['assessment_attempt_id', 'module_attempt_id']);

        return redirect()->route('welcome');
    }

    private function learner(Request $request): Learner
    {
        return CurrentLearner::require($request);
    }

    private function completedFinalAttempt(Request $request, LearnerFlowService $flow, Learner $learner): ?AssessmentAttempt
    {
        $attempt = $flow->resolveFinalAttempt($request, true);

        if ($attempt && $flow->isFinalComplete($attempt)) {
            return $attempt;
        }

        return AssessmentAttempt::with(['selectedItems', 'baselineAssessment'])
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->where('status', LearnerFlowService::FINAL_COMPLETE)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    private function resultSummary(AssessmentAttempt $attempt, array $comparison): array
    {
        $initial = $comparison['initial_scores'] ?? [];
        $final = $comparison['final_scores'] ?? [];
        $deltas = $comparison['deltas'] ?? [];

        return [
            'cards' => [
                $this->scoreCard('Initial Reading Check', $initial),
                $this->scoreCard('Final Reading Check', array_filter([
                    'crla_classification' => $final['crla_classification'] ?? $attempt->crla_classification,
                    'reading_classification' => $final['reading_classification'] ?? $attempt->reading_classification,
                    'final_reading_score' => $final['final_reading_score'] ?? $attempt->final_reading_score,
                    'reading_accuracy' => $final['reading_accuracy'] ?? $attempt->reading_accuracy,
                    'comprehension_percentage' => $final['comprehension_percentage'] ?? $attempt->comprehension_percentage,
                    'crla_total_score' => $final['crla_total_score'] ?? $attempt->crla_total_score,
                ], fn ($value) => $value !== null)),
                $this->progressCard($deltas, $comparison['summary'] ?? null),
            ],
            'fallbackMessage' => 'Your final reading check has been completed.',
            'completedAt' => $attempt->completed_at?->toDateString(),
        ];
    }

    private function scoreCard(string $title, array $scores): array
    {
        $metrics = [];

        foreach ([
            'crla_classification' => 'CRLA level',
            'reading_classification' => 'Reading level',
            'final_reading_score' => 'Reading score',
            'reading_accuracy' => 'Reading accuracy',
            'comprehension_percentage' => 'Understanding',
            'crla_total_score' => 'CRLA score',
        ] as $key => $label) {
            $value = $scores[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $metrics[] = [
                'label' => $label,
                'value' => $this->formatValue($key, $value),
            ];
        }

        return [
            'title' => $title,
            'kind' => str_starts_with($title, 'Final') ? 'final' : 'initial',
            'metrics' => $metrics,
        ];
    }

    private function progressCard(array $deltas, ?string $summary): array
    {
        $metrics = [];

        foreach ([
            'final_reading_score' => 'Reading score change',
            'reading_accuracy' => 'Accuracy change',
            'comprehension_percentage' => 'Understanding change',
            'crla_total_score' => 'CRLA score change',
        ] as $key => $label) {
            $value = $deltas[$key] ?? null;

            if ($value === null || ! is_numeric($value)) {
                continue;
            }

            $metrics[] = [
                'label' => $label,
                'value' => $this->formatDelta($key, (float) $value),
            ];
        }

        return [
            'title' => 'Progress',
            'kind' => 'progress',
            'metrics' => $metrics,
            'message' => $this->friendlySummary($summary, $metrics !== []),
        ];
    }

    private function agentMessages(array $comparison): array
    {
        $hasImprovement = collect($comparison['deltas'] ?? [])->contains(fn ($value) => is_numeric($value) && (float) $value > 0);
        $estelleResult = $hasImprovement
            ? 'You made progress from your first reading check to your final reading check.'
            : 'Here is your final result. It shows your reading check from the beginning and your final reading check.';

        return [
            [
                'agentType' => 'assessment',
                'name' => 'Miss Vivian',
                'role' => 'Assessment Guide',
                'message' => self::MISS_VIVIAN_MESSAGE,
            ],
            [
                'agentType' => 'coach_feedback',
                'name' => 'Miss Ciel',
                'role' => 'Reading Coach',
                'message' => self::MISS_CIEL_MESSAGE,
            ],
            [
                'agentType' => 'evaluator',
                'name' => 'Miss Estelle',
                'role' => 'Results Guide',
                'message' => self::MISS_ESTELLE_MESSAGE,
                'resultMessage' => $estelleResult,
            ],
        ];
    }

    private function friendlySummary(?string $summary, bool $hasMetrics): string
    {
        if (! $hasMetrics) {
            return 'Your final reading check has been completed.';
        }

        if ($summary === 'The learner improved in one or more final reassessment areas.') {
            return 'You made progress from your first reading check to your final reading check.';
        }

        return 'Your first reading check and final reading check are shown together here.';
    }

    private function formatValue(string $key, mixed $value): string
    {
        if (in_array($key, ['reading_accuracy', 'comprehension_percentage'], true) && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
        }

        return (string) $value;
    }

    private function formatDelta(string $key, float $value): string
    {
        $prefix = $value > 0 ? '+' : '';
        $formatted = rtrim(rtrim(number_format($value, 1), '0'), '.');
        $suffix = in_array($key, ['reading_accuracy', 'comprehension_percentage'], true) ? '%' : '';

        return $prefix.$formatted.$suffix;
    }
}
