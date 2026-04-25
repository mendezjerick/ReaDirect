<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\LlmPromptTemplate;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminFilterOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPromptTemplateController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureAdmin($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'prompt_type' => trim($request->string('prompt_type')->toString()),
            'agent_type' => trim($request->string('agent_type')->toString()),
            'status' => $request->string('status', 'all')->toString(),
        ];
        if (! in_array($filters['status'], ['all', 'draft', 'active', 'inactive'], true)) {
            $filters['status'] = 'all';
        }

        return Inertia::render('Admin/Prompts/Index', [
            'prompts' => LlmPromptTemplate::with('agentProfile')
                ->when($filters['prompt_type'], fn ($query) => $query->where('key', 'like', "%{$filters['prompt_type']}%"))
                ->when($filters['agent_type'], function ($query) use ($filters): void {
                    $types = $filters['agent_type'] === 'evaluator'
                        ? ['evaluator', AgentProfile::EVALUATOR_RECOMMENDATION]
                        : [$filters['agent_type']];
                    $query->whereHas('agentProfile', fn ($agent) => $agent->whereIn('agent_type', $types));
                })
                ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
                ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('key', 'like', "%{$filters['search']}%")
                    ->orWhere('template', 'like', "%{$filters['search']}%")))
                ->orderBy('key')
                ->orderByDesc('version')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'filterOptions' => [
                'promptTypes' => LlmPromptTemplate::query()->select('key')->distinct()->orderBy('key')->pluck('key')->map(fn ($key) => ['label' => $key, 'value' => $key])->values(),
                'agentTypes' => $options->agentTypeOptions(),
                'statuses' => $options->promptStatusOptions(),
            ],
        ]);
    }

    public function create(Request $request, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());

        return Inertia::render('Admin/Prompts/Form', ['prompt' => null, 'agents' => AgentProfile::orderBy('key')->get()]);
    }

    public function store(Request $request, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $prompt = LlmPromptTemplate::create($this->validated($request));
        $audit->log($request, 'admin.prompt.created', $prompt, [], $prompt->only(['key', 'version', 'status']));

        return redirect()->route('admin.prompts.show', $prompt)->with('success', 'Prompt template created.');
    }

    public function show(Request $request, LlmPromptTemplate $prompt, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Prompts/Show', ['prompt' => $prompt->load('agentProfile')]);
    }

    public function edit(Request $request, LlmPromptTemplate $prompt, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());

        return Inertia::render('Admin/Prompts/Form', ['prompt' => $prompt, 'agents' => AgentProfile::orderBy('key')->get()]);
    }

    public function update(Request $request, LlmPromptTemplate $prompt, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $validated = $this->validated($request);
        $old = $prompt->only(array_keys($validated));
        $prompt->update($validated);
        $audit->log($request, 'admin.prompt.updated', $prompt, $old, $validated);

        return redirect()->route('admin.prompts.show', $prompt)->with('success', 'Prompt template updated.');
    }

    public function history(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Prompts/History', [
            'prompts' => LlmPromptTemplate::with('agentProfile')->orderBy('key')->orderByDesc('version')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'agent_profile_id' => ['required', 'integer', 'exists:agent_profiles,id'],
            'key' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'max:255'],
            'template' => ['required', 'string'],
            'variables' => ['nullable'],
        ]);
        $validated['variables'] = $this->decodeJson($validated['variables'] ?? null);

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

        return is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
