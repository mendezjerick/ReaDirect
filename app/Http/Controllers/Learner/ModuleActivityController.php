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
use App\Services\ModuleItemRetryService;
use App\Support\CurrentLearner;
use App\Support\LearnerStage;
use App\Support\SubmittedItemSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModuleActivityController extends Controller
{
    public function show(Request $request, Module $module, string $activityType, ModuleActivitySelectionService $selection, LearnerFlowService $flow, AssessmentModeService $mode, ModuleItemRetryService $retry): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            return $redirect;
        }

        $attempt = $flow->resolveModuleAttempt($request, $learner, $module) ?? $selection->startOrResumeModuleAttempt($learner, $module);
        $request->session()->put('module_attempt_id', $attempt->id);
        $configuredActivityTypes = $selection->practiceActivityTypes($module);
        $nextAllowedActivity = $flow->nextPracticeActivity($attempt, $module);

        if ($configuredActivityTypes !== [] && ($attempt->status === 'mastery_started' || $nextAllowedActivity === null)) {
            return redirect()->route('learner.modules.mastery-check', $module);
        }

        if ($configuredActivityTypes !== [] && $activityType !== $nextAllowedActivity) {
            return redirect()->route('learner.modules.activity', [$module, $nextAllowedActivity])
                ->with('info', 'We brought you back to the next module activity.');
        }

        $count = $selection->practiceCountFor($module, $activityType);
        $items = $selection->selectPracticeItemsForAttempt($attempt, $activityType, $count);

        if ($items->isEmpty()) {
            return redirect()->route('learner.modules.mastery-check', $module);
        }

        $activityTypes = $selection->practiceActivityTypes($module);
        $nextActivityType = $this->nextActivityType($activityTypes, $activityType);

        $attempt->update(['status' => 'practice_started']);
        $learner->update(['current_stage' => LearnerStage::MODULE_PRACTICE_IN_PROGRESS]);

        return Inertia::render('Learner/Modules/ModuleActivity', [
            'module' => $module->only('key', 'title', 'description'),
            'moduleAttemptId' => $attempt->id,
            'activityType' => $activityType,
            'activityLabel' => $this->activityLabel($activityType),
            'items' => $this->itemsForForm($items, $retry),
            'nextActivityType' => $nextActivityType,
            'assessmentMode' => $mode->props($request, $attempt, $learner),
        ]);
    }

    public function check(
        Request $request,
        Module $module,
        string $activityType,
        ModuleActivitySelectionService $selection,
        AssessmentModeService $mode,
        LearnerFlowService $flow,
        ModuleItemRetryService $retry,
    ): \Illuminate\Http\JsonResponse {
        $learner = $this->learner($request);
        if ($redirect = $this->guardModuleAccess($learner, $module, $flow)) {
            abort(403, 'That module is locked right now. Continue from your dashboard.');
        }

        $attempt = $this->attemptForSubmission($request, $learner, $module, $flow);
        if (! $attempt || ($selection->practiceActivityTypes($module) !== [] && $activityType !== $flow->nextPracticeActivity($attempt, $module))) {
            abort(409, 'Continue from your current module activity.');
        }

        $validated = $request->validate($this->singleResponseRules(), $this->friendlyValidationMessages());
        $item = $selection->getLockedItemsForAttempt($attempt, $activityType)
            ->where('is_mastery_item', false)
            ->firstWhere('id', (int) $validated['module_attempt_item_id']);

        if (! $item) {
            abort(404, 'Module item not found.');
        }

        $result = $retry->check(
            $attempt,
            $item,
            $module,
            $activityType,
            $validated,
            false,
            $mode->canShowManualFallback($request, $attempt, $learner),
        );

        return response()->json([
            'retry_state' => $result['retry_state'],
            'message' => $result['message'],
        ]);
    }

    public function store(
        Request $request,
        Module $module,
        string $activityType,
        ModuleActivitySelectionService $selection,
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
                ->with('info', 'Continue from your current module activity.');
        }

        if ($selection->practiceActivityTypes($module) !== [] && $activityType !== $flow->nextPracticeActivity($attempt, $module)) {
            return redirect($flow->moduleResumeRoute($learner, $module))
                ->with('info', 'Continue from your current module activity.');
        }

        $items = $selection->getLockedItemsForAttempt($attempt, $activityType)->where('is_mastery_item', false)->values();

        if ($items->isEmpty()) {
            return redirect()->route('learner.modules.activity', [$module, $activityType])
                ->with('info', 'Start this activity before moving on.');
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

                    $retry->check($attempt, $item, $module, $activityType, $submitted, false, $mode->canShowManualFallback($request, $attempt, $learner));
                }
            }
        }

        if ($items->contains(fn (ModuleAttemptItem $item): bool => ! $retry->itemIsComplete($item->refresh()))) {
            return redirect()->route('learner.modules.activity', [$module, $activityType])
                ->with('info', 'Check each item until it is correct or all three tries are used.');
        }

        $activityTypes = $selection->practiceActivityTypes($module);
        $nextActivityType = $this->nextActivityType($activityTypes, $activityType);

        if ($nextActivityType) {
            return redirect()->route('learner.modules.activity', [$module, $nextActivityType]);
        }

        return redirect()->route('learner.modules.mastery-check', $module);
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

    private function nextActivityType(array $activityTypes, string $activityType): ?string
    {
        $index = array_search($activityType, $activityTypes, true);

        if ($index === false) {
            return null;
        }

        return $activityTypes[$index + 1] ?? null;
    }

    private function activityLabel(string $activityType): string
    {
        return str($activityType)->replace('_', ' ')->title()->toString();
    }

    private function responseRules(int $requiredCount): array
    {
        return [
            'responses' => ['required', 'array', 'size:'.$requiredCount],
            'responses.*.module_attempt_item_id' => ['required', 'integer', 'exists:module_attempt_items,id'],
            'responses.*.answer' => ['nullable', 'string', 'max:5000'],
            'responses.*.retry_count' => ['nullable', 'integer', 'min:0'],
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
                'responses' => 'Almost there! Finish this activity before moving on.',
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
