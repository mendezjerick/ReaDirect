<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\ModuleAttempt;
use App\Models\User;

class ClassAnalyticsService
{
    public function __construct(private readonly TeacherAccessService $access)
    {
    }

    public function analyticsFor(User $teacher): array
    {
        $learners = $this->access->learnersFor($teacher)->get();
        $learnerIds = $learners->pluck('id');
        $diagnostics = AssessmentAttempt::whereIn('learner_id', $learnerIds)
            ->where('attempt_type', 'diagnostic')
            ->where('is_sandbox', false)
            ->latest()
            ->get()
            ->unique('learner_id')
            ->values();
        $mastery = ModuleAttempt::with(['module', 'learner'])
            ->whereIn('learner_id', $learnerIds)
            ->where('is_sandbox', false)
            ->latest()
            ->get();

        return [
            'moduleDistribution' => $learners->groupBy(fn ($learner) => $learner->currentModule?->title ?? 'No module needed')->map->count()->all(),
            'crlaDistribution' => $diagnostics->filter(fn ($item) => filled($item->crla_classification))->groupBy('crla_classification')->map->count()->all(),
            'readingDistribution' => $diagnostics->filter(fn ($item) => filled($item->reading_classification))->groupBy('reading_classification')->map->count()->all(),
            'averageCrlaTotalScore' => round((float) $diagnostics->avg('crla_total_score'), 2),
            'averageFinalReadingScore' => round((float) $diagnostics->avg('final_reading_score'), 2),
            'moduleNeeds' => [
                'module_1' => $learners->where('currentModule.key', 'module_1')->count(),
                'module_2' => $learners->where('currentModule.key', 'module_2')->count(),
                'module_3' => $learners->where('currentModule.key', 'module_3')->count(),
                'none' => $learners->whereNull('current_module_id')->count(),
            ],
            'recentMasteryOutcomes' => $mastery->take(10)->map(fn (ModuleAttempt $attempt) => [
                'learner' => trim($attempt->learner?->first_name.' '.$attempt->learner?->last_name),
                'module' => $attempt->module?->title,
                'score' => $attempt->score,
                'decision' => $attempt->mastery_decision,
                'date' => $attempt->updated_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }
}
