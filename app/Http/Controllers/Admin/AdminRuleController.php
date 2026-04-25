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
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'rule_type' => trim($request->string('rule_type', 'all')->toString()) ?: 'all',
        ];
        if (! in_array($filters['rule_type'], ['all', 'crla_classification', 'reading_classification', 'module_placement', 'module_mastery'], true)) {
            $filters['rule_type'] = 'all';
        }
        $classificationRules = collect([
            ['type' => 'crla_classification', 'name' => 'CRLA classification', 'rule' => '0-10 Full Refresher, 11-16 Moderate Refresher, 17-26 Light Refresher, 27-30 Grade Ready'],
            ['type' => 'reading_classification', 'name' => 'Reading classification', 'rule' => 'Based only on final_reading_score: 0-25 Low Emerging, 26-50 High Emerging, 51-75 Developing, 76-90 Transitioning, 91-100 Grade Level'],
            ['type' => 'module_placement', 'name' => 'Module placement', 'rule' => 'Refresher levels go Module 1; Grade Ready uses final reading classification for Module 2/3/no module.'],
        ])
            ->when($filters['rule_type'] !== 'all' && $filters['rule_type'] !== 'module_mastery', fn ($rules) => $rules->where('type', $filters['rule_type']))
            ->when($filters['rule_type'] === 'module_mastery', fn ($rules) => $rules->where('type', '__none__'))
            ->when($filters['search'], fn ($rules) => $rules->filter(fn ($rule) => str_contains(strtolower($rule['name'].' '.$rule['rule']), strtolower($filters['search']))))
            ->values();

        $masteryThresholds = MasteryThreshold::with('module')
            ->when($filters['rule_type'] !== 'all' && $filters['rule_type'] !== 'module_mastery', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner
                ->where('decision', 'like', "%{$filters['search']}%")
                ->orWhere('rule_key', 'like', "%{$filters['search']}%")
                ->orWhereHas('module', fn ($module) => $module->where('title', 'like', "%{$filters['search']}%"))))
            ->orderBy('module_id')
            ->orderBy('min_score')
            ->get();

        return Inertia::render('Admin/Rules/Index', [
            'masteryThresholds' => $masteryThresholds,
            'classificationRules' => $classificationRules,
            'filters' => $filters,
            'filterOptions' => [
                'ruleTypes' => [
                    ['label' => 'All rules', 'value' => 'all'],
                    ['label' => 'CRLA classification', 'value' => 'crla_classification'],
                    ['label' => 'Reading classification', 'value' => 'reading_classification'],
                    ['label' => 'Module placement', 'value' => 'module_placement'],
                    ['label' => 'Module mastery', 'value' => 'module_mastery'],
                ],
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
