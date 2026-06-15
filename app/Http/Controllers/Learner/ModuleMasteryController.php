<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\AssessmentModeService;
use App\Services\AudioStorageService;
use App\Services\LearnerFlowService;
use App\Services\ModuleActivitySelectionService;
use App\Services\ModuleExperienceService;
use App\Services\ModuleItemRetryService;
use App\Services\ModuleMasteryService;
use App\Services\ModuleScoringService;
use App\Support\CurrentLearner;
use App\Support\LearnerStage;
use App\Support\SubmittedItemSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModuleMasteryController extends Controller
{
    public function show(Request $request, Module $module, ModuleActivitySelectionService $selection, LearnerFlowService $flow, AssessmentModeService $mode, ModuleItemRetryService $retry): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            return $redirect;
        }

        $attempt = $flow->resolveModuleAttempt($request, $learner, $module) ?? $selection->startOrResumeModuleAttempt($learner, $module);
        $nextPracticeActivity = $flow->nextPracticeActivity($attempt, $module);

        if ($nextPracticeActivity !== null && $attempt->status !== 'mastery_started') {
            return redirect()->route('learner.modules.activity', [$module, $nextPracticeActivity])
                ->with('info', 'Finish your practice before the mastery check.');
        }

        $request->session()->put('module_attempt_id', $attempt->id);
        $items = $selection->selectMasteryItemsForAttempt($attempt, $selection->masteryCountFor($module));

        $attempt->update(['status' => 'mastery_started']);
        $learner->update(['current_stage' => LearnerStage::MODULE_MASTERY_IN_PROGRESS]);

        return Inertia::render('Learner/Modules/ModuleMasteryCheck', [
            'module' => $module->only('key', 'title', 'description'),
            'moduleAttemptId' => $attempt->id,
            'items' => $this->itemsForForm($items, $retry),
            'assessmentMode' => $mode->props($request, $attempt, $learner),
        ]);
    }

    public function check(
        Request $request,
        Module $module,
        ModuleActivitySelectionService $selection,
        AssessmentModeService $mode,
        LearnerFlowService $flow,
        ModuleItemRetryService $retry,
    ): JsonResponse {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            abort(403, 'That module is locked right now. Continue from your dashboard.');
        }

        $attempt = $this->attemptForSubmission($request, $learner, $module, $flow);
        if (! $attempt) {
            abort(409, 'Continue from your current module step.');
        }

        $nextPracticeActivity = $flow->nextPracticeActivity($attempt, $module);
        if ($nextPracticeActivity !== null && $attempt->status !== 'mastery_started') {
            abort(409, 'Finish your practice before the mastery check.');
        }

        $validated = $request->validate($this->singleResponseRules(), $this->friendlyValidationMessages());
        $item = $selection->getLockedItemsForAttempt($attempt)
            ->where('is_mastery_item', true)
            ->firstWhere('id', (int) $validated['module_attempt_item_id']);

        if (! $item) {
            abort(404, 'Module mastery item not found.');
        }

        $result = $retry->check(
            $attempt,
            $item,
            $module,
            'mastery_check',
            $validated,
            true,
            $mode->canShowManualFallback($request, $attempt, $learner),
        );

        return response()->json([
            'retry_state' => $result['retry_state'],
            'message' => $result['message'],
            'agent_cue' => $result['agent_cue'] ?? null,
            'ciel_agent' => $result['ciel_agent'] ?? null,
            'ciel_focus_event' => $result['ciel_focus_event'] ?? null,
        ]);
    }

    public function store(
        Request $request,
        Module $module,
        ModuleActivitySelectionService $selection,
        ModuleScoringService $scoring,
        ModuleMasteryService $mastery,
        ModuleItemRetryService $retry,
        AssessmentModeService $mode,
        LearnerFlowService $flow
    ): RedirectResponse {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            return $redirect;
        }

        $attempt = $this->attemptForSubmission($request, $learner, $module, $flow);
        if (! $attempt) {
            return redirect($flow->moduleResumeRoute($learner, $module))
                ->with('info', 'Continue from your current module step.');
        }
        $nextPracticeActivity = $flow->nextPracticeActivity($attempt, $module);

        if ($nextPracticeActivity !== null && $attempt->status !== 'mastery_started') {
            return redirect()->route('learner.modules.activity', [$module, $nextPracticeActivity])
                ->with('info', 'Finish your practice before the mastery check.');
        }

        $items = $selection->getLockedItemsForAttempt($attempt)->where('is_mastery_item', true)->values();

        if ($items->isEmpty()) {
            return redirect()->route('learner.modules.mastery-check', $module)
                ->with('info', 'Start the mastery check before moving on.');
        }

        if ($request->has('responses')) {
            $validated = $request->validate($this->responseRules($items->count()), $this->friendlyValidationMessages());
            $this->validateSubmittedItemSet($items, $validated['responses']);

            foreach ($validated['responses'] as $index => $submitted) {
                $item = $items->firstWhere('id', (int) $submitted['module_attempt_item_id']);
                if ($item && ! $retry->itemIsComplete($item)) {
                    if (trim((string) ($submitted['answer'] ?? '')) === '' && empty($submitted['audio_file_id']) && empty($submitted['audio'])) {
                        throw ValidationException::withMessages([
                            "responses.{$index}.answer" => 'Let us answer this first.',
                        ]);
                    }

                    $retry->check($attempt, $item, $module, 'mastery_check', $submitted, true, $mode->canShowManualFallback($request, $attempt, $learner));
                }
            }
        }

        if ($items->contains(fn (ModuleAttemptItem $item): bool => ! $retry->itemIsComplete($item->refresh()))) {
            return redirect()->route('learner.modules.mastery-check', $module)
                ->with('info', 'Check each mastery item until it is correct or all three tries are used.');
        }

        $masteryScore = $scoring->calculateMasteryScore($attempt->refresh());
        $decision = $mastery->decide($module->key, $masteryScore);

        $attempt->update([
            'status' => 'completed',
            'score' => $masteryScore,
            'mastery_decision' => $decision['decision_key'],
            'rule_applied' => $decision['rule_applied'],
            'decision_reason' => $decision['user_friendly_message'],
            'completed_at' => now(),
        ]);

        $this->applyLearnerDecision($learner, $decision);

        return redirect()->route('learner.modules.mastery-result', $module);
    }

    public function result(Request $request, Module $module, ModuleMasteryService $mastery, LearnerFlowService $flow, ModuleExperienceService $experience): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        if ((int) $learner->current_module_id === (int) $module->id && ! $flow->moduleAccessible($learner, $module)) {
            return redirect()->route('learner.dashboard');
        }

        $attempt = ModuleAttempt::where('learner_id', $learner->id)
            ->where('module_id', $module->id)
            ->latest()
            ->firstOrFail();
        $decision = $mastery->decide($module->key, (float) $attempt->score);
        $nextModule = $decision['next_module_key'] ? Module::where('key', $decision['next_module_key'])->first() : null;

        return Inertia::render('Learner/Modules/ModuleMasteryResult', [
            'module' => $module->only('key', 'title', 'description'),
            'score' => $attempt->score,
            'decision' => $decision,
            'resultMessage' => $experience->masteryMessage($decision['decision_key']),
            'nextModule' => $nextModule?->only('key', 'title', 'description'),
        ]);
    }

    private function applyLearnerDecision(Learner $learner, array $decision): void
    {
        $nextModule = $decision['next_module_key'] ? Module::where('key', $decision['next_module_key'])->first() : null;
        $stage = match ($decision['decision_key']) {
            'proceed_to_reassessment' => LearnerStage::FINAL_REASSESSMENT_PENDING,
            default => LearnerStage::MODULE_ASSIGNED,
        };

        $learner->update([
            'current_module_id' => $nextModule?->id,
            'current_stage' => $stage,
        ]);
    }

    private function learner(Request $request): Learner
    {
        return CurrentLearner::require($request);
    }

    private function attemptForSubmission(Request $request, Learner $learner, Module $module, LearnerFlowService $flow): ?ModuleAttempt
    {
        return $flow->resolveModuleAttempt($request, $learner, $module);
    }

    private function guardModuleAccess(Learner $learner, Module $module, LearnerFlowService $flow): ?RedirectResponse
    {
        if (
            in_array(LearnerStage::normalize($learner->current_stage), [LearnerStage::FINAL_REASSESSMENT_COMPLETED, LearnerStage::COMPLETED], true)
            || $flow->isFinalComplete($flow->latestFinalAttempt($learner))
        ) {
            return redirect()->route('learner.completion')
                ->with('info', 'You already completed your reading journey.');
        }

        if (! $flow->moduleAccessible($learner, $module)) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'That module is locked right now. Continue from your dashboard.');
        }

        return null;
    }

    private function itemsForForm(Collection $items, ModuleItemRetryService $retry): array
    {
        return $items->map(fn (ModuleAttemptItem $item) => [
            'id' => $item->id,
            'sequence' => $item->sequence,
            'source_csv_id' => $item->source_csv_id,
            'activity_type' => $item->activity_type,
            'prompt' => $item->prompt_snapshot['prompt'] ?? '',
            'accepted_answers' => $item->prompt_snapshot['accepted_answers'] ?? [],
            'payload' => $item->prompt_snapshot['payload'] ?? [],
            'is_mastery_item' => $item->is_mastery_item,
            'retry_state' => $retry->stateForItem($item),
        ])->values()->all();
    }

    private function responseRules(int $requiredCount): array
    {
        return [
            'responses' => ['required', 'array', 'size:'.$requiredCount],
            'responses.*.module_attempt_item_id' => ['required', 'integer', 'exists:module_attempt_items,id'],
            'responses.*.answer' => ['nullable', 'string', 'max:5000'],
            'responses.*.transcript_source' => ['nullable', 'string', 'in:manual,ai_asr,stt_auto,stt_placeholder,teacher_review,future_asr'],
            'responses.*.audio_file_id' => ['nullable', 'integer', 'exists:audio_files,id'],
            'responses.*.audio' => AudioStorageService::validationRules(),
            'responses.*.duration_seconds' => AudioStorageService::durationValidationRules(),
        ];
    }

    private function singleResponseRules(): array
    {
        return [
            'module_attempt_item_id' => ['required', 'integer', 'exists:module_attempt_items,id'],
            'answer' => ['nullable', 'string', 'max:5000'],
            'transcript_source' => ['nullable', 'string', 'in:manual,ai_asr,stt_auto,stt_placeholder,teacher_review,future_asr'],
            'audio_file_id' => ['nullable', 'integer', 'exists:audio_files,id'],
            'audio' => AudioStorageService::validationRules(),
            'duration_seconds' => AudioStorageService::durationValidationRules(),
        ];
    }

    private function validateSubmittedItemSet(Collection $items, array $responses): void
    {
        if (! SubmittedItemSet::idsMatch($items, $responses, 'module_attempt_item_id')) {
            throw ValidationException::withMessages([
                'responses' => 'Almost there! Finish this check before moving on.',
            ]);
        }
    }

    private function friendlyValidationMessages(): array
    {
        return [
            'responses.required' => 'Almost there! Finish all items to continue.',
            'responses.size' => 'Almost there! Finish all items to continue.',
            'responses.*.answer.required' => 'Let us answer this first.',
            'responses.*.answer.regex' => 'Try this item before moving on.',
            'responses.*.duration_seconds.min' => 'That recording was too short. Please try again and speak clearly.',
        ];
    }
}
