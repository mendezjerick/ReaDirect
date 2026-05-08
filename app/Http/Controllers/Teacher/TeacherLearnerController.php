<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\LearnerProgressService;
use App\Services\TeacherAccessService;
use App\Support\LearnerStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function create(Request $request, TeacherAccessService $access): Response
    {
        $teacher = $request->user();
        $access->ensureTeacherArea($teacher);

        return Inertia::render('Teacher/LearnerForm', [
            'classes' => $this->classOptions($teacher),
        ]);
    }

    public function store(Request $request, TeacherAccessService $access): RedirectResponse
    {
        $teacher = $request->user();
        $access->ensureTeacherArea($teacher);
        $classIds = $this->allowedClassIds($teacher);

        $validated = $request->validate([
            'class_id' => ['required', 'integer', Rule::in($classIds)],
            'learner_code' => ['nullable', 'string', 'max:255', 'unique:learners,learner_code'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'grade_level' => ['required', 'string', 'max:50'],
        ]);

        $class = SchoolClass::query()->whereKey($validated['class_id'])->firstOrFail();
        $learner = Learner::create([
            'school_id' => $class->school_id,
            'class_id' => $class->id,
            'learner_code' => $validated['learner_code'] ?: 'LRN-'.Str::upper(Str::random(8)),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'grade_level' => $validated['grade_level'],
            'current_stage' => LearnerStage::NEW,
            'is_active' => true,
        ]);

        $this->audit($request, 'teacher.learner.created', $learner);

        return redirect()->route('teacher.learners.show', $learner)->with('success', 'Learner created.');
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

    private function classOptions(User $teacher)
    {
        $query = $teacher->hasRole('system_admin')
            ? SchoolClass::query()
            : $teacher->teachingClasses();

        return $query->with('school')
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'grade_level' => $class->grade_level,
                'school' => [
                    'id' => $class->school?->id,
                    'name' => $class->school?->name,
                ],
            ])
            ->values();
    }

    private function allowedClassIds(User $teacher): array
    {
        $query = $teacher->hasRole('system_admin')
            ? SchoolClass::query()
            : $teacher->teachingClasses();

        return $query->pluck('classes.id')->map(fn ($id) => (int) $id)->all();
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
