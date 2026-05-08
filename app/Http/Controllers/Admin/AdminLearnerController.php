<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Module;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminFilterOptionsService;
use App\Services\LearnerProgressService;
use App\Support\LearnerStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminLearnerController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureAdmin($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'school_id' => trim($request->string('school_id')->toString()),
            'class_id' => trim($request->string('class_id')->toString()),
            'status' => $options->activeValue($request->string('status')->toString()),
            'current_module' => trim($request->string('current_module')->toString()),
            'crla_level' => trim($request->string('crla_level')->toString()),
            'reading_classification' => trim($request->string('reading_classification')->toString()),
            'diagnostic_status' => trim($request->string('diagnostic_status')->toString()),
            'final_status' => trim($request->string('final_status')->toString()),
        ];
        $moduleKeys = collect($options->moduleOptions())->pluck('value')->all();
        if ($filters['current_module'] && ! in_array($filters['current_module'], $moduleKeys, true)) {
            $filters['current_module'] = '';
        }

        $learners = Learner::with(['school', 'schoolClass', 'currentModule'])
            ->when($filters['school_id'], fn ($query) => $query->where('school_id', $filters['school_id']))
            ->when($filters['class_id'], fn ($query) => $query->where('class_id', $filters['class_id']))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['current_module'], fn ($query) => $query->whereHas('currentModule', fn ($module) => $module->where('key', $filters['current_module'])))
            ->when($filters['crla_level'], fn ($query) => $query->whereHas('assessmentAttempts', fn ($attempt) => $attempt
                ->where('attempt_type', 'diagnostic')
                ->where('is_sandbox', false)
                ->where('crla_classification', $filters['crla_level'])))
            ->when($filters['reading_classification'], fn ($query) => $query->whereHas('assessmentAttempts', fn ($attempt) => $attempt
                ->where('is_sandbox', false)
                ->where('reading_classification', $filters['reading_classification'])))
            ->when($filters['diagnostic_status'], fn ($query) => $query->whereHas('assessmentAttempts', fn ($attempt) => $attempt
                ->where('attempt_type', 'diagnostic')
                ->where('is_sandbox', false)
                ->where('status', $filters['diagnostic_status'])))
            ->when($filters['final_status'], fn ($query) => $query->whereHas('assessmentAttempts', fn ($attempt) => $attempt
                ->where('attempt_type', 'final_reassessment')
                ->where('is_sandbox', false)
                ->where('status', $filters['final_status'])))
            ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner
                ->where('learner_code', 'like', "%{$filters['search']}%")
                ->orWhere('first_name', 'like', "%{$filters['search']}%")
                ->orWhere('last_name', 'like', "%{$filters['search']}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Learners/Index', [
            'learners' => $learners,
            'filters' => $filters,
            'filterOptions' => [
                'schools' => $options->schoolOptions(),
                'classes' => $options->classOptions(),
                'modules' => $options->moduleOptions(),
                'statuses' => $options->statusOptions(),
                'crlaLevels' => ['Full Refresher', 'Moderate Refresher', 'Light Refresher', 'Grade Ready'],
                'readingClassifications' => ['Low Emerging Reader', 'High Emerging Reader', 'Developing Reader', 'Transitioning Reader', 'Reading at Grade Level'],
                'diagnosticStatuses' => ['in_progress', 'task_1', 'task_1_completed', 'task_2_completed', 'module_placement_completed'],
                'finalStatuses' => ['final_reassessment_started', 'final_reassessment_completed'],
            ],
        ]);
    }

    public function create(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Learners/Form', $this->formData(null));
    }

    public function store(Request $request, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $validated = $this->validateLearner($request);
        $validated['learner_code'] = $validated['learner_code'] ?: 'LRN-'.Str::upper(Str::random(8));
        $validated['current_stage'] = $validated['current_stage'] ?: LearnerStage::NEW;
        $learner = Learner::create($validated + [
            'is_active' => true,
        ]);
        $audit->log($request, 'admin.learner.created', $learner, [], $learner->only(['learner_code', 'school_id', 'class_id']));

        return redirect()->route('admin.learners.show', $learner)->with('success', 'Learner created.');
    }

    public function show(Request $request, Learner $learner, AdminAccessService $access, LearnerProgressService $progress): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Learners/Show', [
            'learner' => $learner->load(['school', 'schoolClass', 'currentModule']),
            'progress' => $progress->detailFor($learner),
            'testingUrl' => route('admin.testing.learner-jump', $learner),
        ]);
    }

    public function edit(Request $request, Learner $learner, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Learners/Form', $this->formData($learner));
    }

    public function update(Request $request, Learner $learner, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $validated = $this->validateLearner($request, $learner);
        $old = $learner->only(array_keys($validated));
        $learner->update($validated);
        $audit->log($request, 'admin.learner.updated', $learner, $old, $validated);

        return redirect()->route('admin.learners.show', $learner)->with('success', 'Learner updated.');
    }

    public function deactivate(Request $request, Learner $learner, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $learner->update(['is_active' => false]);
        $audit->log($request, 'admin.learner.deactivated', $learner);

        return back()->with('success', 'Learner deactivated.');
    }

    public function reactivate(Request $request, Learner $learner, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $learner->update(['is_active' => true]);
        $audit->log($request, 'admin.learner.reactivated', $learner);

        return back()->with('success', 'Learner reactivated.');
    }

    private function validateLearner(Request $request, ?Learner $learner = null): array
    {
        return $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'current_module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'learner_code' => ['nullable', 'string', 'max:255', 'unique:learners,learner_code,'.($learner?->id ?? 'NULL')],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'grade_level' => ['required', 'string', 'max:50'],
            'current_stage' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function formData(?Learner $learner): array
    {
        return [
            'learner' => $learner,
            'schools' => School::orderBy('name')->get(['id', 'name']),
            'classes' => SchoolClass::with('school')->orderBy('name')->get(),
            'modules' => Module::orderBy('sequence')->get(['id', 'key', 'title']),
        ];
    }
}
