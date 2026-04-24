<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\Module;
use App\Services\ModuleActivitySelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function index(Request $request): Response
    {
        $learner = $this->learner($request);
        $module = $this->currentOrPlacedModule($learner);

        return Inertia::render('Learner/Modules/ModuleIndex', [
            'module' => $module?->only('key', 'title', 'description'),
            'learnerStage' => $learner->current_stage,
        ]);
    }

    public function start(Request $request, Module $module, ModuleActivitySelectionService $selection): RedirectResponse
    {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);

        $request->session()->put('module_attempt_id', $attempt->id);

        $learner->update([
            'current_module_id' => $module->id,
            'current_stage' => 'module_practice',
        ]);

        return redirect()->route('learner.modules.overview', $module);
    }

    public function overview(Request $request, Module $module, ModuleActivitySelectionService $selection): Response
    {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $request->session()->put('module_attempt_id', $attempt->id);
        $activityTypes = $selection->practiceActivityTypes($module);

        return Inertia::render('Learner/Modules/ModuleOverview', [
            'module' => $module->only('key', 'title', 'description'),
            'activityTypes' => $activityTypes,
            'firstActivityType' => $activityTypes[0] ?? null,
        ]);
    }

    public function extraDrills(Request $request, Module $module): Response
    {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);

        return Inertia::render('Learner/Modules/ExtraDrills', [
            'module' => $module->only('key', 'title', 'description'),
        ]);
    }

    private function learner(Request $request): Learner
    {
        return Learner::find($request->session()->get('learner_id')) ?? Learner::firstOrFail();
    }

    private function currentOrPlacedModule(Learner $learner): ?Module
    {
        if ($learner->current_module_id) {
            return $learner->currentModule;
        }

        $placedAttempt = AssessmentAttempt::where('learner_id', $learner->id)
            ->whereNotNull('assigned_module_id')
            ->latest()
            ->first();

        if ($placedAttempt?->assigned_module_id) {
            $learner->update([
                'current_module_id' => $placedAttempt->assigned_module_id,
                'current_stage' => 'module_assigned',
            ]);

            return Module::find($placedAttempt->assigned_module_id);
        }

        return null;
    }

    private function authorizeModule(Learner $learner, Module $module): void
    {
        if ($learner->current_module_id && (int) $learner->current_module_id !== (int) $module->id) {
            abort(403);
        }
    }
}
