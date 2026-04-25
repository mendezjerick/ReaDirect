<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherReportService
{
    public function __construct(
        private readonly TeacherAccessService $access,
        private readonly LearnerProgressService $progress
    ) {
    }

    public function reportsIndex(User $teacher): array
    {
        return [
            ['type' => 'learner_diagnostic', 'title' => 'Learner diagnostic summary', 'description' => 'CRLA and reading comprehension scores by learner.'],
            ['type' => 'learner_module_progress', 'title' => 'Learner module progress summary', 'description' => 'Module attempts, mastery decisions, and scores.'],
            ['type' => 'learner_full_progress', 'title' => 'Learner full progress summary', 'description' => 'Combined diagnostic and module progress view.'],
            ['type' => 'learner_final_comparison', 'title' => 'Learner final comparison', 'description' => 'Initial diagnostic and final reassessment side-by-side.'],
            ['type' => 'class_summary', 'title' => 'Class summary report', 'description' => 'Class-level distribution and placement report.'],
        ];
    }

    public function learnerDiagnosticCsv(User $teacher, Learner $learner): StreamedResponse
    {
        $this->access->authorizeLearner($teacher, $learner);
        $attempt = AssessmentAttempt::where('learner_id', $learner->id)->where('attempt_type', 'diagnostic')->latest()->first();

        $rows = [[
            'Learner Code', 'Learner Name', 'Status', 'Task 1', 'Task 2A', 'Task 2B',
            'CRLA Total', 'CRLA Level', 'Reading Accuracy', 'Comprehension %',
            'Final Reading Score', 'Reading Classification', 'Rule Applied',
        ]];

        $rows[] = [
            $learner->learner_code,
            trim($learner->first_name.' '.$learner->last_name),
            $attempt?->status,
            $attempt?->task_1_score,
            $attempt?->task_2a_score,
            $attempt?->task_2b_score,
            $attempt?->crla_total_score,
            $attempt?->crla_classification,
            $attempt?->reading_accuracy,
            $attempt?->comprehension_percentage,
            $attempt?->final_reading_score,
            $attempt?->reading_classification,
            $attempt?->rule_applied,
        ];

        return $this->csv('learner-diagnostic-'.$learner->learner_code.'.csv', $rows);
    }

    public function learnerModuleProgressCsv(User $teacher, Learner $learner): StreamedResponse
    {
        $this->access->authorizeLearner($teacher, $learner);
        $attempts = ModuleAttempt::with('module')->where('learner_id', $learner->id)->latest()->get();

        $rows = [['Learner Code', 'Learner Name', 'Module', 'Status', 'Score', 'Mastery Decision', 'Rule Applied', 'Completed At']];

        foreach ($attempts as $attempt) {
            $rows[] = [
                $learner->learner_code,
                trim($learner->first_name.' '.$learner->last_name),
                $attempt->module?->title,
                $attempt->status,
                $attempt->score,
                $attempt->mastery_decision,
                $attempt->rule_applied,
                $attempt->completed_at?->toDateTimeString(),
            ];
        }

        return $this->csv('learner-module-progress-'.$learner->learner_code.'.csv', $rows);
    }

    public function learnerFullProgressCsv(User $teacher, Learner $learner): StreamedResponse
    {
        $this->access->authorizeLearner($teacher, $learner);
        $detail = $this->progress->detailFor($learner);

        $rows = [['Section', 'Metric', 'Value']];
        foreach (($detail['diagnosticSummary'] ?? []) as $key => $value) {
            $rows[] = ['Diagnostic', $key, $value];
        }
        foreach (($detail['readingSummary'] ?? []) as $key => $value) {
            $rows[] = ['Reading', $key, $value];
        }
        foreach ($detail['moduleProgress'] as $attempt) {
            $rows[] = ['Module', ($attempt['module'] ?? 'Module').' '.$attempt['status'], $attempt['score'] ?? $attempt['mastery_decision']];
        }

        return $this->csv('learner-full-progress-'.$learner->learner_code.'.csv', $rows);
    }

    public function learnerFinalComparisonCsv(User $teacher, Learner $learner): StreamedResponse
    {
        $this->access->authorizeLearner($teacher, $learner);
        $initial = AssessmentAttempt::where('learner_id', $learner->id)->where('attempt_type', 'diagnostic')->latest()->first();
        $final = AssessmentAttempt::where('learner_id', $learner->id)->where('attempt_type', 'final_reassessment')->latest()->first();
        $comparison = $final?->comparison_summary ?? [];

        $rows = [['Metric', 'Initial', 'Final', 'Delta', 'Percent Change']];

        foreach (['task_1_score', 'task_2a_score', 'task_2b_score', 'crla_total_score', 'reading_accuracy', 'comprehension_percentage', 'final_reading_score'] as $metric) {
            $rows[] = [
                $metric,
                $initial?->{$metric},
                $final?->{$metric},
                $comparison['deltas'][$metric] ?? null,
                $comparison['percent_change'][$metric] ?? null,
            ];
        }

        return $this->csv('learner-final-comparison-'.$learner->learner_code.'.csv', $rows);
    }

    public function classSummaryCsv(User $teacher): StreamedResponse
    {
        $learners = $this->access->learnersFor($teacher)->with(['currentModule', 'assessmentAttempts', 'moduleAttempts'])->get();
        $rows = [['Learner Code', 'Learner Name', 'Class', 'Current Stage', 'Current Module', 'CRLA Level', 'Reading Classification', 'Latest Mastery Decision']];

        foreach ($learners as $learner) {
            $diagnostic = $learner->assessmentAttempts()->where('attempt_type', 'diagnostic')->latest()->first();
            $mastery = $learner->moduleAttempts()->latest()->first();
            $rows[] = [
                $learner->learner_code,
                trim($learner->first_name.' '.$learner->last_name),
                $learner->schoolClass?->name,
                $learner->current_stage,
                $learner->currentModule?->title,
                $diagnostic?->crla_classification,
                $diagnostic?->reading_classification,
                $mastery?->mastery_decision,
            ];
        }

        return $this->csv('class-summary.csv', $rows);
    }

    private function csv(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
