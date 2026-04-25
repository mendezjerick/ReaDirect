<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasteryThreshold;
use App\Models\Module;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRuleController extends Controller
{
    public function index(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Rules/Index', [
            'masteryThresholds' => MasteryThreshold::with('module')->orderBy('module_id')->orderBy('min_score')->get(),
            'classificationRules' => [
                ['name' => 'CRLA classification', 'rule' => '0-10 Full Refresher, 11-16 Moderate Refresher, 17-26 Light Refresher, 27-30 Grade Ready'],
                ['name' => 'Reading classification', 'rule' => 'Based only on final_reading_score: 0-25 Low Emerging, 26-50 High Emerging, 51-75 Developing, 76-90 Transitioning, 91-100 Grade Level'],
                ['name' => 'Module placement', 'rule' => 'Refresher levels go Module 1; Grade Ready uses final reading classification for Module 2/3/no module.'],
            ],
        ]);
    }

    public function show(Request $request, MasteryThreshold $rule, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Rules/Show', ['rule' => $rule->load('module')]);
    }

    public function edit(Request $request, MasteryThreshold $rule, AdminAccessService $access): Response
    {
        $access->ensureSystemAdmin($request->user());

        return Inertia::render('Admin/Rules/Form', ['rule' => $rule, 'modules' => Module::orderBy('sequence')->get()]);
    }

    public function update(Request $request, MasteryThreshold $rule, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureSystemAdmin($request->user());
        $validated = $request->validate([
            'min_score' => ['required', 'numeric', 'min:0'],
            'max_score' => ['nullable', 'numeric', 'min:0'],
            'decision' => ['required', 'string', 'max:255'],
            'next_module_key' => ['nullable', 'string', 'max:255'],
            'rule_key' => ['required', 'string', 'max:255'],
        ]);
        $old = $rule->only(array_keys($validated));
        $rule->update($validated);
        $audit->log($request, 'admin.rule.updated', $rule, $old, $validated);

        return redirect()->route('admin.rules.show', $rule)->with('success', 'Rule updated.');
    }

    public function history(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Rules/History', [
            'events' => \App\Models\AuditLog::where('action', 'like', 'admin.rule.%')->latest()->limit(100)->get(),
        ]);
    }
}
