<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminFilterOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminModuleContentController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureAdmin($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'module' => trim($request->string('module')->toString()),
            'activity_type' => trim($request->string('activity_type')->toString()),
            'is_mastery_item' => in_array($request->string('is_mastery_item')->toString(), ['all', 'practice', 'mastery'], true) ? $request->string('is_mastery_item')->toString() : 'all',
            'status' => $options->activeValue($request->string('status')->toString()),
        ];

        $moduleKeys = collect($options->moduleOptions())->pluck('value')->all();
        if ($filters['module'] && ! in_array($filters['module'], $moduleKeys, true)) {
            $filters['module'] = '';
        }

        $activityTypes = collect($options->moduleActivityTypeOptions())->pluck('value')->unique()->all();
        if ($filters['activity_type'] && ! in_array($filters['activity_type'], $activityTypes, true)) {
            $filters['activity_type'] = '';
        }

        $activities = ModuleActivity::with(['module', 'learningContent'])
            ->when($filters['module'], fn ($query) => $query->whereHas('module', fn ($module) => $module->where('key', $filters['module'])))
            ->when($filters['activity_type'], fn ($query) => $query->where('activity_type', $filters['activity_type']))
            ->when($filters['is_mastery_item'] === 'mastery', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('activity_type', 'mastery_check')
                ->orWhere('configuration->is_mastery_item', true)))
            ->when($filters['is_mastery_item'] === 'practice', fn ($query) => $query
                ->where('activity_type', '!=', 'mastery_check')
                ->where(fn ($inner) => $inner->whereNull('configuration->is_mastery_item')->orWhere('configuration->is_mastery_item', false)))
            ->when($filters['status'] === 'active', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('configuration->is_active', true)
                ->orWhere(fn ($fallback) => $fallback->whereNull('configuration->is_active')->whereHas('learningContent', fn ($content) => $content->where('is_active', true)))))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('configuration->is_active', false)
                ->orWhereHas('learningContent', fn ($content) => $content->where('is_active', false))))
            ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('activity_type', 'like', "%{$filters['search']}%")
                ->orWhereHas('module', fn ($module) => $module->where('title', 'like', "%{$filters['search']}%"))
                ->orWhereHas('learningContent', fn ($content) => $content->where('title', 'like', "%{$filters['search']}%")->orWhere('prompt', 'like', "%{$filters['search']}%"))))
            ->orderBy('module_id')
            ->orderBy('sequence')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/ModuleContent/Index', [
            'activities' => $activities,
            'modules' => Module::orderBy('sequence')->get(['id', 'key', 'title']),
            'filters' => $filters,
            'filterOptions' => [
                'modules' => $options->moduleOptions(),
                'activityTypes' => $options->moduleActivityTypeOptions(),
                'mastery' => [
                    ['label' => 'Practice and mastery', 'value' => 'all'],
                    ['label' => 'Practice only', 'value' => 'practice'],
                    ['label' => 'Mastery only', 'value' => 'mastery'],
                ],
                'statuses' => $options->statusOptions(),
            ],
        ]);
    }

    public function create(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/ModuleContent/Form', ['activity' => null, 'modules' => Module::orderBy('sequence')->get()]);
    }

    public function store(Request $request, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $activity = ModuleActivity::create($this->validated($request));
        $audit->log($request, 'admin.module_content.created', $activity, [], $activity->only(['module_id', 'activity_type', 'title']));

        return redirect()->route('admin.module-content.show', $activity)->with('success', 'Module activity created.');
    }

    public function show(Request $request, ModuleActivity $moduleContent, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/ModuleContent/Show', [
            'activity' => $moduleContent->load('module', 'learningContent'),
            'usageCount' => $moduleContent->attemptItems()->count(),
        ]);
    }

    public function edit(Request $request, ModuleActivity $moduleContent, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/ModuleContent/Form', ['activity' => $moduleContent, 'modules' => Module::orderBy('sequence')->get()]);
    }

    public function update(Request $request, ModuleActivity $moduleContent, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $validated = $this->validated($request);
        $old = $moduleContent->only(array_keys($validated));
        $moduleContent->update($validated);
        $audit->log($request, 'admin.module_content.updated', $moduleContent, $old, $validated);

        return redirect()->route('admin.module-content.show', $moduleContent)->with('success', 'Module activity updated.');
    }

    public function deactivate(Request $request, ModuleActivity $moduleContent, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $configuration = $moduleContent->configuration ?? [];
        $configuration['is_active'] = false;
        $moduleContent->update(['configuration' => $configuration]);
        $audit->log($request, 'admin.module_content.deactivated', $moduleContent);

        return back()->with('success', 'Module activity deactivated.');
    }

    public function reactivate(Request $request, ModuleActivity $moduleContent, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $configuration = $moduleContent->configuration ?? [];
        $configuration['is_active'] = true;
        $moduleContent->update(['configuration' => $configuration]);
        $audit->log($request, 'admin.module_content.reactivated', $moduleContent);

        return back()->with('success', 'Module activity reactivated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'sequence' => ['required', 'integer', 'min:1'],
            'activity_type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'configuration' => ['nullable'],
        ]);

        $validated['configuration'] = $this->decodeJson($validated['configuration'] ?? null) ?? [];
        $validated['configuration']['is_active'] = $request->boolean('is_active', $validated['configuration']['is_active'] ?? true);

        return $validated;
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! filled($value)) {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : ['raw' => (string) $value];
    }
}
