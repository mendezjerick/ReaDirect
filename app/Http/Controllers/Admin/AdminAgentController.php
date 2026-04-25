<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminFilterOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAgentController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureAdmin($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'agent_type' => trim($request->string('agent_type')->toString()),
            'status' => $options->activeValue($request->string('status')->toString()),
        ];
        $typeValues = collect($options->agentTypeOptions())->pluck('value')->all();
        if ($filters['agent_type'] && ! in_array($filters['agent_type'], $typeValues, true)) {
            $filters['agent_type'] = '';
        }

        return Inertia::render('Admin/Agents/Index', [
            'agents' => AgentProfile::query()
                ->when($filters['agent_type'], function ($query) use ($filters): void {
                    $types = $filters['agent_type'] === 'evaluator'
                        ? ['evaluator', AgentProfile::EVALUATOR_RECOMMENDATION]
                        : [$filters['agent_type']];
                    $query->whereIn('agent_type', $types);
                })
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('key', 'like', "%{$filters['search']}%")
                    ->orWhere('purpose', 'like', "%{$filters['search']}%")))
                ->orderBy('key')
                ->get(),
            'filters' => $filters,
            'filterOptions' => [
                'agentTypes' => $options->agentTypeOptions(),
                'statuses' => $options->statusOptions(),
            ],
        ]);
    }

    public function show(Request $request, AgentProfile $agent, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Agents/Show', [
            'agent' => $agent,
            'prompts' => \App\Models\LlmPromptTemplate::where('agent_profile_id', $agent->id)->orderBy('key')->orderByDesc('version')->get(),
        ]);
    }

    public function edit(Request $request, AgentProfile $agent, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());

        return Inertia::render('Admin/Agents/Form', ['agent' => $agent]);
    }

    public function update(Request $request, AgentProfile $agent, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string'],
            'sprite_path' => ['nullable', 'string', 'max:255'],
            'default_state' => ['nullable', 'string', 'max:255'],
            'voice_settings' => ['nullable'],
        ]);
        $validated['voice_settings'] = $this->decodeJson($validated['voice_settings'] ?? null) ?? [];
        $validated['is_active'] = $request->boolean('is_active', true);
        $old = $agent->only(array_keys($validated));
        $agent->update($validated);
        $audit->log($request, 'admin.agent.updated', $agent, $old, $validated);

        return redirect()->route('admin.agents.show', $agent)->with('success', 'Agent profile updated.');
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
