<?php

namespace App\Http\Controllers;

use App\Models\AgentProfile;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\AssessmentTaskResponse;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AnswerMatchingService;
use App\Services\Assessment\FinalAssessmentComparisonService;
use App\Services\AssessmentItemSelectionService;
use App\Services\AssessmentModeService;
use App\Services\AudioStorageService;
use App\Services\CrlaScoringService;
use App\Services\LearnerFlowService;
use App\Services\ReadingComprehensionScoringService;
use App\Services\SentenceReadingScoringService;
use App\Support\CurrentLearner;
use App\Support\LearnerStage;
use App\Support\SubmittedItemSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinalAssessmentController extends Controller
{
    public function start(Request $request, LearnerFlowService $flow): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        $stage = LearnerStage::normalize($learner->current_stage);
        $activeAttempt = $flow->resolveFinalAttempt($request);

        if (in_array($stage, [LearnerStage::FINAL_REASSESSMENT_COMPLETED, LearnerStage::COMPLETED], true)) {
            return redirect()->route('learner.completion');
        }

        if ($this->completedFinalAttempt($learner)) {
            return redirect()->route('learner.completion');
        }

        if ($activeAttempt) {
            $learner->update(['current_stage' => LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS]);

            return redirect($flow->finalResumeRoute($activeAttempt));
        }

        if ($stage !== LearnerStage::FINAL_REASSESSMENT_PENDING) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'The final reassessment is not available yet.');
        }

        return Inertia::render('Learner/FinalAssessment/Start');
    }

    public function storeStart(Request $request, LearnerFlowService $flow): RedirectResponse
    {
        $learner = $this->learner($request);
        $stage = LearnerStage::normalize($learner->current_stage);
        $activeAttempt = $flow->resolveFinalAttempt($request);

        if (in_array($stage, [LearnerStage::FINAL_REASSESSMENT_COMPLETED, LearnerStage::COMPLETED], true)) {
            return redirect()->route('learner.completion');
        }

        if ($this->completedFinalAttempt($learner)) {
            return redirect()->route('learner.completion');
        }

        if ($activeAttempt) {
            $learner->update(['current_stage' => LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS]);

            return redirect($flow->finalResumeRoute($activeAttempt));
        }

        if ($stage !== LearnerStage::FINAL_REASSESSMENT_PENDING) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'The final reassessment is not available yet.');
        }

        $baseline = $this->baselineDiagnostic($learner);
        if (! $baseline) {
            return redirect()->route('learner.dashboard')
                ->with('error', 'A completed diagnostic is needed before the final reassessment.');
        }

        $agent = AgentProfile::where('key', AgentProfile::ASSESSMENT)->first();

        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'agent_profile_id' => $agent?->id,
            'baseline_assessment_attempt_id' => $baseline?->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'task_1',
            'started_at' => now(),
        ]);

        if ($baseline) {
            $this->cloneBaselineItems($baseline, $attempt, AssessmentItemSelectionService::TASK_1_LETTER);
        }

        $request->session()->put('final_assessment_attempt_id', $attempt->id);
        $learner->update(['current_stage' => LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS]);

        return redirect()->route('final-assessment.task', 'task-1');
    }

    public function showTask(Request $request, string $taskKey, AssessmentItemSelectionService $itemSelection, CrlaScoringService $crla, LearnerFlowService $flow, AssessmentModeService $mode): Response|RedirectResponse
    {
        $attempt = $this->attemptForFinalStep($request, $flow, $taskKey);
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        return match ($taskKey) {
            'task-1' => Inertia::render('Learner/FinalAssessment/Task1LetterPronunciation', [
                'items' => $this->itemsForForm($taskOneItems = $this->taskItems($attempt, $itemSelection, AssessmentItemSelectionService::TASK_1_LETTER)),
                'initialIndex' => $this->initialIndexForItems($taskOneItems),
                'assessmentAttemptId' => $attempt->id,
                'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
            ]),
            'task-2a' => $this->showTaskTwoA($request, $attempt, $itemSelection, $crla, $mode),
            'task-2b' => Inertia::render('Learner/FinalAssessment/Task2BWordInSentence', [
                'items' => $this->itemsForForm($taskTwoBItems = $this->taskItems($attempt, $itemSelection, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE)),
                'initialIndex' => $this->initialIndexForItems($taskTwoBItems),
                'assessmentAttemptId' => $attempt->id,
                'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
            ]),
            'passage' => Inertia::render('Learner/FinalAssessment/PassageReading', [
                'passage' => $this->itemForForm($this->readingPassage($attempt, $itemSelection)),
                'assessmentAttemptId' => $attempt->id,
                'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
            ]),
            'comprehension' => Inertia::render('Learner/FinalAssessment/ComprehensionQuestions', [
                'questions' => $this->questionsForPassage($this->readingPassage($attempt, $itemSelection)),
                'assessmentAttemptId' => $attempt->id,
                'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
            ]),
            default => abort(404),
        };
    }

    public function submitTask(
        Request $request,
        string $taskKey,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        CrlaScoringService $crla,
        SentenceReadingScoringService $sentenceScoring,
        ReadingComprehensionScoringService $reading,
        FinalAssessmentComparisonService $comparison,
        AssessmentModeService $mode
    ): RedirectResponse {
        $attempt = $this->attemptForFinalStep($request, app(LearnerFlowService::class), $taskKey);
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        return match ($taskKey) {
            'task-1' => $this->submitTaskOne($request, $attempt, $itemSelection, $answerMatching, $audioStorage, $analysis, $crla, $mode),
            'task-2a' => $this->submitTaskTwoA($request, $attempt, $itemSelection, $answerMatching, $audioStorage, $analysis, $mode),
            'task-2b' => $this->submitTaskTwoB($request, $attempt, $itemSelection, $answerMatching, $audioStorage, $analysis, $crla, $sentenceScoring, $mode),
            'passage' => $this->submitPassage($request, $attempt, $itemSelection, $reading, $audioStorage, $analysis, $mode),
            'comprehension' => $this->submitComprehension($request, $attempt, $itemSelection, $answerMatching, $reading, $comparison),
            default => abort(404),
        };
    }

    public function summary(Request $request, FinalAssessmentComparisonService $comparison): Response|RedirectResponse
    {
        $attempt = $this->attemptForFinalStep($request, app(LearnerFlowService::class), 'summary', true);
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        return redirect()->route('learner.completion');
    }

    private function submitTaskOne(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        CrlaScoringService $crla,
        AssessmentModeService $mode
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_1_LETTER);
        $this->validateSubmittedAssessmentItemSet($items, $validated['responses']);
        $score = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, $analysis, 'FINAL_CRLA_TASK_1_SCORING_V1', $mode->canShowManualFallback($request, $attempt, $attempt->learner));
        $route = $crla->routeTaskOne($score);

        $attempt->update([
            'task_1_score' => $score,
            'task_2a_score' => $route['assigned_task_2a_score'],
            'status' => 'task_1_completed',
            'rule_applied' => $route['rule_applied'],
            'decision_reason' => $route['requires_task_2a']
                ? 'Final Task 1 score is 0-6, so Task 2A is required.'
                : 'Final Task 1 score is 7-10, so Task 2A receives an automatic score of 10.',
        ]);

        return redirect()->route('final-assessment.task', $route['requires_task_2a'] ? 'task-2a' : 'task-2b');
    }

    private function submitTaskTwoA(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AssessmentModeService $mode
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2A_RHYME);
        $this->validateSubmittedAssessmentItemSet($items, $validated['responses']);
        $score = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, $analysis, 'FINAL_CRLA_TASK_2A_SCORING_V1', $mode->canShowManualFallback($request, $attempt, $attempt->learner));

        $attempt->update(['task_2a_score' => $score, 'status' => 'task_2a_completed']);

        return redirect()->route('final-assessment.task', 'task-2b');
    }

    private function submitTaskTwoB(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        CrlaScoringService $crla,
        SentenceReadingScoringService $sentenceScoring,
        AssessmentModeService $mode
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE);
        $this->validateSubmittedAssessmentItemSet($items, $validated['responses']);
        $taskTwoBReview = $this->scoreSentenceResponses($attempt, $items, $validated['responses'], $audioStorage, $analysis, $sentenceScoring, 'FINAL_CRLA_TASK_2B_SCORING_V2', $mode->canShowManualFallback($request, $attempt, $attempt->learner));
        $taskTwoBScore = $taskTwoBReview['task_score'];
        $totalScore = $crla->calculateTotalScore((int) $attempt->task_1_score, (int) $attempt->task_2a_score, $taskTwoBScore);

        $attempt->update([
            'task_2b_score' => $taskTwoBScore,
            'crla_total_score' => $totalScore,
            'crla_classification' => $crla->classifyTotalScore($totalScore),
            'status' => 'crla_completed',
        ]);

        return redirect()->route('final-assessment.task', 'passage');
    }

    private function submitPassage(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentItemSelectionService $itemSelection,
        ReadingComprehensionScoringService $reading,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AssessmentModeService $mode
    ): RedirectResponse {
        $allowManualFallback = $mode->canShowManualFallback($request, $attempt, $attempt->learner);

        $validated = $request->validate([
            'incorrect_words' => [$allowManualFallback ? 'required' : 'nullable', 'integer', 'min:0', 'max:50'],
            'audio' => AudioStorageService::validationRules(),
            'audio_file_id' => ['nullable', 'integer', 'exists:audio_files,id'],
            'duration_seconds' => AudioStorageService::durationValidationRules(),
        ], $this->friendlyValidationMessages());
        $passage = $itemSelection->selectReadingPassageForAttempt($attempt);
        $incorrectWords = (int) ($validated['incorrect_words'] ?? 0);
        $audioFile = isset($validated['audio_file_id']) && $validated['audio_file_id']
            ? AudioFile::query()
                ->where('learner_id', $attempt->learner_id)
                ->where('assessment_attempt_id', $attempt->id)
                ->find($validated['audio_file_id'])
            : null;

        if (! $audioFile && $request->hasFile('audio')) {
            $audioFile = $audioStorage->store(
                file: $request->file('audio'),
                learner: $attempt->learner,
                recordingContext: 'final_passage_reading',
                assessmentAttempt: $attempt,
                durationSeconds: isset($validated['duration_seconds']) ? (float) $validated['duration_seconds'] : null
            );
        }

        $transcriptText = '';

        if ($audioFile) {
            $transcriptText = trim((string) $audioFile->transcript);
            $analysisContext = [
                'expected_text' => (string) ($passage?->prompt_snapshot['prompt'] ?? ''),
                'prompt_id' => $passage?->source_csv_id,
                'task_type' => 'final_reading_passage',
                'content_metadata' => ['prompt_snapshot' => $passage?->prompt_snapshot ?? [], 'is_final_reassessment' => true],
                'debug' => (bool) config('readirect_ai.debug.show_admin_debug'),
            ];

            if ($transcriptText === '') {
                $resolved = $analysis->resolve(null, $audioFile, $analysisContext, $this->sttOptionsForPassage($passage));

                if (! $analysis->canComplete($resolved, $analysisContext) && ! $allowManualFallback) {
                    throw ValidationException::withMessages([
                        'audio' => $analysis->completionFailureMessage($resolved, $analysisContext),
                    ]);
                }

                $transcriptText = trim((string) $resolved['transcript']);
            }

            if ($transcriptText !== '') {
                $incorrectWords = $reading->calculateIncorrectWordCount(
                    (string) ($passage?->prompt_snapshot['prompt'] ?? ''),
                    $transcriptText,
                );
            }
        }

        if (! $allowManualFallback && (! $audioFile || $transcriptText === '')) {
            throw ValidationException::withMessages([
                'audio' => 'Please record your reading clearly before continuing.',
            ]);
        }

        $attempt->update([
            'incorrect_words' => $incorrectWords,
            'reading_accuracy' => $reading->calculateAccuracyPercentage($incorrectWords),
            'status' => 'passage_completed',
        ]);

        return redirect()->route('final-assessment.task', 'comprehension');
    }

    private function submitComprehension(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        ReadingComprehensionScoringService $reading,
        FinalAssessmentComparisonService $comparison
    ): RedirectResponse {
        $validated = $request->validate([
            'responses' => ['required', 'array', 'size:5'],
            'responses.*.question_id' => ['required', 'string'],
            'responses.*.answer' => ['required', 'string', 'max:255', 'regex:/\S/'],
        ], $this->friendlyValidationMessages());

        $passage = $this->readingPassage($attempt, $itemSelection);
        $questions = $this->questionsForPassage($passage);
        $correct = 0;

        foreach ($questions as $question) {
            $submitted = collect($validated['responses'])->firstWhere('question_id', $question['id']);
            $answer = $submitted['answer'] ?? '';
            $isCorrect = $answerMatching->isAcceptedAnswer($answer, $question['accepted_answers']);
            $correct += $isCorrect ? 1 : 0;

            AssessmentTaskResponse::create([
                'assessment_attempt_id' => $attempt->id,
                'learner_id' => $attempt->learner_id,
                'learning_content_id' => $question['learning_content_id'],
                'task_key' => 'final_reading_comprehension',
                'task_type' => 'comprehension_question',
                'item_number' => $question['sequence'],
                'prompt' => $question['question_text'],
                'expected_answer' => $question['correct_answer'],
                'selected_answer' => $answer,
                'response_text' => $answer,
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? 1 : 0,
                'rule_applied' => 'FINAL_COMPREHENSION_EXACT_MATCH_V1',
                'metadata' => ['source_csv_id' => $question['id']],
                'metadata_json' => ['choices' => $question['choices']],
            ]);
        }

        $comprehension = $reading->calculateComprehensionPercentage($correct, 5);
        $finalScore = $reading->calculateFinalReadingScore($comprehension, (float) $attempt->reading_accuracy);
        $classification = $reading->classifyReadingLevelFromFinalScore($finalScore);

        $attempt->update([
            'comprehension_correct_count' => $correct,
            'comprehension_percentage' => $comprehension,
            'final_reading_score' => $finalScore,
            'reading_classification' => $classification,
            'status' => 'final_reassessment_completed',
            'completed_at' => now(),
        ]);

        $attempt->refresh();
        $comparisonSummary = $comparison->compareAttempts($attempt->baselineAssessment, $attempt);
        $attempt->update(['comparison_summary' => $comparisonSummary]);
        $attempt->learner->update(['current_stage' => LearnerStage::FINAL_REASSESSMENT_COMPLETED]);

        return redirect()->route('learner.completion');
    }

    private function scoreTextResponses(
        AssessmentAttempt $attempt,
        Collection $items,
        array $responses,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        string $rule,
        bool $allowManualFallback = true
    ): int {
        $score = 0;

        foreach ($items as $item) {
            $submittedIndex = collect($responses)->search(fn ($response) => (int) ($response['assessment_attempt_item_id'] ?? 0) === (int) $item->id);
            $submitted = $submittedIndex === false ? [] : $responses[$submittedIndex];
            $audioFile = isset($submitted['audio_file_id']) && $submitted['audio_file_id']
                ? AudioFile::where('learner_id', $attempt->learner_id)
                    ->where('assessment_attempt_id', $attempt->id)
                    ->find($submitted['audio_file_id'])
                : null;

            $audioFile = $audioFile ?: (isset($submitted['audio']) && $submitted['audio']
                ? $audioStorage->store(
                    file: $submitted['audio'],
                    learner: $attempt->learner,
                    recordingContext: 'final_assessment_task',
                    assessmentAttempt: $attempt,
                    durationSeconds: isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
                    metadata: ['assessment_attempt_item_id' => $item->id, 'task_type' => $item->task_type]
                )
                : null);
            $expectedAnswer = $this->expectedAnswer($item);
            $acceptedAnswers = $this->acceptedAnswersForItem($item, $expectedAnswer);
            $analysisContext = $this->analysisContext($item, $expectedAnswer, $acceptedAnswers);
            $resolved = $analysis->resolve(
                $allowManualFallback ? ($submitted['answer'] ?? null) : null,
                $audioFile,
                $analysisContext
            );
            $answer = $resolved['transcript'];
            $displayedAnswer = $resolved['displayed_transcript'] ?? $answer;

            if (! $analysis->canComplete($resolved, $analysisContext)) {
                throw ValidationException::withMessages([
                    'responses.'.($submittedIndex === false ? 0 : $submittedIndex).'.answer' => $analysis->completionFailureMessage($resolved, $analysisContext),
                ]);
            }

            $isCorrect = $answerMatching->isAcceptedAnswer($answer, $acceptedAnswers)
                || $analysis->acceptedForShortPrompt($resolved['ai_response'] ?? null);
            $score += $isCorrect ? 1 : 0;

            $response = AssessmentTaskResponse::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'assessment_attempt_item_id' => $item->id],
                array_merge([
                    'learner_id' => $attempt->learner_id,
                    'learning_content_id' => $item->learning_content_id,
                    'audio_file_id' => $audioFile?->id,
                    'task_key' => 'final_'.$item->task_type,
                    'task_type' => $item->task_type,
                    'item_number' => $item->sequence,
                    'prompt' => $item->prompt_snapshot['prompt'] ?? null,
                    'expected_answer' => $expectedAnswer,
                    'learner_transcript' => $answer,
                    'transcript_source' => $resolved['source'],
                    'stt_confidence' => $resolved['confidence'],
                    'response_text' => $displayedAnswer,
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? 1 : 0,
                    'error_type' => $isCorrect ? null : 'incorrect_general',
                    'rule_applied' => $rule,
                    'metadata' => ['source_csv_id' => $item->source_csv_id, 'is_final_reassessment' => true],
                    'metadata_json' => [
                        'prompt_snapshot' => $item->prompt_snapshot,
                        'asr_scoring_debug' => $this->asrScoringDebug($attempt, $item, $expectedAnswer, $answer, $displayedAnswer, $isCorrect ? 1 : 0, $audioFile, $resolved),
                    ],
                ], $analysis->responseFields($resolved['ai_response'] ?? null))
            );

            if ($audioFile) {
                $audioStorage->attachToAssessmentResponse($audioFile, $response->id);
            }

            $item->update(['answered_at' => now()]);
        }

        return $score;
    }

    private function scoreSentenceResponses(
        AssessmentAttempt $attempt,
        Collection $items,
        array $responses,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        SentenceReadingScoringService $sentenceScoring,
        string $rule,
        bool $allowManualFallback = true
    ): array {
        $evaluations = [];

        foreach ($items as $item) {
            $submittedIndex = collect($responses)->search(fn ($response) => (int) ($response['assessment_attempt_item_id'] ?? 0) === (int) $item->id);
            $submitted = $submittedIndex === false ? [] : $responses[$submittedIndex];
            $audioFile = isset($submitted['audio_file_id']) && $submitted['audio_file_id']
                ? AudioFile::where('learner_id', $attempt->learner_id)
                    ->where('assessment_attempt_id', $attempt->id)
                    ->find($submitted['audio_file_id'])
                : null;

            $audioFile = $audioFile ?: (isset($submitted['audio']) && $submitted['audio']
                ? $audioStorage->store(
                    file: $submitted['audio'],
                    learner: $attempt->learner,
                    recordingContext: 'final_assessment_task',
                    assessmentAttempt: $attempt,
                    durationSeconds: isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
                    metadata: ['assessment_attempt_item_id' => $item->id, 'task_type' => $item->task_type]
                )
                : null);
            $expectedAnswer = $this->expectedAnswer($item);
            $acceptedAnswers = $this->acceptedAnswersForItem($item, $expectedAnswer);
            $analysisContext = $this->analysisContext($item, $expectedAnswer, $acceptedAnswers);
            $resolved = $analysis->resolve(
                $allowManualFallback ? ($submitted['answer'] ?? null) : null,
                $audioFile,
                $analysisContext
            );
            $answer = $resolved['transcript'];
            $displayedAnswer = $resolved['displayed_transcript'] ?? $answer;

            if (! $analysis->canComplete($resolved, $analysisContext)) {
                throw ValidationException::withMessages([
                    'responses.'.($submittedIndex === false ? 0 : $submittedIndex).'.answer' => $analysis->completionFailureMessage($resolved, $analysisContext),
                ]);
            }

            $sentencePrompt = (string) ($expectedAnswer ?? $item->prompt_snapshot['prompt'] ?? '');
            $evaluation = $sentenceScoring->evaluate($sentencePrompt, $answer, $audioFile?->duration_seconds, $resolved['ai_response'] ?? null);
            $evaluations[] = $evaluation;
            $isCorrect = ($evaluation['accuracy_percentage'] ?? 0) >= 80;

            $response = AssessmentTaskResponse::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'assessment_attempt_item_id' => $item->id],
                array_merge([
                    'learner_id' => $attempt->learner_id,
                    'learning_content_id' => $item->learning_content_id,
                    'audio_file_id' => $audioFile?->id,
                    'task_key' => 'final_'.$item->task_type,
                    'task_type' => $item->task_type,
                    'item_number' => $item->sequence,
                    'prompt' => $item->prompt_snapshot['prompt'] ?? null,
                    'expected_answer' => $expectedAnswer,
                    'learner_transcript' => $answer,
                    'transcript_source' => $resolved['source'],
                    'stt_confidence' => $resolved['confidence'],
                    'response_text' => $displayedAnswer,
                    'is_correct' => $isCorrect,
                    'score' => $evaluation['score_ten'] ?? 0,
                    'error_type' => $isCorrect ? null : 'incorrect_general',
                    'rule_applied' => $rule,
                    'metadata' => ['source_csv_id' => $item->source_csv_id, 'is_final_reassessment' => true],
                    'metadata_json' => array_merge([
                        'prompt_snapshot' => $item->prompt_snapshot,
                        'asr_scoring_debug' => $this->asrScoringDebug($attempt, $item, $expectedAnswer, $answer, $displayedAnswer, $evaluation['score_ten'] ?? 0, $audioFile, $resolved),
                    ], $evaluation),
                ], $analysis->responseFields($resolved['ai_response'] ?? null))
            );

            if ($audioFile) {
                $audioStorage->attachToAssessmentResponse($audioFile, $response->id);
            }

            $item->update(['answered_at' => now()]);
        }

        return $sentenceScoring->summarize($evaluations);
    }

    private function taskItems(AssessmentAttempt $attempt, AssessmentItemSelectionService $itemSelection, string $taskType): Collection
    {
        $baseline = $attempt->baselineAssessment;

        if ($baseline) {
            $this->cloneBaselineItems($baseline, $attempt, $taskType);
        }

        return match ($taskType) {
            AssessmentItemSelectionService::TASK_1_LETTER => $itemSelection->selectTask1LettersForAttempt($attempt),
            AssessmentItemSelectionService::TASK_2A_RHYME => $itemSelection->selectTask2ARhymingPromptsForAttempt($attempt),
            AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE => $itemSelection->selectTask2BWordSentenceItemsForAttempt($attempt),
            default => $itemSelection->getLockedItemsForAttempt($attempt, $taskType),
        };
    }

    private function readingPassage(AssessmentAttempt $attempt, AssessmentItemSelectionService $itemSelection): ?AssessmentAttemptItem
    {
        if ($attempt->baselineAssessment) {
            $this->cloneBaselineItems($attempt->baselineAssessment, $attempt, AssessmentItemSelectionService::READING_PASSAGE);
        }

        return $itemSelection->selectReadingPassageForAttempt($attempt);
    }

    private function cloneBaselineItems(AssessmentAttempt $baseline, AssessmentAttempt $attempt, string $taskType): void
    {
        if ($attempt->selectedItems()->where('task_type', $taskType)->exists()) {
            return;
        }

        foreach ($baseline->selectedItems()->where('task_type', $taskType)->orderBy('sequence')->get() as $item) {
            AssessmentAttemptItem::create([
                'assessment_attempt_id' => $attempt->id,
                'learning_content_id' => $item->learning_content_id,
                'source_csv_id' => $item->source_csv_id,
                'task_type' => $item->task_type,
                'sequence' => $item->sequence,
                'prompt_snapshot' => $item->prompt_snapshot,
                'selected_at' => now(),
            ]);
        }
    }

    private function showTaskTwoA(Request $request, AssessmentAttempt $attempt, AssessmentItemSelectionService $itemSelection, CrlaScoringService $crla, AssessmentModeService $mode): Response|RedirectResponse
    {
        if (! $crla->shouldRequireTask2A((int) $attempt->task_1_score)) {
            return redirect()->route('final-assessment.task', 'task-2b');
        }

        return Inertia::render('Learner/FinalAssessment/Task2ARhymingWords', [
            'items' => $this->itemsForForm($items = $this->taskItems($attempt, $itemSelection, AssessmentItemSelectionService::TASK_2A_RHYME)),
            'initialIndex' => $this->initialIndexForItems($items),
            'assessmentAttemptId' => $attempt->id,
            'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
        ]);
    }

    private function learner(Request $request): Learner
    {
        return CurrentLearner::require($request);
    }

    private function attempt(Request $request): AssessmentAttempt
    {
        return AssessmentAttempt::with(['selectedItems', 'baselineAssessment'])->findOrFail($request->session()->get('final_assessment_attempt_id'));
    }

    private function attemptForFinalStep(Request $request, LearnerFlowService $flow, string $taskKey, bool $allowCompleted = false): AssessmentAttempt|RedirectResponse
    {
        $learner = $this->learner($request);
        $stage = LearnerStage::normalize($learner->current_stage);

        if (! in_array($stage, [
            LearnerStage::FINAL_REASSESSMENT_PENDING,
            LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS,
            LearnerStage::FINAL_REASSESSMENT_COMPLETED,
            LearnerStage::COMPLETED,
        ], true)) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'The final reassessment is not available yet.');
        }

        $attempt = $this->attemptFromRequest($request, $learner, $allowCompleted)
            ?? $flow->resolveFinalAttempt($request, $allowCompleted);

        if (! $attempt && $allowCompleted) {
            $attempt = $flow->latestFinalAttempt($learner);
        }

        if (! $attempt) {
            return $stage === LearnerStage::FINAL_REASSESSMENT_PENDING
                ? redirect()->route('final-assessment.start')
                : redirect()->route('learner.dashboard');
        }

        if ($flow->isFinalComplete($attempt)) {
            return $taskKey === 'summary'
                ? $attempt
                : redirect()->route('learner.completion')
                    ->with('info', 'Your final reassessment is complete.');
        }

        $learner->update(['current_stage' => LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS]);

        if (! $flow->finalTaskAllowed($attempt, $taskKey)) {
            $resumeTask = $flow->finalResumeTaskKey($attempt);

            return $resumeTask === 'summary'
                ? redirect()->route('final-assessment.summary')
                : redirect()->route('final-assessment.task', $resumeTask)
                    ->with('info', 'We brought you back to the next step.');
        }

        return $attempt;
    }

    private function attemptFromRequest(Request $request, Learner $learner, bool $allowCompleted = false): ?AssessmentAttempt
    {
        $attemptId = $request->input('assessment_attempt_id');

        if (! $attemptId) {
            return null;
        }

        $attempt = AssessmentAttempt::with(['selectedItems', 'baselineAssessment'])
            ->where('id', $attemptId)
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->first();

        if (! $attempt) {
            return null;
        }

        if (! $allowCompleted && app(LearnerFlowService::class)->isFinalComplete($attempt)) {
            return null;
        }

        $request->session()->put('final_assessment_attempt_id', $attempt->id);

        return $attempt;
    }

    private function baselineDiagnostic(Learner $learner): ?AssessmentAttempt
    {
        return AssessmentAttempt::where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->where('status', 'module_placement_completed')
            ->latest()
            ->first();
    }

    private function completedFinalAttempt(Learner $learner): ?AssessmentAttempt
    {
        return AssessmentAttempt::query()
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->where('status', LearnerFlowService::FINAL_COMPLETE)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    private function itemsForForm(Collection $items): array
    {
        return $items->map(fn (AssessmentAttemptItem $item) => $this->itemForForm($item))->values()->all();
    }

    private function itemForForm(?AssessmentAttemptItem $item): ?array
    {
        if (! $item) {
            return null;
        }

        $savedResponse = AssessmentTaskResponse::query()
            ->where('assessment_attempt_id', $item->assessment_attempt_id)
            ->where('assessment_attempt_item_id', $item->id)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        return [
            'id' => $item->id,
            'sequence' => $item->sequence,
            'source_csv_id' => $item->source_csv_id,
            'prompt' => $item->prompt_snapshot['prompt'] ?? '',
            'title' => $item->prompt_snapshot['title'] ?? '',
            'payload' => $this->payloadForForm($item),
            'accepted_answers' => $item->prompt_snapshot['accepted_answers'] ?? [],
            'answered_at' => $item->answered_at?->toISOString(),
            'saved_response' => $savedResponse ? [
                'answer' => $savedResponse->learner_transcript ?: $savedResponse->response_text,
                'displayed_transcript' => $savedResponse->response_text,
                'audio_file_id' => $savedResponse->audio_file_id,
                'transcript_source' => $savedResponse->transcript_source,
            ] : null,
        ];
    }

    private function initialIndexForItems(Collection $items): int
    {
        $values = $items->values();
        $firstUnanswered = $values->search(fn (AssessmentAttemptItem $item) => $item->answered_at === null);

        if ($firstUnanswered === false) {
            return max(0, $values->count() - 1);
        }

        return (int) $firstUnanswered;
    }

    private function expectedAnswer(AssessmentAttemptItem $item): ?string
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];

        if ($item->task_type === AssessmentItemSelectionService::TASK_2A_RHYME) {
            return $payload['expected_answer']
                ?? $payload['target_word']
                ?? collect($item->prompt_snapshot['accepted_answers'] ?? [])->first()
                ?? null;
        }

        if ($item->task_type === AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE) {
            return $payload['target_word'] ?? $payload['expected_answer'] ?? $item->prompt_snapshot['prompt'] ?? null;
        }

        return $payload['expected_answer'] ?? $payload['target_word'] ?? $item->prompt_snapshot['prompt'] ?? null;
    }

    private function acceptedAnswersForItem(AssessmentAttemptItem $item, ?string $expectedAnswer): array
    {
        if ($item->task_type === AssessmentItemSelectionService::TASK_2A_RHYME) {
            return trim((string) $expectedAnswer) !== '' ? [$expectedAnswer] : [];
        }

        return $item->prompt_snapshot['accepted_answers'] ?? [];
    }

    private function payloadForForm(AssessmentAttemptItem $item): array
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];

        if ($item->task_type === AssessmentItemSelectionService::TASK_2A_RHYME) {
            $target = $payload['target_word']
                ?? $payload['expected_answer']
                ?? collect($item->prompt_snapshot['accepted_answers'] ?? [])->first();

            if ($target) {
                $payload['target_word'] = $target;
                $payload['expected_answer'] = $target;
            }
        }

        return $payload;
    }

    private function analysisContext(AssessmentAttemptItem $item, ?string $expectedAnswer, array $acceptedAnswers): array
    {
        return [
            'expected_text' => $expectedAnswer,
            'accepted_answers' => $acceptedAnswers,
            'prompt_id' => $item->source_csv_id,
            'assessment_type' => $item->assessmentAttempt?->attempt_type ?? 'final_reassessment',
            'item_id' => $item->id,
            'learner_id' => $item->assessmentAttempt?->learner_id,
            'attempt_id' => $item->assessment_attempt_id,
            'task_type' => $item->task_type,
            'activity_type' => $item->task_type,
            'current_scoring_context' => [
                'accepted_answers' => $acceptedAnswers,
                'source_csv_id' => $item->source_csv_id,
                'prompt_snapshot' => $item->prompt_snapshot,
            ],
            'content_metadata' => ['prompt_snapshot' => $item->prompt_snapshot, 'is_final_reassessment' => true],
            'debug' => (bool) config('readirect_ai.debug.show_admin_debug'),
        ];
    }

    private function asrScoringDebug(
        AssessmentAttempt $attempt,
        AssessmentAttemptItem $item,
        ?string $expectedAnswer,
        string $scoringTranscript,
        string $displayedTranscript,
        mixed $scoreGiven,
        ?AudioFile $audioFile,
        array $resolved
    ): array {
        $ai = $resolved['ai_response'] ?? [];

        return [
            'learner_id' => $attempt->learner_id,
            'attempt_id' => $attempt->id,
            'assessment_type' => $attempt->attempt_type,
            'module_type' => $item->prompt_snapshot['payload']['module_key'] ?? null,
            'activity_type' => $item->task_type,
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

    private function sttOptionsForPassage(?AssessmentAttemptItem $passage): array
    {
        return [];
    }

    private function questionsForPassage(?AssessmentAttemptItem $passage): array
    {
        $passageCsvId = $passage?->source_csv_id;
        $savedAnswers = AssessmentTaskResponse::query()
            ->where('assessment_attempt_id', $passage?->assessment_attempt_id)
            ->where('task_type', 'comprehension_question')
            ->get()
            ->mapWithKeys(fn (AssessmentTaskResponse $response) => [
                (string) ($response->metadata['source_csv_id'] ?? '') => $response->selected_answer ?: $response->response_text,
            ]);

        return LearningContent::where('content_type', 'comprehension_question')
            ->where('is_active', true)
            ->get()
            ->filter(fn (LearningContent $content) => ($content->payload['passage_id'] ?? null) === $passageCsvId)
            ->sortBy(fn (LearningContent $content) => $content->payload['sequence'] ?? 0)
            ->values()
            ->map(fn (LearningContent $content) => [
                'learning_content_id' => $content->id,
                'id' => $content->payload['source_csv_id'] ?? (string) $content->id,
                'sequence' => $content->payload['sequence'] ?? 0,
                'question_text' => $content->prompt,
                'question_type' => $content->payload['question_type'] ?? 'multiple_choice',
                'correct_answer' => $content->payload['correct_answer'] ?? '',
                'accepted_answers' => $content->accepted_answers ?? [],
                'choices' => $content->payload['choices'] ?? [],
                'saved_answer' => $savedAnswers->get((string) ($content->payload['source_csv_id'] ?? $content->id)),
            ])
            ->all();
    }

    private function textResponseRules(int $requiredCount): array
    {
        return [
            'responses' => ['required', 'array', 'size:'.$requiredCount],
            'responses.*.assessment_attempt_item_id' => ['required', 'integer', 'exists:assessment_attempt_items,id'],
            'responses.*.answer' => ['nullable', 'string', 'max:5000'],
            'responses.*.transcript_source' => ['nullable', 'string', 'in:manual,ai_asr,stt_auto,stt_placeholder,teacher_review,future_asr'],
            'responses.*.audio_file_id' => ['nullable', 'integer', 'exists:audio_files,id'],
            'responses.*.audio' => AudioStorageService::validationRules(),
            'responses.*.duration_seconds' => AudioStorageService::durationValidationRules(),
        ];
    }

    private function validateSubmittedAssessmentItemSet(Collection $items, array $responses): void
    {
        if (! SubmittedItemSet::idsMatch($items, $responses, 'assessment_attempt_item_id')) {
            throw ValidationException::withMessages([
                'responses' => 'Almost there! Continue from the current step.',
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
            'incorrect_words.required' => 'Add the number of words to review before moving on.',
            'incorrect_words.integer' => 'Use a whole number for words to review.',
            'duration_seconds.min' => 'That recording was too short. Please try again and speak clearly.',
            'responses.*.duration_seconds.min' => 'That recording was too short. Please try again and speak clearly.',
        ];
    }
}
