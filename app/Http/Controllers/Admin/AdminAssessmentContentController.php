<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningContent;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminAssessmentContentController extends Controller
{
    public function index(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());
        $search = $request->string('search')->toString();
        $type = $request->string('content_type')->toString();
        $items = LearningContent::query()
            ->when($type, fn ($query) => $query->where('content_type', $type))
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('title', 'like', "%{$search}%")->orWhere('prompt', 'like', "%{$search}%")))
            ->orderBy('content_type')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/AssessmentContent/Index', [
            'items' => $items,
            'filters' => ['search' => $search, 'content_type' => $type],
            'contentTypes' => LearningContent::query()->select('content_type')->distinct()->orderBy('content_type')->pluck('content_type'),
        ]);
    }

    public function create(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/AssessmentContent/Form', ['item' => null]);
    }

    public function store(Request $request, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $validated = $this->validated($request);
        $item = LearningContent::create($validated);
        $audit->log($request, 'admin.assessment_content.created', $item, [], $item->only(['content_type', 'title']));

        return redirect()->route('admin.assessment-content.show', $item)->with('success', 'Assessment content created.');
    }

    public function show(Request $request, LearningContent $assessmentContent, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/AssessmentContent/Show', [
            'item' => $assessmentContent,
            'usageCount' => DB::table('assessment_attempt_items')->where('learning_content_id', $assessmentContent->id)->count(),
        ]);
    }

    public function edit(Request $request, LearningContent $assessmentContent, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/AssessmentContent/Form', ['item' => $assessmentContent]);
    }

    public function update(Request $request, LearningContent $assessmentContent, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $validated = $this->validated($request);
        $old = $assessmentContent->only(array_keys($validated));
        $assessmentContent->update($validated);
        $audit->log($request, 'admin.assessment_content.updated', $assessmentContent, $old, $validated);

        return redirect()->route('admin.assessment-content.show', $assessmentContent)->with('success', 'Assessment content updated.');
    }

    public function deactivate(Request $request, LearningContent $assessmentContent, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $assessmentContent->update(['is_active' => false]);
        $audit->log($request, 'admin.assessment_content.deactivated', $assessmentContent);

        return back()->with('success', 'Assessment content deactivated.');
    }

    public function reactivate(Request $request, LearningContent $assessmentContent, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureAdmin($request->user());
        $assessmentContent->update(['is_active' => true]);
        $audit->log($request, 'admin.assessment_content.reactivated', $assessmentContent);

        return back()->with('success', 'Assessment content reactivated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'content_type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'prompt' => ['nullable', 'string'],
            'difficulty' => ['required', 'string', 'max:255'],
            'accepted_answers' => ['nullable'],
            'payload' => ['nullable'],
        ]);

        $validated['accepted_answers'] = $this->decodeJsonOrPipe($validated['accepted_answers'] ?? null);
        $validated['payload'] = $this->decodeJson($validated['payload'] ?? null);
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function decodeJsonOrPipe(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! filled($value)) {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode('|', (string) $value))));
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
