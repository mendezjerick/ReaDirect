<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Module;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\LearnerProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminLearnerController extends Controller
{
    public function index(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());
        $search = $request->string('search')->toString();
        $learners = Learner::with(['school', 'schoolClass', 'currentModule'])
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('learner_code', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Learners/Index', ['learners' => $learners, 'filters' => ['search' => $search]]);
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
        $learner = Learner::create($validated + [
            'learner_code' => $validated['learner_code'] ?: 'LRN-'.Str::upper(Str::random(8)),
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
