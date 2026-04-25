<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminFilterOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminTeacherController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureAdmin($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'school_id' => trim($request->string('school_id')->toString()),
            'status' => $options->activeValue($request->string('status')->toString()),
            'role' => trim($request->string('role', 'teacher')->toString()) ?: 'teacher',
        ];
        $roleValues = collect($options->roleOptions())->pluck('value')->all();
        if ($filters['role'] !== 'all' && ! in_array($filters['role'], $roleValues, true)) {
            $filters['role'] = 'teacher';
        }

        $teachers = User::query()
            ->when($filters['role'] !== 'all', fn ($query) => $query->whereHas('roles', fn ($role) => $role->where('name', $filters['role'])))
            ->withCount('teachingClasses')
            ->when($filters['school_id'], fn ($query) => $query->whereHas('teachingClasses', fn ($class) => $class->where('school_id', $filters['school_id'])))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$filters['search']}%")->orWhere('email', 'like', "%{$filters['search']}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $filters,
            'filterOptions' => [
                'schools' => $options->schoolOptions(),
                'statuses' => $options->statusOptions(),
                'roles' => array_merge([['label' => 'All roles', 'value' => 'all']], $options->roleOptions()),
            ],
        ]);
    }

    public function create(Request $request, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());

        return Inertia::render('Admin/Teachers/Form', [
            'teacher' => null,
            'schools' => School::orderBy('name')->get(['id', 'name']),
            'classes' => SchoolClass::with('school')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $teacher = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::password(16)),
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');
        SchoolClass::whereIn('id', $validated['class_ids'] ?? [])->update(['teacher_id' => $teacher->id]);
        $audit->log($request, 'admin.teacher.created', $teacher, [], ['email' => $teacher->email]);

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Teacher account created. Use password reset before sharing access.');
    }

    public function show(Request $request, User $teacher, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());
        abort_unless($teacher->hasRole('teacher'), 404);

        return Inertia::render('Admin/Teachers/Show', [
            'teacher' => $teacher->load('teachingClasses.school'),
            'classes' => $teacher->teachingClasses()->withCount('learners')->get(),
        ]);
    }

    public function edit(Request $request, User $teacher, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());
        abort_unless($teacher->hasRole('teacher'), 404);

        return Inertia::render('Admin/Teachers/Form', [
            'teacher' => $teacher->load('teachingClasses'),
            'schools' => School::orderBy('name')->get(['id', 'name']),
            'classes' => SchoolClass::with('school')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $teacher, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        abort_unless($teacher->hasRole('teacher'), 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$teacher->id],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $old = $teacher->only(['name', 'email']);
        $teacher->update(['name' => $validated['name'], 'email' => $validated['email']]);
        SchoolClass::where('teacher_id', $teacher->id)->update(['teacher_id' => null]);
        SchoolClass::whereIn('id', $validated['class_ids'] ?? [])->update(['teacher_id' => $teacher->id]);
        $audit->log($request, 'admin.teacher.updated', $teacher, $old, $teacher->only(['name', 'email']));

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Teacher updated.');
    }

    public function deactivate(Request $request, User $teacher, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $teacher->update(['is_active' => false]);
        $audit->log($request, 'admin.teacher.deactivated', $teacher);

        return back()->with('success', 'Teacher deactivated.');
    }

    public function reactivate(Request $request, User $teacher, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $teacher->update(['is_active' => true]);
        $audit->log($request, 'admin.teacher.reactivated', $teacher);

        return back()->with('success', 'Teacher reactivated.');
    }
}
