<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\ModuleActivitySelectionService;
use App\Services\ModuleFeedbackService;
use App\Services\ModuleScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModuleActivityController extends Controller
{
    public function show(Request $request, Module $module, string $activityType, ModuleActivitySelectionService $selection): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $request->session()->put('module_attempt_id', $attempt->id);

        $count = $selection->practiceCountFor($module, $activityType);
        $items = $selection->selectPracticeItemsForAttempt($attempt, $activityType, $count);

        if ($items->isEmpty()) {
            return redirect()->route('learner.modules.mastery-check', $module);
        }

        $activityTypes = $selection->practiceActivityTypes($module);
        $nextActivityType = $this->nextActivityType($activityTypes, $activityType);

        $attempt->update(['status' => 'practice_started']);

        return Inertia::render('Learner/Modules/ModuleActivity', [
            'module' => $module->only('key', 'title', 'description'),
            'activityType' => $activityType,
            'activityLabel' => $this->activityLabel($activityType),
            'items' => $this->itemsForForm($items),
            'nextActivityType' => $nextActivityType,
        ]);
    }

    public function store(
        Request $request,
        Module $module,
        string $activityType,
        ModuleActivitySelectionService $selection,
        ModuleScoringService $scoring,
        ModuleFeedbackService $feedback
    ): RedirectResponse {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);
        $attempt = $this->attempt($request, $learner, $module, $selection);
        $items = $selection->getLockedItemsForAttempt($attempt, $activityType)->where('is_mastery_item', false)->values();

        $validated = $request->validate($this->responseRules($items->count()), $this->friendlyValidationMessages());
        $this->validateSubmittedItemSet($items, $validated['responses']);

        $this->persistResponses($attempt, $items, $validated['responses'], $scoring, $feedback, $module, $activityType, false);

        $activityTypes = $selection->practiceActivityTypes($module);
        $nextActivityType = $this->nextActivityType($activityTypes, $activityType);

        if ($nextActivityType) {
            return redirect()->route('learner.modules.activity', [$module, $nextActivityType]);
        }

        return redirect()->route('learner.modules.mastery-check', $module);
    }

    private function persistResponses(
        ModuleAttempt $attempt,
        Collection $items,
        array $responses,
        ModuleScoringService $scoring,
        ModuleFeedbackService $feedback,
        Module $module,
        string $activityType,
        bool $isMastery
    ): void {
        foreach ($items as $item) {
            $submitted = collect($responses)->firstWhere('module_attempt_item_id', $item->id);
            $answer = $submitted['answer'] ?? '';
            $score = $scoring->scoreAnswer($item, $answer);
            $template = $score['is_correct']
                ? $feedback->feedbackForCorrect($module->key, $activityType)
                : $feedback->feedbackForIncorrect($module->key, $activityType, $score['error_type'] ?? 'incorrect_general');

            ModuleActivityResponse::updateOrCreate(
                ['module_attempt_id' => $attempt->id, 'module_attempt_item_id' => $item->id],
                [
                    'module_activity_id' => $item->module_activity_id,
                    'response_text' => $answer,
                    'learner_answer' => $answer,
                    'expected_answer' => $score['expected_answer'],
                    'is_correct' => $score['is_correct'],
                    'score' => $score['score'],
                    'feedback_text' => $score['is_correct'] ? $template['success_text'] : $template['feedback_text'],
                    'retry_count' => (int) ($submitted['retry_count'] ?? 0),
                    'is_mastery_item' => $isMastery,
                    'error_type' => $score['error_type'],
                    'metadata' => ['source_csv_id' => $item->source_csv_id],
                    'metadata_json' => ['prompt_snapshot' => $item->prompt_snapshot],
                ]
            );

            $item->update(['answered_at' => now()]);
        }
    }

    private function learner(Request $request): Learner
    {
        return Learner::find($request->session()->get('learner_id')) ?? Learner::firstOrFail();
    }

    private function attempt(Request $request, Learner $learner, Module $module, ModuleActivitySelectionService $selection): ModuleAttempt
    {
        $attempt = ModuleAttempt::where('id', $request->session()->get('module_attempt_id'))
            ->where('learner_id', $learner->id)
            ->where('module_id', $module->id)
            ->first();

        return $attempt ?? $selection->startOrResumeModuleAttempt($learner, $module);
    }

    private function authorizeModule(Learner $learner, Module $module): void
    {
        if ($learner->current_module_id && (int) $learner->current_module_id !== (int) $module->id) {
            abort(403);
        }
    }

    private function itemsForForm(Collection $items): array
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
            'responses.*.answer' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'responses.*.retry_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function validateSubmittedItemSet(Collection $items, array $responses): void
    {
        $expected = $items->pluck('id')->sort()->values()->all();
        $submitted = collect($responses)->pluck('module_attempt_item_id')->sort()->values()->all();

        if ($expected !== $submitted) {
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
        ];
    }
}
