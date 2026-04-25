<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminFilterOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSchoolController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureAdmin($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status' => $options->activeValue($request->string('status')->toString()),
            'district' => trim($request->string('district')->toString()),
            'division' => trim($request->string('division')->toString()),
        ];

        $schools = School::withCount(['classes', 'learners'])
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['district'], fn ($query) => $query->where('district', $filters['district']))
            ->when($filters['division'], fn ($query) => $query->where('division', $filters['division']))
            ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('district', 'like', "%{$filters['search']}%")
                ->orWhere('division', 'like', "%{$filters['search']}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Schools/Index', [
            'schools' => $schools,
            'filters' => $filters,
            'filterOptions' => [
                'statuses' => $options->statusOptions(),
                'districts' => School::query()->whereNotNull('district')->select('district')->distinct()->orderBy('district')->pluck('district')->map(fn ($district) => ['label' => $district, 'value' => $district])->values(),
                'divisions' => School::query()->whereNotNull('division')->select('division')->distinct()->orderBy('division')->pluck('division')->map(fn ($division) => ['label' => $division, 'value' => $division])->values(),
            ],
        ]);
    }

    public function create(Request $request, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());

        return Inertia::render('Admin/Schools/Form', ['school' => null]);
    }

    public function store(Request $request, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
        ]);

        $school = School::create($validated + ['is_active' => true]);
        $audit->log($request, 'admin.school.created', $school, [], $school->only(['name', 'district', 'division']));

        return redirect()->route('admin.schools.show', $school)->with('success', 'School created.');
    }

    public function show(Request $request, School $school, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());
        $school->loadCount(['classes', 'learners']);

        return Inertia::render('Admin/Schools/Show', [
            'school' => $school,
            'classes' => $school->classes()->withCount('learners')->orderBy('name')->get(),
            'learners' => $school->learners()->latest()->limit(10)->get(),
        ]);
    }

    public function edit(Request $request, School $school, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());

        return Inertia::render('Admin/Schools/Form', ['school' => $school]);
    }

    public function update(Request $request, School $school, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $school->only(array_keys($validated));
        $school->update($validated);
        $audit->log($request, 'admin.school.updated', $school, $old, $validated);

        return redirect()->route('admin.schools.show', $school)->with('success', 'School updated.');
    }

    public function deactivate(Request $request, School $school, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $school->update(['is_active' => false]);
        $audit->log($request, 'admin.school.deactivated', $school);

        return back()->with('success', 'School deactivated.');
    }

    public function reactivate(Request $request, School $school, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $school->update(['is_active' => true]);
        $audit->log($request, 'admin.school.reactivated', $school);

        return back()->with('success', 'School reactivated.');
    }
}
