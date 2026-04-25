<?php

namespace App\Services\Admin;

use App\Models\AssessmentAttempt;
use App\Models\AuditLog;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\LlmInteraction;
use App\Models\ModuleAttempt;
use App\Models\School;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminDashboardService
{
    public function summary(): array
    {
        $realAssessments = AssessmentAttempt::query()->where('is_sandbox', false);
        $metrics = [
            'schools' => School::count(),
            'teachers' => $this->roleCount('teacher'),
            'learners' => Learner::count(),
            'active_learners' => Learner::where('is_active', true)->count(),
            'completed_diagnostics' => (clone $realAssessments)->where('attempt_type', 'diagnostic')->where('status', 'module_placement_completed')->count(),
            'completed_final_reassessments' => (clone $realAssessments)->where('attempt_type', 'final_reassessment')->where('status', 'final_reassessment_completed')->count(),
            'sandbox_attempts' => AssessmentAttempt::where('is_sandbox', true)->count() + ModuleAttempt::where('is_sandbox', true)->count(),
        ];

        return [
            'metrics' => $metrics,
            'counts' => $metrics,
            'moduleDistribution' => Learner::with('currentModule')->get()
                ->groupBy(fn (Learner $learner) => $learner->currentModule?->title ?? 'No module needed')
                ->map->count()
                ->all(),
            'crlaDistribution' => (clone $realAssessments)->whereNotNull('crla_classification')->get()->groupBy('crla_classification')->map->count()->all(),
            'readingDistribution' => (clone $realAssessments)->whereNotNull('reading_classification')->get()->groupBy('reading_classification')->map->count()->all(),
            'recentAssessmentActivity' => AssessmentAttempt::with('learner')->latest()->limit(8)->get()->map(fn (AssessmentAttempt $attempt) => [
                'learner' => $attempt->learner?->learner_code,
                'type' => $attempt->attempt_type,
                'status' => $attempt->status,
                'sandbox' => $attempt->is_sandbox ? 'Yes' : 'No',
                'date' => $attempt->updated_at?->toDateTimeString(),
            ])->all(),
            'recentModuleActivity' => ModuleAttempt::with(['learner', 'module'])->latest()->limit(8)->get()->map(fn (ModuleAttempt $attempt) => [
                'learner' => $attempt->learner?->learner_code,
                'module' => $attempt->module?->title,
                'status' => $attempt->status,
                'sandbox' => $attempt->is_sandbox ? 'Yes' : 'No',
                'date' => $attempt->updated_at?->toDateTimeString(),
            ])->all(),
            'recentSttFailures' => AudioFile::whereNotNull('stt_error')->latest()->limit(5)->get()->map(fn (AudioFile $file) => [
                'audio' => $file->public_id,
                'context' => $file->recording_context,
                'error' => $file->stt_error,
                'date' => $file->updated_at?->toDateTimeString(),
            ])->all(),
            'recentLlmFallbacks' => class_exists(LlmInteraction::class)
                ? LlmInteraction::where('fallback_used', true)->latest()->limit(5)->get(['model', 'source_type', 'safety_status', 'created_at'])->toArray()
                : [],
            'recentAdminActions' => AuditLog::latest()->limit(8)->get()->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'entity' => class_basename((string) $log->auditable_type),
                'date' => $log->created_at?->toDateTimeString(),
            ])->all(),
            'systemHealth' => [
                'database' => config('database.default'),
                'queue' => config('queue.default'),
                'environment' => app()->environment(),
            ],
        ];
    }

    private function roleCount(string $role): int
    {
        if (! Role::where('name', $role)->where('guard_name', 'web')->exists()) {
            return 0;
        }

        return User::role($role)->count();
    }
}
