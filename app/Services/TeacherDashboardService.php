<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

class TeacherDashboardService
{
    public function __construct(private readonly TeacherAccessService $access)
    {
    }

    public function summaryFor(User $teacher): array
    {
        $learners = $this->access->learnersFor($teacher)->get();
        $learnerIds = $learners->pluck('id');
        $latestDiagnostics = $this->latestDiagnostics($learnerIds);
        $latestFinals = $this->latestFinalReassessments($learnerIds);
        $latestMastery = $this->latestMastery($learnerIds);

        return [
            'counts' => [
                'total_learners' => $learners->count(),
                'diagnostic_completed' => $latestDiagnostics->where('status', 'module_placement_completed')->count(),
                'diagnostic_pending' => $learners->count() - $latestDiagnostics->where('status', 'module_placement_completed')->count(),
                'ready_for_reassessment' => $learners->where('current_stage', 'final_reassessment_pending')->count(),
                'final_reassessment_completed' => $latestFinals->where('status', 'final_reassessment_completed')->count(),
            ],
            'moduleDistribution' => $this->moduleDistribution($learners),
            'crlaDistribution' => $this->distribution($latestDiagnostics, 'crla_classification'),
            'readingDistribution' => $this->distribution($latestDiagnostics, 'reading_classification'),
            'finalReadingDistribution' => $this->distribution($latestFinals, 'reading_classification'),
            'recentActivity' => $this->recentActivity($learnerIds),
            'learnersByModule' => $this->moduleDistribution($learners),
        ];
    }

    private function latestFinalReassessments(Collection $learnerIds): Collection
    {
        return AssessmentAttempt::whereIn('learner_id', $learnerIds)
            ->where('attempt_type', 'final_reassessment')
            ->latest()
            ->get()
            ->unique('learner_id')
            ->values();
    }

    private function latestDiagnostics(Collection $learnerIds): Collection
    {
        return AssessmentAttempt::whereIn('learner_id', $learnerIds)
            ->where('attempt_type', 'diagnostic')
            ->latest()
            ->get()
            ->unique('learner_id')
            ->values();
    }

    private function latestMastery(Collection $learnerIds): Collection
    {
        return ModuleAttempt::whereIn('learner_id', $learnerIds)
            ->latest()
            ->get()
            ->unique('learner_id')
            ->values();
    }

    private function moduleDistribution(Collection $learners): array
    {
        $labels = ['Module 1' => 0, 'Module 2' => 0, 'Module 3' => 0, 'No module needed' => 0];

        foreach ($learners as $learner) {
            $label = match ($learner->currentModule?->key) {
                'module_1' => 'Module 1',
                'module_2' => 'Module 2',
                'module_3' => 'Module 3',
                default => 'No module needed',
            };

            $labels[$label]++;
        }

        return $labels;
    }

    private function distribution(Collection $records, string $field): array
    {
        return $records
            ->filter(fn ($record) => filled($record->{$field}))
            ->groupBy($field)
            ->map(fn ($items) => $items->count())
            ->sortKeys()
            ->all();
    }

    private function recentActivity(Collection $learnerIds): array
    {
        $diagnostics = AssessmentAttempt::with('learner')
            ->whereIn('learner_id', $learnerIds)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (AssessmentAttempt $attempt) => [
                'learner' => trim($attempt->learner->first_name.' '.$attempt->learner->last_name),
                'activity' => 'Diagnostic '.$attempt->status,
                'date' => $attempt->updated_at?->toDateTimeString(),
            ]);

        $modules = ModuleAttempt::with(['learner', 'module'])
            ->whereIn('learner_id', $learnerIds)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ModuleAttempt $attempt) => [
                'learner' => trim($attempt->learner->first_name.' '.$attempt->learner->last_name),
                'activity' => ($attempt->module?->title ?? 'Module').' '.$attempt->status,
                'date' => $attempt->updated_at?->toDateTimeString(),
            ]);

        return $diagnostics->concat($modules)->sortByDesc('date')->take(8)->values()->all();
    }
}
