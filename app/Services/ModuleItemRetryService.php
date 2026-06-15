<?php

namespace App\Services;

use App\Agents\Ciel\CielCoachDecisionService;
use App\Models\AudioFile;
use App\Models\Module;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AI\LearnerHistoryPayloadBuilder;
use App\Services\Ciel\CielTutorAgentClient;
use Illuminate\Validation\ValidationException;

class ModuleItemRetryService
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly ModuleScoringService $scoring,
        private readonly ModuleFeedbackService $feedback,
        private readonly AudioStorageService $audioStorage,
        private readonly AIAnalysisResolver $analysis,
        private readonly LearnerHistoryPayloadBuilder $learnerHistory,
        private readonly CielCoachDecisionService $ciel,
        private readonly CielTutorAgentClient $cielTutorAgent,
        private readonly CielFocusModeService $focusMode,
    ) {}

    public function check(
        ModuleAttempt $attempt,
        ModuleAttemptItem $item,
        Module $module,
        string $activityType,
        array $submitted,
        bool $isMastery,
        bool $allowManualFallback,
    ): array {
        $existing = $this->responseFor($attempt, $item);
        $history = $this->attemptHistory($existing);
        $state = $this->stateFromHistory($history, $existing);

        if ($state['is_resolved']) {
            return [
                'retry_state' => $state,
                'response' => $existing,
                'message' => $existing?->feedback_text ?? 'This item is already checked.',
            ];
        }

        if (count($history) >= self::MAX_ATTEMPTS) {
            $item->update(['answered_at' => $item->answered_at ?? now()]);

            return [
                'retry_state' => $this->stateForItem($item->refresh()),
                'response' => $existing,
                'message' => 'You used all three tries. Go to the next item.',
            ];
        }

        $audioFile = $this->audioFile($attempt, $submitted);

        if (! $audioFile && isset($submitted['audio'])) {
            $audioFile = $this->audioStorage->store(
                $submitted['audio'],
                $attempt->learner,
                $isMastery ? 'module_mastery_check' : 'module_activity',
                moduleAttempt: $attempt,
                durationSeconds: isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
                metadata: [
                    'module_attempt_item_id' => $item->id,
                    'activity_type' => $activityType,
                    'attempt_number' => count($history) + 1,
                ],
            );
        }

        $expectedAnswer = $this->expectedAnswer($item);
        $acceptedAnswers = $item->prompt_snapshot['accepted_answers'] ?? [];
        $analysisContext = $this->analysisContext($attempt, $item, $module, $activityType, $isMastery, $expectedAnswer, $acceptedAnswers);
        $resolved = $this->analysis->resolve(
            $allowManualFallback ? ($submitted['answer'] ?? null) : null,
            $audioFile,
            $analysisContext,
        );
        $answer = $resolved['transcript'];
        $displayedAnswer = $resolved['displayed_transcript'] ?? $answer;

        if (! $this->analysis->canComplete($resolved, $analysisContext)) {
            throw ValidationException::withMessages([
                'answer' => $this->analysis->completionFailureMessage($resolved, $analysisContext),
            ]);
        }

        $score = $this->scoring->scoreAnswer($item, $answer);
        if ($this->analysis->acceptedForShortPrompt($resolved['ai_response'] ?? null)) {
            $score['is_correct'] = true;
            $score['score'] = $score['possible_score'];
            $score['error_type'] = null;
        }

        $attemptNumber = count($history) + 1;
        $isCorrect = (bool) $score['is_correct'];
        $correctStreak = $this->correctStreak($attempt, $isCorrect);
        $incorrectStreak = $this->incorrectStreak($attempt, $isCorrect);
        $focusCorrectStreak = $this->moduleAttemptCorrectStreak($attempt, $isCorrect);
        $history[] = [
            'attempt' => $attemptNumber,
            'is_correct' => $isCorrect,
            'score' => (float) $score['score'],
            'possible_score' => (float) $score['possible_score'],
            'answer' => $displayedAnswer,
            'scoring_transcript' => $answer,
            'transcript_source' => $resolved['source'],
            'audio_file_id' => $audioFile?->id,
            'error_type' => $score['error_type'],
            'checked_at' => now()->toDateTimeString(),
        ];

        $isResolved = $isCorrect || $attemptNumber >= self::MAX_ATTEMPTS;
        $template = $score['is_correct']
            ? $this->feedback->feedbackForCorrect($module->key, $activityType)
            : $this->feedback->feedbackForIncorrect($module->key, $activityType, $score['error_type'] ?? 'incorrect_general');
        $templateFeedback = $score['is_correct'] ? $template['success_text'] : $template['feedback_text'];
        $aiResponse = is_array($resolved['ai_response'] ?? null) ? $resolved['ai_response'] : [];
        $cielEvent = [
            'source_type' => 'module',
            'context' => $isMastery ? 'mastery_practice' : 'module_practice',
            'learner_id' => $attempt->learner_id,
            'session_id' => 'module-attempt-'.($attempt->public_id ?: $attempt->id),
            'module_type' => $module->key,
            'activity_type' => $activityType,
            'target_text' => $score['expected_answer'],
            'expected' => $score['expected_answer'],
            'attempt_number' => $attemptNumber,
            'attempt' => $attemptNumber,
            'remaining_attempts' => max(0, self::MAX_ATTEMPTS - $attemptNumber),
            'is_correct' => $isCorrect,
            'transcript' => $displayedAnswer,
            'error_type' => $score['error_type'] ?? null,
            'similarity_label' => $aiResponse['similarity_label'] ?? null,
            'asr_confidence' => $resolved['confidence'] ?? null,
            'gop_score' => $aiResponse['gop_score'] ?? $aiResponse['overall_gop_score'] ?? null,
            'phoneme_errors' => $aiResponse['phoneme_errors'] ?? [],
            'uncertain' => $aiResponse['uncertain'] ?? null,
            'retry_required' => $aiResponse['retry_required'] ?? null,
            'audio_duration_seconds' => isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
            'correct_streak' => $correctStreak,
            'incorrect_streak' => $incorrectStreak,
            'weak_skill' => $aiResponse['recommended_practice_focus'] ?? null,
            'skill_signal' => $aiResponse['skill_signal'] ?? null,
            'activity_id' => $item->id,
            'section_completed' => $isCorrect && $this->isLastSectionItem($attempt, $item, $activityType, $isMastery),
            'final_completion' => false,
            'congrats_allowed' => false,
            'is_final_assessment_completion' => false,
            'ai_response' => $aiResponse,
        ];
        $agentCue = $this->ciel->decide($cielEvent);
        $cielAgent = $this->cielTutorAgent->decide($cielEvent);
        $feedbackMessage = $cielAgent['message'] ?? $agentCue['message'] ?? $templateFeedback;

        $metadata = array_merge($existing?->metadata_json ?? [], [
            'prompt_snapshot' => $item->prompt_snapshot,
            'attempt_history' => $history,
            'max_attempts' => self::MAX_ATTEMPTS,
            'ciel_agent' => $cielAgent,
            'asr_scoring_debug' => $this->asrScoringDebug($attempt, $item, $module, $activityType, $isMastery, $score['expected_answer'], $answer, $displayedAnswer, $score['score'], $audioFile, $resolved),
        ]);

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
                'is_correct' => $isCorrect,
                'score' => (float) $score['score'],
                'feedback_text' => $feedbackMessage,
                'agent_commentary_text' => $cielAgent['message'] ?? $agentCue['message'] ?? null,
                'agent_commentary_source' => $cielAgent['decision_source'] ?? ($agentCue ? 'ciel_deterministic_policy' : null),
                'agent_type' => ($cielAgent || $agentCue) ? 'coach_feedback' : null,
                'retry_count' => $attemptNumber,
                'is_mastery_item' => $isMastery,
                'error_type' => $score['error_type'],
                'metadata' => ['source_csv_id' => $item->source_csv_id],
                'metadata_json' => $metadata,
            ], $this->analysis->responseFields($resolved['ai_response'] ?? null)),
        );

        if ($audioFile) {
            $this->audioStorage->attachToModuleResponse($audioFile, $response->id);
        }

        if ($isResolved) {
            $item->update(['answered_at' => $item->answered_at ?? now()]);
        }

        $focusEvent = $this->focusMode->eventForModuleCheck(
            $attempt,
            $item,
            $module,
            $activityType,
            $score['expected_answer'] ?? $expectedAnswer,
            $isCorrect,
            $attemptNumber,
            $focusCorrectStreak,
        );

        return [
            'retry_state' => $this->stateFromHistory($history, $response),
            'response' => $response,
            'message' => $response->feedback_text,
            'agent_cue' => $agentCue,
            'ciel_agent' => $cielAgent,
            'ciel_focus_event' => $focusEvent,
        ];
    }

    public function stateForItem(ModuleAttemptItem $item): array
    {
        $response = $this->responseFor($item->moduleAttempt, $item);

        return $this->stateFromHistory($this->attemptHistory($response), $response, $item);
    }

    public function itemIsComplete(ModuleAttemptItem $item): bool
    {
        return (bool) $item->answered_at || $this->stateForItem($item)['is_resolved'];
    }

    private function stateFromHistory(array $history, ?ModuleActivityResponse $response = null, ?ModuleAttemptItem $item = null): array
    {
        $attempts = collect($history)
            ->take(self::MAX_ATTEMPTS)
            ->map(fn (array $attempt): array => [
                'attempt' => (int) ($attempt['attempt'] ?? 0),
                'is_correct' => (bool) ($attempt['is_correct'] ?? false),
                'status' => ($attempt['is_correct'] ?? false) ? 'correct' : 'incorrect',
                'answer' => $attempt['answer'] ?? null,
                'checked_at' => $attempt['checked_at'] ?? null,
            ])
            ->values()
            ->all();

        $used = count($attempts);
        $isCorrect = collect($attempts)->contains(fn (array $attempt) => $attempt['is_correct']);
        $isResolved = $isCorrect || $used >= self::MAX_ATTEMPTS || (bool) $item?->answered_at;

        return [
            'max_attempts' => self::MAX_ATTEMPTS,
            'attempt_count' => $used,
            'remaining_attempts' => max(0, self::MAX_ATTEMPTS - $used),
            'attempts' => $attempts,
            'is_correct' => $isCorrect,
            'is_resolved' => $isResolved,
            'can_retry' => ! $isResolved && $used < self::MAX_ATTEMPTS,
            'next_attempt' => min(self::MAX_ATTEMPTS, $used + 1),
            'feedback' => $response?->feedback_text,
        ];
    }

    private function responseFor(?ModuleAttempt $attempt, ModuleAttemptItem $item): ?ModuleActivityResponse
    {
        if (! $attempt) {
            return null;
        }

        return ModuleActivityResponse::query()
            ->where('module_attempt_id', $attempt->id)
            ->where('module_attempt_item_id', $item->id)
            ->first();
    }

    private function attemptHistory(?ModuleActivityResponse $response): array
    {
        $history = $response?->metadata_json['attempt_history'] ?? [];

        if (is_array($history) && $history !== []) {
            return array_values($history);
        }

        if (! $response || $response->is_correct === null) {
            return [];
        }

        return [[
            'attempt' => max(1, (int) ($response->retry_count ?: 1)),
            'is_correct' => (bool) $response->is_correct,
            'score' => (float) $response->score,
            'answer' => $response->learner_answer ?? $response->response_text,
            'checked_at' => $response->updated_at?->toDateTimeString(),
        ]];
    }

    private function audioFile(ModuleAttempt $attempt, array $submitted): ?AudioFile
    {
        return isset($submitted['audio_file_id']) && $submitted['audio_file_id']
            ? AudioFile::where('learner_id', $attempt->learner_id)
                ->where('module_attempt_id', $attempt->id)
                ->find($submitted['audio_file_id'])
            : null;
    }

    private function expectedAnswer(ModuleAttemptItem $item): ?string
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];

        return $payload['expected_answer'] ?? $payload['target_word'] ?? $item->prompt_snapshot['prompt'] ?? null;
    }

    private function correctStreak(ModuleAttempt $attempt, bool $currentCorrect): int
    {
        if (! $currentCorrect) {
            return 0;
        }

        $streak = 1;
        foreach ($this->learnerHistory->recentForLearner($attempt->learner, 10) as $entry) {
            if (($entry['is_correct'] ?? null) !== true) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function incorrectStreak(ModuleAttempt $attempt, bool $currentCorrect): int
    {
        if ($currentCorrect) {
            return 0;
        }

        $streak = 1;
        foreach ($this->learnerHistory->recentForLearner($attempt->learner, 10) as $entry) {
            if (($entry['is_correct'] ?? null) !== false) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function moduleAttemptCorrectStreak(ModuleAttempt $attempt, bool $currentCorrect): int
    {
        if (! $currentCorrect) {
            return 0;
        }

        $streak = 1;
        $responses = $attempt->responses()
            ->latest('id')
            ->get(['is_correct']);

        foreach ($responses as $response) {
            if ($response->is_correct !== true) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    private function isLastSectionItem(ModuleAttempt $attempt, ModuleAttemptItem $item, string $activityType, bool $isMastery): bool
    {
        $lastSequence = $attempt->items()
            ->where('is_mastery_item', $isMastery)
            ->when(! $isMastery, fn ($query) => $query->where('activity_type', $activityType))
            ->max('sequence');

        return $lastSequence !== null && (int) $item->sequence >= (int) $lastSequence;
    }

    private function analysisContext(ModuleAttempt $attempt, ModuleAttemptItem $item, Module $module, string $activityType, bool $isMastery, ?string $expectedAnswer, array $acceptedAnswers): array
    {
        return [
            'expected_text' => $expectedAnswer,
            'accepted_answers' => $acceptedAnswers,
            'prompt_id' => $item->source_csv_id,
            'module_key' => $module->key,
            'module_type' => $module->key,
            'activity_type' => $activityType,
            'assessment_type' => $isMastery ? 'module_mastery' : 'module_activity',
            'item_id' => $item->id,
            'learner_id' => $attempt->learner_id,
            'attempt_id' => $attempt->id,
            'task_type' => $isMastery ? 'module_mastery' : 'module_activity',
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
        bool $isMastery,
        ?string $expectedAnswer,
        string $scoringTranscript,
        string $displayedTranscript,
        mixed $scoreGiven,
        mixed $audioFile,
        array $resolved,
    ): array {
        $ai = $resolved['ai_response'] ?? [];

        return [
            'learner_id' => $attempt->learner_id,
            'attempt_id' => $attempt->id,
            'assessment_type' => $isMastery ? 'module_mastery' : 'module_activity',
            'module_type' => $module->key,
            'activity_type' => $activityType,
            'item_id' => $item->id,
            'expected_text' => $expectedAnswer,
            'prompt_type' => $ai['prompt_type'] ?? null,
            'raw_transcript' => $ai['raw_transcript'] ?? $audioFile?->transcript ?? $scoringTranscript,
            'corrected_transcript' => $ai['corrected_transcript'] ?? $scoringTranscript,
            'displayed_transcript' => $ai['displayed_transcript'] ?? $displayedTranscript,
            'score_given' => is_numeric($scoreGiven) ? (float) $scoreGiven : $scoreGiven,
            'accepted' => $ai['accepted'] ?? null,
            'error_type' => $ai['error_type'] ?? null,
            'audio_file_path' => $audioFile?->file_path ?? $audioFile?->path,
            'asr_confidence' => $resolved['confidence'],
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
