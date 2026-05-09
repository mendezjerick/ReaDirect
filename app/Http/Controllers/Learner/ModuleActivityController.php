<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\Agents\AgentCommentaryService;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AssessmentModeService;
use App\Services\AudioStorageService;
use App\Services\LearnerFlowService;
use App\Services\ModuleActivitySelectionService;
use App\Services\ModuleFeedbackService;
use App\Services\ModuleScoringService;
use App\Support\LearnerStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModuleActivityController extends Controller
{
    public function show(Request $request, Module $module, string $activityType, ModuleActivitySelectionService $selection, LearnerFlowService $flow, AssessmentModeService $mode): Response|RedirectResponse
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
            'items' => $this->itemsForForm($items),
            'nextActivityType' => $nextActivityType,
            'assessmentMode' => $mode->props($request, $attempt, $learner),
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
        AgentCommentaryService $commentary,
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

        $validated = $request->validate($this->responseRules($items->count()), $this->friendlyValidationMessages());
        $this->validateSubmittedItemSet($items, $validated['responses']);

        $this->persistResponses($attempt, $items, $validated['responses'], $scoring, $feedback, $audioStorage, $analysis, $commentary, $module, $activityType, false, $mode->canShowManualFallback($request, $attempt, $learner));

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
        bool $isMastery,
        bool $allowManualFallback
    ): void {
        foreach ($items as $item) {
            $submittedIndex = collect($responses)->search(fn ($response) => (int) ($response['module_attempt_item_id'] ?? 0) === (int) $item->id);
            $submitted = $submittedIndex === false ? [] : $responses[$submittedIndex];
            $audioFile = isset($submitted['audio_file_id']) && $submitted['audio_file_id']
                ? AudioFile::where('learner_id', $attempt->learner_id)
                    ->where('module_attempt_id', $attempt->id)
                    ->find($submitted['audio_file_id'])
                : null;

            if (! $audioFile && isset($submitted['audio'])) {
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
                $allowManualFallback ? ($submitted['answer'] ?? null) : null,
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
            if ($analysis->acceptedForShortPrompt($resolved['ai_response'] ?? null)) {
                $score['is_correct'] = true;
                $score['score'] = $score['possible_score'];
                $score['error_type'] = null;
            }
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

    private function attemptForSubmission(Request $request, Learner $learner, Module $module, LearnerFlowService $flow): ?ModuleAttempt
    {
        return $flow->resolveModuleAttempt($request, $learner, $module);
    }

    private function guardModuleAccess(Learner $learner, Module $module, LearnerFlowService $flow): ?RedirectResponse
    {
        if (! $flow->moduleAccessible($learner, $module)) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'That module is locked right now. Continue from your dashboard.');
        }

        return null;
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
            'module_type' => $module->key,
            'activity_type' => $activityType,
            'assessment_type' => 'module_activity',
            'item_id' => $item->id,
            'learner_id' => $item->moduleAttempt?->learner_id,
            'attempt_id' => $item->module_attempt_id,
            'task_type' => 'module_activity',
            'current_scoring_context' => [
                'accepted_answers' => $acceptedAnswers,
                'source_csv_id' => $item->source_csv_id,
                'prompt_snapshot' => $item->prompt_snapshot,
            ],
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
            'prompt_type' => $ai['prompt_type'] ?? null,
            'asr_route' => $ai['asr_route'] ?? null,
            'model_family' => $ai['model_family'] ?? null,
            'model_used' => $ai['model_used'] ?? null,
            'raw_transcript' => $ai['raw_transcript'] ?? $audioFile?->transcript ?? $scoringTranscript,
            'wav2vec2_transcript' => $ai['wav2vec2_transcript'] ?? null,
            'corrected_transcript' => $ai['corrected_transcript'] ?? $scoringTranscript,
            'displayed_transcript' => $ai['displayed_transcript'] ?? $displayedTranscript,
            'raw_cer' => $ai['raw_cer'] ?? null,
            'corrected_cer' => $ai['corrected_cer'] ?? null,
            'raw_wer' => $ai['raw_wer'] ?? null,
            'corrected_wer' => $ai['corrected_wer'] ?? null,
            'pause_metrics' => $ai['pause_metrics'] ?? null,
            'retry_required' => $ai['retry_required'] ?? false,
            'uncertain' => $ai['uncertain'] ?? false,
            'uncertainty_reasons' => $ai['uncertainty_reasons'] ?? [],
            'audio_quality' => $ai['audio_quality'] ?? null,
            'learner_retry_message' => $ai['learner_retry_message'] ?? null,
            'score_given' => is_numeric($scoreGiven) ? (float) $scoreGiven : $scoreGiven,
            'accepted' => $ai['accepted'] ?? null,
            'expected_phonemes' => $ai['expected_phonemes'] ?? null,
            'observed_phonemes' => $ai['observed_phonemes'] ?? null,
            'phonetic_similarity_score' => $ai['phonetic_similarity_score'] ?? null,
            'composite_score' => $ai['composite_score'] ?? null,
            'threshold_used' => $ai['threshold_used'] ?? null,
            'normalization_applied' => $ai['normalization_applied'] ?? false,
            'normalization_reason' => $ai['normalization_reason'] ?? null,
            'correction_strategy_used' => $ai['correction_strategy_used'] ?? null,
            'accepted_by_exact_match' => $ai['accepted_by_exact_match'] ?? false,
            'accepted_by_letter_alias' => $ai['accepted_by_letter_alias'] ?? $ai['accepted_by_letter_normalization'] ?? false,
            'accepted_by_letter_lattice' => $ai['accepted_by_letter_lattice'] ?? false,
            'accepted_by_vowel_tail' => $ai['accepted_by_vowel_tail'] ?? false,
            'accepted_by_known_confusion' => $ai['accepted_by_known_confusion'] ?? false,
            'accepted_by_phonetic_threshold' => $ai['accepted_by_phonetic_threshold'] ?? false,
            'accepted_by_phoneme_evidence' => $ai['accepted_by_phoneme_evidence'] ?? false,
            'critical_phoneme' => $ai['critical_phoneme'] ?? null,
            'critical_phoneme_detected' => $ai['critical_phoneme_detected'] ?? null,
            'debug_metadata' => $ai['debug_metadata'] ?? null,
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
            'responses.*.audio_file_id' => ['nullable', 'integer', 'exists:audio_files,id'],
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
            'responses.*.duration_seconds.min' => 'That recording was too short. Please try again and speak clearly.',
        ];
    }
}
