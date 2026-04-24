<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Learner;
use App\Services\LearnerProgressService;
use App\Services\TeacherAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherLearnerController extends Controller
{
    public function index(Request $request, TeacherAccessService $access): Response
    {
        $teacher = $request->user();
        $access->ensureTeacherArea($teacher);
        $learners = $access->learnersFor($teacher)->with(['assessmentAttempts', 'moduleAttempts', 'currentModule'])->get();
        $filters = $request->only(['search', 'class', 'module', 'crla', 'reading', 'diagnostic_status', 'mastery']);

        $rows = $learners->map(function (Learner $learner): array {
            $diagnostic = $learner->assessmentAttempts()->where('attempt_type', 'diagnostic')->latest()->first();
            $mastery = $learner->moduleAttempts()->latest()->first();

            return [
                'public_id' => $learner->public_id,
                'learner_code' => $learner->learner_code,
                'name' => trim($learner->first_name.' '.$learner->last_name),
                'class' => $learner->schoolClass?->name,
                'current_stage' => $learner->current_stage,
                'current_module' => $learner->currentModule?->title,
                'current_module_key' => $learner->currentModule?->key,
                'crla_level' => $diagnostic?->crla_classification,
                'reading_classification' => $diagnostic?->reading_classification,
                'diagnostic_status' => $diagnostic?->status ?? 'Diagnostic Pending',
                'latest_mastery_decision' => $mastery?->mastery_decision,
                'last_activity_date' => $this->latestDate($diagnostic?->updated_at, $mastery?->updated_at),
            ];
        });

        $rows = $this->applyFilters($rows, $filters)->values();

        return Inertia::render('Teacher/LearnerList', [
            'learners' => $rows,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Learner $learner, TeacherAccessService $access, LearnerProgressService $progress): Response
    {
        $access->authorizeLearner($request->user(), $learner);
        $this->audit($request, 'teacher.viewed_learner_detail', $learner);

        return Inertia::render('Teacher/LearnerDetail', $progress->detailFor($learner->load(['schoolClass', 'currentModule'])));
    }

    private function applyFilters($rows, array $filters)
    {
        return $rows
            ->when($filters['search'] ?? null, fn ($items, $search) => $items->filter(fn ($row) => str_contains(strtolower($row['name'].' '.$row['learner_code']), strtolower($search))))
            ->when($filters['class'] ?? null, fn ($items, $value) => $items->where('class', $value))
            ->when($filters['module'] ?? null, fn ($items, $value) => $items->where('current_module_key', $value))
            ->when($filters['crla'] ?? null, fn ($items, $value) => $items->where('crla_level', $value))
            ->when($filters['reading'] ?? null, fn ($items, $value) => $items->where('reading_classification', $value))
            ->when($filters['diagnostic_status'] ?? null, fn ($items, $value) => $items->where('diagnostic_status', $value))
            ->when($filters['mastery'] ?? null, fn ($items, $value) => $items->where('latest_mastery_decision', $value));
    }

    private function latestDate($first, $second): ?string
    {
        return collect([$first, $second])->filter()->sortDesc()->first()?->toDateTimeString();
    }

    private function audit(Request $request, string $action, Learner $learner): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => Learner::class,
            'auditable_id' => $learner->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
