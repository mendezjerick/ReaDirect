<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\Module;
use App\Services\LearnerFlowService;
use App\Services\ModuleActivitySelectionService;
use App\Support\LearnerStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function index(Request $request, LearnerFlowService $flow): Response
    {
        $learner = $this->learner($request);
        $module = $this->currentOrPlacedModule($learner);
        $flowState = $flow->state($learner);

        return Inertia::render('Learner/Modules/ModuleIndex', [
            'module' => $module?->only('key', 'title', 'description'),
            'learnerStage' => $learner->current_stage,
            'flowState' => $flowState,
        ]);
    }

    public function start(Request $request, Module $module, ModuleActivitySelectionService $selection, LearnerFlowService $flow): RedirectResponse
    {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            return $redirect;
        }

        $attempt = $flow->resolveModuleAttempt($request, $learner, $module) ?? $selection->startOrResumeModuleAttempt($learner, $module);

        $request->session()->put('module_attempt_id', $attempt->id);

        $learner->update([
            'current_module_id' => $module->id,
            'current_stage' => LearnerStage::MODULE_PRACTICE_IN_PROGRESS,
        ]);

        return redirect()->route('learner.modules.overview', $module);
    }

    public function overview(Request $request, Module $module, ModuleActivitySelectionService $selection, LearnerFlowService $flow): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            return $redirect;
        }

        $attempt = $flow->resolveModuleAttempt($request, $learner, $module) ?? $selection->startOrResumeModuleAttempt($learner, $module);
        $request->session()->put('module_attempt_id', $attempt->id);
        $activityTypes = $selection->practiceActivityTypes($module);

        return Inertia::render('Learner/Modules/ModuleOverview', [
            'module' => $module->only('key', 'title', 'description'),
            'activityTypes' => $activityTypes,
            'firstActivityType' => $activityTypes[0] ?? null,
        ]);
    }

    public function extraDrills(Request $request, Module $module, LearnerFlowService $flow): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            return $redirect;
        }

        if (LearnerStage::normalize($learner->current_stage) !== LearnerStage::EXTRA_PHONEME_DRILLS) {
            return redirect($flow->moduleResumeRoute($learner, $module))
                ->with('info', 'Continue from your current module step.');
        }

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
                'current_stage' => LearnerStage::MODULE_ASSIGNED,
            ]);

            return Module::find($placedAttempt->assigned_module_id);
        }

        return null;
    }

    private function guardModuleAccess(Learner $learner, Module $module, LearnerFlowService $flow): ?RedirectResponse
    {
        if (! $flow->moduleAccessible($learner, $module)) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'That module is locked right now. Continue from your dashboard.');
        }

        return null;
    }
}
