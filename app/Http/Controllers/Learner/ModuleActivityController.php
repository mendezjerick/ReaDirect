<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AudioStorageService;
use App\Services\Agents\AgentCommentaryService;
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
        ModuleFeedbackService $feedback,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AgentCommentaryService $commentary
    ): RedirectResponse {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);
        $attempt = $this->attempt($request, $learner, $module, $selection);
        $items = $selection->getLockedItemsForAttempt($attempt, $activityType)->where('is_mastery_item', false)->values();

        $validated = $request->validate($this->responseRules($items->count()), $this->friendlyValidationMessages());
        $this->validateSubmittedItemSet($items, $validated['responses']);

        $this->persistResponses($attempt, $items, $validated['responses'], $scoring, $feedback, $audioStorage, $analysis, $commentary, $module, $activityType, false);

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
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AgentCommentaryService $commentary,
        Module $module,
        string $activityType,
        bool $isMastery
    ): void {
        foreach ($items as $item) {
            $submittedIndex = collect($responses)->search(fn ($response) => (int) ($response['module_attempt_item_id'] ?? 0) === (int) $item->id);
            $submitted = $submittedIndex === false ? [] : $responses[$submittedIndex];
            $audioFile = null;

            if (isset($submitted['audio'])) {
                $audioFile = $audioStorage->store(
                    $submitted['audio'],
                    $attempt->learner,
                    'module_activity',
                    moduleAttempt: $attempt,
                    durationSeconds: isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
                    metadata: [
                        'module_attempt_item_id' => $item->id,
                        'activity_type' => $activityType,
                    ],
                );
            }

            $expectedAnswer = $this->expectedAnswer($item);
            $acceptedAnswers = $item->prompt_snapshot['accepted_answers'] ?? [];
            $resolved = $analysis->resolve(
                $submitted['answer'] ?? null,
                $audioFile,
                $this->analysisContext($item, $module, $activityType, $expectedAnswer, $acceptedAnswers)
            );
            $answer = $resolved['transcript'];
            $displayedAnswer = $resolved['displayed_transcript'] ?? $answer;

            if (trim($answer) === '') {
                throw ValidationException::withMessages([
                    'responses.'.($submittedIndex === false ? 0 : $submittedIndex).'.answer' => 'Let us answer this first.',
                ]);
            }

            $score = $scoring->scoreAnswer($item, $answer);
            $template = $score['is_correct']
                ? $feedback->feedbackForCorrect($module->key, $activityType)
                : $feedback->feedbackForIncorrect($module->key, $activityType, $score['error_type'] ?? 'incorrect_general');
            $templateFeedback = $score['is_correct'] ? $template['success_text'] : $template['feedback_text'];

            $response = ModuleActivityResponse::updateOrCreate(
                ['module_attempt_id' => $attempt->id, 'module_attempt_item_id' => $item->id],
                array_merge([
                    'module_activity_id' => $item->module_activity_id,
                    'audio_file_id' => $audioFile?->id,
                    'transcript_source' => $resolved['source'],
                    'stt_confidence' => $resolved['confidence'],
                    'response_text' => $displayedAnswer,
                    'learner_answer' => $displayedAnswer,
                    'learner_transcript' => $answer,
                    'expected_answer' => $score['expected_answer'],
                    'is_correct' => $score['is_correct'],
                    'score' => $score['score'],
                    'feedback_text' => $templateFeedback,
                    'retry_count' => (int) ($submitted['retry_count'] ?? 0),
                    'is_mastery_item' => $isMastery,
                    'error_type' => $score['error_type'],
                    'metadata' => ['source_csv_id' => $item->source_csv_id],
                    'metadata_json' => [
                        'prompt_snapshot' => $item->prompt_snapshot,
                        'asr_scoring_debug' => $this->asrScoringDebug($attempt, $item, $module, $activityType, $score['expected_answer'], $answer, $displayedAnswer, $score['score'], $audioFile, $resolved),
                    ],
                ], $analysis->responseFields($resolved['ai_response'] ?? null))
            );

            if ($audioFile) {
                $audioStorage->attachToModuleResponse($audioFile, $response->id);
            }

            $agentCommentary = $commentary->generateCommentary([
                'mode' => 'module_coaching',
                'agent_type' => 'coach_feedback',
                'learner_id' => $attempt->learner_id,
                'source_type' => 'module_activity_response',
                'source_id' => $response->id,
                'module_key' => $module->key,
                'activity_type' => $activityType,
                'expected_answer' => $score['expected_answer'],
                'learner_answer' => $displayedAnswer,
                'is_correct' => $score['is_correct'],
                'score' => $score['score'],
                'max_score' => $score['possible_score'],
                'error_type' => $score['error_type'] ?? null,
                'recommended_action' => $score['is_correct'] ? 'continue' : 'try_again',
                'template_feedback' => $templateFeedback,
                'retry_instruction' => $template['retry_instruction'] ?? '',
                'is_module' => true,
                'can_give_hint' => true,
            ]);

            $response->update([
                'feedback_text' => $agentCommentary['message'],
                'agent_commentary_text' => $agentCommentary['message'],
                'agent_commentary_source' => $agentCommentary['source'],
                'agent_type' => $agentCommentary['agent_type'],
            ]);

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

    private function expectedAnswer(ModuleAttemptItem $item): ?string
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];

        return $payload['expected_answer'] ?? $payload['target_word'] ?? $item->prompt_snapshot['prompt'] ?? null;
    }

    private function analysisContext(ModuleAttemptItem $item, Module $module, string $activityType, ?string $expectedAnswer, array $acceptedAnswers): array
    {
        return [
            'expected_text' => $expectedAnswer,
            'accepted_answers' => $acceptedAnswers,
            'prompt_id' => $item->source_csv_id,
            'module_key' => $module->key,
            'activity_type' => $activityType,
            'task_type' => 'module_activity',
            'content_metadata' => ['prompt_snapshot' => $item->prompt_snapshot],
            'debug' => (bool) config('readirect_ai.debug.show_admin_debug'),
        ];
    }

    private function asrScoringDebug(
        ModuleAttempt $attempt,
        ModuleAttemptItem $item,
        Module $module,
        string $activityType,
        ?string $expectedAnswer,
        string $scoringTranscript,
        string $displayedTranscript,
        mixed $scoreGiven,
        mixed $audioFile,
        array $resolved
    ): array {
        $ai = $resolved['ai_response'] ?? [];

        return [
            'learner_id' => $attempt->learner_id,
            'attempt_id' => $attempt->id,
            'assessment_type' => 'module_activity',
            'module_type' => $module->key,
            'activity_type' => $activityType,
            'item_id' => $item->id,
            'expected_text' => $expectedAnswer,
            'raw_transcript' => $ai['raw_transcript'] ?? $audioFile?->transcript ?? $scoringTranscript,
            'corrected_transcript' => $ai['corrected_transcript'] ?? $scoringTranscript,
            'displayed_transcript' => $ai['displayed_transcript'] ?? $displayedTranscript,
            'raw_wer' => $ai['raw_wer'] ?? null,
            'corrected_wer' => $ai['corrected_wer'] ?? null,
            'score_given' => is_numeric($scoreGiven) ? (float) $scoreGiven : $scoreGiven,
            'phonetic_similarity_score' => $ai['phonetic_similarity_score'] ?? null,
            'threshold_used' => $ai['threshold_used'] ?? null,
            'normalization_applied' => $ai['normalization_applied'] ?? false,
            'normalization_reason' => $ai['normalization_reason'] ?? null,
            'correction_strategy_used' => $ai['correction_strategy_used'] ?? null,
            'accepted_by_exact_match' => $ai['accepted_by_exact_match'] ?? false,
            'accepted_by_letter_normalization' => $ai['accepted_by_letter_normalization'] ?? false,
            'accepted_by_letter_lattice' => $ai['accepted_by_letter_lattice'] ?? false,
            'accepted_by_known_confusion' => $ai['accepted_by_known_confusion'] ?? false,
            'accepted_by_phonetic_threshold' => $ai['accepted_by_phonetic_threshold'] ?? false,
            'audio_file_path' => $audioFile?->file_path ?? $audioFile?->path,
            'asr_confidence' => $resolved['confidence'],
            'created_at' => now()->toDateTimeString(),
        ];
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
            'responses.*.answer' => ['nullable', 'string', 'max:255'],
            'responses.*.retry_count' => ['nullable', 'integer', 'min:0'],
            'responses.*.transcript_source' => ['nullable', 'string', 'in:manual,ai_asr,stt_auto,stt_placeholder,teacher_review,future_asr'],
            'responses.*.audio' => AudioStorageService::validationRules(),
            'responses.*.duration_seconds' => AudioStorageService::durationValidationRules(),
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
            'responses.*.duration_seconds.min' => 'Record at least 1 second so the transcript can be generated.',
        ];
    }
}
