<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\AssessmentTaskResponse;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Services\Agents\AgentCommentaryService;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AnswerMatchingService;
use App\Services\AssessmentItemSelectionService;
use App\Services\AssessmentModeService;
use App\Services\AudioStorageService;
use App\Services\CrlaScoringService;
use App\Services\DiagnosticPlacementService;
use App\Services\LearnerFlowService;
use App\Services\ModulePlacementService;
use App\Services\ReadingComprehensionScoringService;
use App\Services\SentenceReadingScoringService;
use App\Services\TaskTwoARhymeDecisionScoringService;
use App\Support\CurrentLearner;
use App\Support\LearnerStage;
use App\Support\SubmittedItemSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosticAssessmentController extends Controller
{
    public function start(Request $request, LearnerFlowService $flow): Response|RedirectResponse
    {
        $learner = $this->learner($request);
        $activeAttempt = $flow->resolveDiagnosticAttempt($request);
        $latestAttempt = $flow->latestDiagnosticAttempt($learner);

        if (
            in_array(LearnerStage::normalize($learner->current_stage), [LearnerStage::FINAL_REASSESSMENT_COMPLETED, LearnerStage::COMPLETED], true)
            || $this->hasCompletedFinalAttempt($learner)
        ) {
            return redirect()->route('learner.completion')
                ->with('info', 'You already completed your reading journey.');
        }

        if ($activeAttempt) {
            $learner->update(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);

            return redirect($flow->diagnosticResumeRoute($activeAttempt));
        }

        if ($flow->isDiagnosticComplete($latestAttempt) && ! $this->canUseDeveloperRetest($request)) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'Your diagnostic is already complete. Continue from your dashboard.');
        }

        return Inertia::render('Learner/DiagnosticStart', [
            'developerRetest' => [
                'enabled' => $this->canUseDeveloperRetest(request()),
            ],
        ]);
    }

    public function storeStart(Request $request, AssessmentItemSelectionService $itemSelection, LearnerFlowService $flow): RedirectResponse
    {
        $learner = $this->learner($request);
        $activeAttempt = $flow->resolveDiagnosticAttempt($request);

        if (
            in_array(LearnerStage::normalize($learner->current_stage), [LearnerStage::FINAL_REASSESSMENT_COMPLETED, LearnerStage::COMPLETED], true)
            || $this->hasCompletedFinalAttempt($learner)
        ) {
            return redirect()->route('learner.completion')
                ->with('info', 'You already completed your reading journey.');
        }

        if ($activeAttempt) {
            $learner->update(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);

            return redirect($flow->diagnosticResumeRoute($activeAttempt));
        }

        if ($flow->isDiagnosticComplete($flow->latestDiagnosticAttempt($learner)) && ! $this->canUseDeveloperRetest($request)) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'Your diagnostic is already complete. Continue from your dashboard.');
        }

        $agent = AgentProfile::where('key', AgentProfile::ASSESSMENT)->first();

        $attempt = $this->createDiagnosticAttempt($learner, (bool) $request->session()->get('admin_testing_mode'), $agent?->id);

        $itemSelection->selectTask1LettersForAttempt($attempt);
        $request->session()->put('assessment_attempt_id', $attempt->id);
        if ($attempt->is_sandbox) {
            $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);
        }
        $learner->update(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);

        return redirect()->route('learner.diagnostic.task-1');
    }

    public function developerRetest(Request $request, AssessmentItemSelectionService $itemSelection): RedirectResponse
    {
        abort_unless($this->canUseDeveloperRetest($request), 403);

        $learner = $this->learner($request);
        $agent = AgentProfile::where('key', AgentProfile::ASSESSMENT)->first();
        $attempt = $this->createDiagnosticAttempt($learner, true, $agent?->id);

        $itemSelection->selectTask1LettersForAttempt($attempt);
        $request->session()->put('assessment_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);

        return redirect()
            ->route('learner.diagnostic.task-1')
            ->with('success', 'Developer test attempt created. Previous attempts were preserved for QA review.');
    }

    public function taskOne(Request $request, AssessmentItemSelectionService $itemSelection, LearnerFlowService $flow, AssessmentModeService $mode): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'task-1');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        $items = $itemSelection->selectTask1LettersForAttempt($attempt);

        return Inertia::render('Learner/Task1LetterPronunciation', [
            'items' => $this->itemsForForm($items),
            'initialIndex' => $this->initialIndexForItems($items),
            'assessmentAttemptId' => $attempt->id,
            'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
        ]);
    }

    public function storeTaskOne(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AgentCommentaryService $commentary,
        CrlaScoringService $crla,
        AssessmentModeService $mode
    ): RedirectResponse {
        $attempt = $this->attemptForStep($request, app(LearnerFlowService::class), 'task-1');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_1_LETTER);
        $this->validateSubmittedAssessmentItemSet($items, $validated['responses']);
        $score = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, $analysis, $commentary, 'CRLA_TASK_1_SCORING_V1', $mode->canShowManualFallback($request, $attempt, $attempt->learner));
        $route = $crla->routeTaskOne($score);

        $attempt->update([
            'task_1_score' => $score,
            'task_2a_score' => $route['assigned_task_2a_score'],
            'status' => 'task_1_completed',
            'rule_applied' => $route['rule_applied'],
            'decision_reason' => $route['requires_task_2a']
                ? 'Task 1 score is 0-6, so Task 2A is required.'
                : 'Task 1 score is 7-10, so Task 2A receives an automatic score of 10.',
        ]);

        $request->session()->put('task_one_route', $route);

        return redirect()->route('learner.diagnostic.task-routing');
    }

    public function taskRouting(Request $request, LearnerFlowService $flow): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'task-routing');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        $storedRoute = $request->session()->get('task_one_route', []);
        $requiresTask2A = (int) $attempt->task_1_score <= 6;
        $route = [
            'requires_task_2a' => $storedRoute['requires_task_2a'] ?? $requiresTask2A,
            'assigned_task_2a_score' => $storedRoute['assigned_task_2a_score'] ?? ($requiresTask2A ? null : 10),
            'next_task' => $storedRoute['next_task'] ?? ($requiresTask2A ? 'task_2a' : 'task_2b'),
            'rule_applied' => $storedRoute['rule_applied'] ?? 'CRLA_TASK_1_ROUTING_V1',
        ];

        return Inertia::render('Learner/TaskRoutingResult', [
            'attempt' => $attempt->only('task_1_score', 'task_2a_score', 'decision_reason'),
            'route' => $route,
        ]);
    }

    public function taskTwoA(Request $request, AssessmentItemSelectionService $itemSelection, CrlaScoringService $crla, AssessmentModeService $mode): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, app(LearnerFlowService::class), 'task-2a');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        if (! $crla->shouldRequireTask2A((int) $attempt->task_1_score)) {
            return redirect()->route('learner.diagnostic.task-2b');
        }

        $items = $itemSelection->selectTask2ARhymingPromptsForAttempt($attempt);

        return Inertia::render('Learner/Task2ARhymingWords', [
            'items' => $this->itemsForForm($items),
            'initialIndex' => $this->initialIndexForItems($items),
            'assessmentAttemptId' => $attempt->id,
            'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
        ]);
    }

    public function storeTaskTwoA(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        CrlaScoringService $crla,
        TaskTwoARhymeDecisionScoringService $rhymeDecisionScoring
    ): RedirectResponse {
        $attempt = $this->attemptForStep($request, app(LearnerFlowService::class), 'task-2a');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        if (! $crla->shouldRequireTask2A((int) $attempt->task_1_score)) {
            return redirect()->route('learner.diagnostic.task-2b');
        }

        $validated = $request->validate($this->rhymeDecisionResponseRules(), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2A_RHYME);
        $this->validateSubmittedAssessmentItemSet($items, $validated['responses']);
        $score = $rhymeDecisionScoring->score($attempt, $items, $validated['responses'], 'CRLA_TASK_2A_RHYME_DECISION_V1');

        $attempt->update(array_merge(
            [
                'task_2a_score' => $score,
                'status' => 'crla_completed',
            ],
            $crla->completeWithoutTask2BOrPassage((int) $attempt->task_1_score, $score),
        ));

        return redirect()->route('learner.diagnostic.crla-summary');
    }

    public function taskTwoASummary(Request $request, LearnerFlowService $flow): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'task-2a-summary');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        return Inertia::render('Learner/Task2ASummary', [
            'attempt' => $attempt->only('task_1_score', 'task_2a_score', 'decision_reason'),
        ]);
    }

    public function taskTwoB(Request $request, AssessmentItemSelectionService $itemSelection, LearnerFlowService $flow, AssessmentModeService $mode, CrlaScoringService $crla): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'task-2b');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        if (! $crla->canProceedToTask2B((int) $attempt->task_1_score)) {
            return redirect()->route('learner.diagnostic.crla-summary');
        }

        $items = $itemSelection->selectTask2BWordSentenceItemsForAttempt($attempt);

        return Inertia::render('Learner/Task2BWordInSentence', [
            'items' => $this->itemsForForm($items),
            'initialIndex' => $this->initialIndexForItems($items),
            'assessmentAttemptId' => $attempt->id,
            'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
        ]);
    }

    public function storeTaskTwoB(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AgentCommentaryService $commentary,
        CrlaScoringService $crla,
        SentenceReadingScoringService $sentenceScoring,
        AssessmentModeService $mode
    ): RedirectResponse {
        $attempt = $this->attemptForStep($request, app(LearnerFlowService::class), 'task-2b');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        if (! $crla->canProceedToTask2B((int) $attempt->task_1_score)) {
            return redirect()->route('learner.diagnostic.crla-summary');
        }

        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE);
        $this->validateSubmittedAssessmentItemSet($items, $validated['responses']);
        $taskTwoBReview = $this->scoreSentenceResponses($attempt, $items, $validated['responses'], $audioStorage, $analysis, $commentary, $sentenceScoring, 'CRLA_TASK_2B_SCORING_V2', $mode->canShowManualFallback($request, $attempt, $attempt->learner));
        $taskTwoBScore = $taskTwoBReview['task_score'];
        $taskTwoAScore = (int) $attempt->task_2a_score;
        $totalScore = $crla->calculateTotalScore((int) $attempt->task_1_score, $taskTwoAScore, $taskTwoBScore);
        $classification = $crla->classifyTotalScore($totalScore);

        $fields = [
            'task_2b_score' => $taskTwoBScore,
            'crla_total_score' => $totalScore,
            'crla_classification' => $classification,
            'status' => 'crla_completed',
        ];

        if (! $crla->shouldAdministerPassage((int) $attempt->task_1_score, $totalScore)) {
            $fields = array_merge($fields, $crla->ineligiblePassageFields());
        }

        $attempt->update($fields);

        return redirect()->route('learner.diagnostic.crla-summary');
    }

    public function crlaSummary(Request $request, LearnerFlowService $flow, ModulePlacementService $placementService, CrlaScoringService $crla): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'crla-summary');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        $passageEligible = $crla->shouldAdministerPassage((int) $attempt->task_1_score, (int) $attempt->crla_total_score);

        return Inertia::render('Learner/CrlaSummary', [
            'attempt' => $attempt->only('task_1_score', 'task_2a_score', 'task_2b_score', 'crla_total_score', 'crla_classification'),
            'placementPreview' => $placementService->crlaSummary((string) $attempt->crla_classification, $passageEligible),
            'taskTwoBReview' => $this->taskTwoBSummary($attempt),
            'passageEligible' => $passageEligible,
        ]);
    }

    public function readingIntro(Request $request, LearnerFlowService $flow): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'reading-intro');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        return Inertia::render('Learner/ReadingIntro');
    }

    public function passage(Request $request, AssessmentItemSelectionService $itemSelection, LearnerFlowService $flow, AssessmentModeService $mode): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'passage');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        $passage = $itemSelection->selectReadingPassageForAttempt($attempt);

        return Inertia::render('Learner/PassageReading', [
            'passage' => $this->itemForForm($passage),
            'assessmentAttemptId' => $attempt->id,
            'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
        ]);
    }

    public function storePassage(Request $request, ReadingComprehensionScoringService $reading, AudioStorageService $audioStorage, AIAnalysisResolver $analysis, AssessmentModeService $mode): RedirectResponse
    {
        $attempt = $this->attemptForStep($request, app(LearnerFlowService::class), 'passage');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }
        $allowManualFallback = $mode->canShowManualFallback($request, $attempt, $attempt->learner);

        $validated = $request->validate([
            'incorrect_words' => [$allowManualFallback ? 'required' : 'nullable', 'integer', 'min:0', 'max:50'],
            'audio' => AudioStorageService::validationRules(),
            'audio_file_id' => ['nullable', 'integer', 'exists:audio_files,id'],
            'duration_seconds' => $this->passageDurationValidationRules(),
        ], $this->friendlyValidationMessages());

        $passage = app(AssessmentItemSelectionService::class)->selectReadingPassageForAttempt($attempt);
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
                recordingContext: 'passage_reading',
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
                'task_type' => 'reading_passage',
                'content_metadata' => ['prompt_snapshot' => $passage?->prompt_snapshot ?? []],
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
                $passageResponse = AssessmentTaskResponse::query()
                    ->where('assessment_attempt_id', $attempt->id)
                    ->where('audio_file_id', $audioFile->id)
                    ->latest('updated_at')
                    ->latest('id')
                    ->first();
                $wordAlignment = data_get($passageResponse?->metadata_json, 'word_alignment', data_get($passageResponse?->metadata_json, 'asr.word_alignment', []));
                $incorrectWords = $reading->calculateIncorrectWordCount(
                    (string) ($passage?->prompt_snapshot['prompt'] ?? ''),
                    $transcriptText,
                    is_array($wordAlignment) ? $wordAlignment : [],
                );
            }
        }

        if (! $allowManualFallback && (! $audioFile || $transcriptText === '')) {
            throw ValidationException::withMessages([
                'audio' => 'Please record your reading clearly before continuing.',
            ]);
        }

        $accuracy = $reading->calculateAccuracyPercentage($incorrectWords);

        $attempt->update([
            'incorrect_words' => $incorrectWords,
            'reading_accuracy' => $accuracy,
            'status' => 'passage_completed',
        ]);

        return redirect()->route('learner.diagnostic.comprehension');
    }

    public function comprehension(Request $request, AssessmentItemSelectionService $itemSelection, LearnerFlowService $flow, AssessmentModeService $mode): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'comprehension');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        $passage = $itemSelection->selectReadingPassageForAttempt($attempt);

        return Inertia::render('Learner/ComprehensionQuestions', [
            'questions' => $this->questionsForPassage($passage),
            'assessmentAttemptId' => $attempt->id,
            'assessmentMode' => $mode->props($request, $attempt, $attempt->learner),
        ]);
    }

    public function storeComprehension(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        ReadingComprehensionScoringService $reading
    ): RedirectResponse {
        $attempt = $this->attemptForStep($request, app(LearnerFlowService::class), 'comprehension');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        $validated = $request->validate([
            'responses' => ['required', 'array', 'size:5'],
            'responses.*.question_id' => ['required', 'string'],
            'responses.*.answer' => ['required', 'string', 'max:255', 'regex:/\S/'],
        ], $this->friendlyValidationMessages());

        $passage = $itemSelection->selectReadingPassageForAttempt($attempt);
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
                'task_key' => 'reading_comprehension',
                'task_type' => 'comprehension_question',
                'item_number' => $question['sequence'],
                'prompt' => $question['question_text'],
                'expected_answer' => $question['correct_answer'],
                'selected_answer' => $answer,
                'response_text' => $answer,
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? 1 : 0,
                'rule_applied' => 'COMPREHENSION_EXACT_MATCH_V1',
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
            'status' => 'reading_completed',
        ]);

        return redirect()->route('learner.diagnostic.reading-summary');
    }

    public function readingSummary(Request $request, LearnerFlowService $flow): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'reading-summary', true);
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }

        return Inertia::render('Learner/ReadingSummary', [
            'attempt' => $attempt->only(
                'incorrect_words',
                'reading_accuracy',
                'comprehension_correct_count',
                'comprehension_percentage',
                'final_reading_score',
                'reading_classification'
            ),
        ]);
    }

    public function modulePlacement(Request $request, DiagnosticPlacementService $placementService, LearnerFlowService $flow): Response|RedirectResponse
    {
        $attempt = $this->attemptForStep($request, $flow, 'module-placement');
        if ($attempt instanceof RedirectResponse) {
            return $attempt;
        }
        $placementResult = $placementService->completePlacement($attempt);
        $attempt = $placementResult['attempt'];
        $decision = $placementResult['decision'];
        $module = $placementResult['module'];

        return Inertia::render('Learner/ModulePlacementResult', [
            'attempt' => $attempt->only(
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'crla_classification',
                'reading_accuracy',
                'comprehension_correct_count',
                'comprehension_percentage',
                'final_reading_score',
                'reading_classification'
            ),
            'decision' => $decision,
            'module' => $module?->only('key', 'title', 'description'),
        ]);
    }

    private function learner(Request $request): Learner
    {
        return CurrentLearner::require($request);
    }

    private function createDiagnosticAttempt(Learner $learner, bool $sandbox = false, ?int $agentProfileId = null): AssessmentAttempt
    {
        return AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'agent_profile_id' => $agentProfileId,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'is_sandbox' => $sandbox,
            'started_at' => now(),
        ]);
    }

    private function canUseDeveloperRetest(Request $request): bool
    {
        return app(AssessmentModeService::class)->canResetLearnerFlow($request);
    }

    private function attempt(Request $request): AssessmentAttempt
    {
        return AssessmentAttempt::with('selectedItems')->findOrFail($request->session()->get('assessment_attempt_id'));
    }

    private function attemptForStep(Request $request, LearnerFlowService $flow, string $step, bool $allowCompleted = false): AssessmentAttempt|RedirectResponse
    {
        $learner = $flow->learner($request);

        if (
            in_array(LearnerStage::normalize($learner->current_stage), [LearnerStage::FINAL_REASSESSMENT_COMPLETED, LearnerStage::COMPLETED], true)
            || $this->hasCompletedFinalAttempt($learner)
        ) {
            return redirect()->route('learner.completion')
                ->with('info', 'You already completed your reading journey.');
        }

        $attempt = $this->attemptFromRequest($request, $learner, $allowCompleted)
            ?? $flow->resolveDiagnosticAttempt($request, $allowCompleted);

        if (! $attempt && $allowCompleted) {
            $attempt = $flow->latestDiagnosticAttempt($learner);
        }

        if (! $attempt) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'Continue your reading path from the dashboard.');
        }

        if ($flow->isDiagnosticComplete($attempt)) {
            return $step === 'reading-summary'
                ? $attempt
                : redirect()->route('learner.dashboard')
                    ->with('info', 'Your diagnostic is complete. Continue from your dashboard.');
        }

        $learner->update(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);

        if (! $flow->diagnosticStepAllowed($attempt, $step)) {
            return redirect($flow->diagnosticResumeRoute($attempt))
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

        $attempt = AssessmentAttempt::with('selectedItems')
            ->where('id', $attemptId)
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->first();

        if (! $attempt) {
            return null;
        }

        if (! $allowCompleted && app(LearnerFlowService::class)->isDiagnosticComplete($attempt)) {
            return null;
        }

        $request->session()->put('assessment_attempt_id', $attempt->id);

        return $attempt;
    }

    private function hasCompletedFinalAttempt(Learner $learner): bool
    {
        return AssessmentAttempt::query()
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->where('status', LearnerFlowService::FINAL_COMPLETE)
            ->whereNotNull('completed_at')
            ->exists();
    }

    private function scoreTextResponses(
        AssessmentAttempt $attempt,
        Collection $items,
        array $responses,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AgentCommentaryService $commentary,
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
                    recordingContext: 'assessment_task',
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
                $analysisContext,
                $this->sttOptionsForAssessmentItem($item)
            );
            $answer = $resolved['transcript'];
            $displayedAnswer = $resolved['displayed_transcript'] ?? $answer;
            $transcriptSource = $resolved['source'];

            if (! $analysis->canComplete($resolved, $analysisContext)) {
                throw ValidationException::withMessages([
                    'responses.'.($submittedIndex === false ? 0 : $submittedIndex).'.answer' => $analysis->completionFailureMessage($resolved, $analysisContext),
                ]);
            }

            $isCorrect = $answerMatching->isAcceptedAnswer($answer, $acceptedAnswers)
                || $analysis->acceptedForShortPrompt($resolved['ai_response'] ?? null);
            $score += $isCorrect ? 1 : 0;
            $agentCommentary = $commentary->generateCommentary([
                'mode' => 'assessment_neutral',
                'agent_type' => AgentProfile::ASSESSMENT,
                'learner_id' => $attempt->learner_id,
                'source_type' => 'assessment_task_response',
                'source_id' => null,
                'task_type' => $item->task_type,
                'expected_answer' => $expectedAnswer,
                'learner_answer' => $answer,
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? 1 : 0,
                'max_score' => 1,
                'template_feedback' => 'Thank you. Let us continue.',
                'attempt_number' => $item->sequence,
                'is_assessment' => true,
                'can_give_hint' => false,
            ]);
            $response = AssessmentTaskResponse::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'assessment_attempt_item_id' => $item->id],
                array_merge([
                    'learner_id' => $attempt->learner_id,
                    'learning_content_id' => $item->learning_content_id,
                    'audio_file_id' => $audioFile?->id,
                    'task_key' => $item->task_type,
                    'task_type' => $item->task_type,
                    'item_number' => $item->sequence,
                    'prompt' => $item->prompt_snapshot['prompt'] ?? null,
                    'expected_answer' => $expectedAnswer,
                    'learner_transcript' => $answer,
                    'transcript_source' => $transcriptSource,
                    'stt_confidence' => $resolved['confidence'],
                    'response_text' => $displayedAnswer,
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? 1 : 0,
                    'error_type' => $isCorrect ? null : 'incorrect_general',
                    'rule_applied' => $rule,
                    'agent_commentary_text' => $agentCommentary['message'],
                    'agent_commentary_source' => $agentCommentary['source'],
                    'agent_type' => $agentCommentary['agent_type'],
                    'metadata' => ['source_csv_id' => $item->source_csv_id],
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
        AgentCommentaryService $commentary,
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
                    recordingContext: 'assessment_task',
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
                $analysisContext,
                $this->sttOptionsForAssessmentItem($item)
            );
            $answer = $resolved['transcript'];
            $displayedAnswer = $resolved['displayed_transcript'] ?? $answer;
            $transcriptSource = $resolved['source'];

            if (! $analysis->canComplete($resolved, $analysisContext)) {
                throw ValidationException::withMessages([
                    'responses.'.($submittedIndex === false ? 0 : $submittedIndex).'.answer' => $analysis->completionFailureMessage($resolved, $analysisContext),
                ]);
            }

            $sentencePrompt = (string) ($expectedAnswer ?? $item->prompt_snapshot['prompt'] ?? '');
            $evaluation = $sentenceScoring->evaluate($sentencePrompt, $answer, $audioFile?->duration_seconds, $resolved['ai_response'] ?? null);
            $evaluations[] = $evaluation;

            $isCorrect = ($evaluation['accuracy_percentage'] ?? 0) >= 80;
            $agentCommentary = $commentary->generateCommentary([
                'mode' => 'assessment_neutral',
                'agent_type' => AgentProfile::ASSESSMENT,
                'learner_id' => $attempt->learner_id,
                'source_type' => 'assessment_task_response',
                'source_id' => null,
                'task_type' => $item->task_type,
                'expected_answer' => $expectedAnswer,
                'learner_answer' => $answer,
                'is_correct' => $isCorrect,
                'score' => $evaluation['score_ten'] ?? 0,
                'max_score' => 10,
                'template_feedback' => 'Thank you. Let us continue.',
                'attempt_number' => $item->sequence,
                'is_assessment' => true,
                'can_give_hint' => false,
            ]);

            $response = AssessmentTaskResponse::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'assessment_attempt_item_id' => $item->id],
                array_merge([
                    'learner_id' => $attempt->learner_id,
                    'learning_content_id' => $item->learning_content_id,
                    'audio_file_id' => $audioFile?->id,
                    'task_key' => $item->task_type,
                    'task_type' => $item->task_type,
                    'item_number' => $item->sequence,
                    'prompt' => $item->prompt_snapshot['prompt'] ?? null,
                    'expected_answer' => $expectedAnswer,
                    'learner_transcript' => $answer,
                    'transcript_source' => $transcriptSource,
                    'stt_confidence' => $resolved['confidence'],
                    'response_text' => $displayedAnswer,
                    'is_correct' => $isCorrect,
                    'score' => $evaluation['score_ten'] ?? 0,
                    'error_type' => $isCorrect ? null : 'incorrect_general',
                    'rule_applied' => $rule,
                    'agent_commentary_text' => $agentCommentary['message'],
                    'agent_commentary_source' => $agentCommentary['source'],
                    'agent_type' => $agentCommentary['agent_type'],
                    'metadata' => ['source_csv_id' => $item->source_csv_id],
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
                'answer' => $savedResponse->selected_answer ?: ($savedResponse->learner_transcript ?: $savedResponse->response_text),
                'displayed_transcript' => $savedResponse->response_text,
                'audio_file_id' => $savedResponse->audio_file_id,
                'transcript_source' => $savedResponse->transcript_source,
                'word_alignment' => data_get($savedResponse->metadata_json, 'word_alignment', data_get($savedResponse->metadata_json, 'asr.word_alignment', [])),
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
            'assessment_type' => $item->assessmentAttempt?->attempt_type ?? 'diagnostic',
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
            'content_metadata' => ['prompt_snapshot' => $item->prompt_snapshot],
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
            'threshold_used' => $ai['threshold_used'] ?? null,
            'debug_metadata' => $ai['debug_metadata'] ?? null,
            'audio_file_path' => $audioFile?->file_path ?? $audioFile?->path,
            'asr_confidence' => $resolved['confidence'],
            'created_at' => now()->toDateTimeString(),
        ];
    }

    private function sttOptionsForAssessmentItem(AssessmentAttemptItem $item): array
    {
        return match ($item->task_type) {
            'crla_task_1_letter' => [
                'prompt' => (string) ($item->prompt_snapshot['prompt'] ?? ''),
                'model_path' => $this->letterModelPath(),
                'beam_size' => 1,
                'best_of' => 1,
                'temperature' => 0,
                'temperature_inc' => 0,
            ],
            'crla_task_2b_sentence' => [
                'prompt' => (string) ($item->prompt_snapshot['prompt'] ?? ''),
            ],
            default => [],
        };
    }

    private function sttOptionsForPassage(?AssessmentAttemptItem $passage): array
    {
        return [];
    }

    private function taskTwoBSummary(AssessmentAttempt $attempt): ?array
    {
        $responses = $attempt->responses()
            ->where('task_type', AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE)
            ->orderBy('item_number')
            ->get();

        if ($responses->isEmpty()) {
            return null;
        }

        $averageAccuracy = (int) round($responses->avg(fn ($response) => (float) ($response->metadata_json['accuracy_percentage'] ?? 0)) ?? 0);
        $labelCounts = $responses
            ->map(fn ($response) => $response->metadata_json['feedback_label'] ?? SentenceReadingScoringService::RIGHT_WORDS_BUT_UNCLEAR)
            ->countBy();

        $feedbackLabel = match (true) {
            $averageAccuracy >= 90 => SentenceReadingScoringService::MOSTLY_CORRECT,
            ($labelCounts[SentenceReadingScoringService::A_LITTLE_RUSHED] ?? 0) >= max(1, (int) ceil($responses->count() / 3)) => SentenceReadingScoringService::A_LITTLE_RUSHED,
            ($labelCounts[SentenceReadingScoringService::MISSING_ONE_WORD] ?? 0) >= max(1, (int) ceil($responses->count() / 3)) => SentenceReadingScoringService::MISSING_ONE_WORD,
            default => SentenceReadingScoringService::RIGHT_WORDS_BUT_UNCLEAR,
        };

        return [
            'average_accuracy_percentage' => $averageAccuracy,
            'feedback_label' => $feedbackLabel,
            'items' => $responses->map(fn ($response) => [
                'item_number' => $response->item_number,
                'prompt' => $response->prompt,
                'accuracy_percentage' => (int) ($response->metadata_json['accuracy_percentage'] ?? 0),
                'feedback_label' => $response->metadata_json['feedback_label'] ?? SentenceReadingScoringService::RIGHT_WORDS_BUT_UNCLEAR,
                'text_accuracy_percentage' => (int) ($response->metadata_json['text_accuracy_percentage'] ?? 0),
                'phoneme_similarity_percentage' => isset($response->metadata_json['phoneme_similarity_percentage']) ? (int) $response->metadata_json['phoneme_similarity_percentage'] : null,
                'target_word' => $response->metadata_json['target_word'] ?? null,
                'actual_target_word' => $response->metadata_json['actual_target_word'] ?? null,
                'target_word_phoneme_similarity_percentage' => isset($response->metadata_json['target_word_phoneme_similarity_percentage']) ? (int) $response->metadata_json['target_word_phoneme_similarity_percentage'] : null,
                'target_word_error_type' => $response->metadata_json['target_word_error_type'] ?? null,
                'matched_words' => (int) ($response->metadata_json['matched_words'] ?? 0),
                'total_words' => (int) ($response->metadata_json['total_words'] ?? 0),
                'missing_words' => (int) ($response->metadata_json['missing_words'] ?? 0),
                'correct_words' => (int) ($response->metadata_json['correct_words'] ?? $response->metadata_json['matched_words'] ?? 0),
                'substitutions' => (int) ($response->metadata_json['substitutions'] ?? 0),
                'deletions' => (int) ($response->metadata_json['deletions'] ?? 0),
                'insertions' => (int) ($response->metadata_json['insertions'] ?? 0),
                'wer' => isset($response->metadata_json['wer']) ? (float) $response->metadata_json['wer'] : null,
                'wpm' => isset($response->metadata_json['wpm']) ? (float) $response->metadata_json['wpm'] : null,
                'wcpm' => isset($response->metadata_json['wcpm']) ? (float) $response->metadata_json['wcpm'] : null,
                'fluency_label' => $response->metadata_json['fluency_label'] ?? null,
                'long_pause_warning' => $response->metadata_json['long_pause_warning'] ?? null,
                'retry_required' => (bool) ($response->metadata_json['retry_required'] ?? false),
                'learner_retry_message' => $response->metadata_json['learner_retry_message'] ?? null,
                'pronunciation_verified' => (bool) ($response->metadata_json['pronunciation_verified'] ?? false),
            ])->values()->all(),
        ];
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

    private function letterModelPath(): ?string
    {
        $tinyModelPath = config('stt.whisper_cpp.letter_model_path');

        if (is_string($tinyModelPath) && $tinyModelPath !== '' && is_file($tinyModelPath)) {
            return $tinyModelPath;
        }

        return config('stt.whisper_cpp.model_path');
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

    private function passageDurationValidationRules(): array
    {
        return ['nullable', 'numeric', 'min:'.AudioStorageService::MIN_TRANSCRIBABLE_SECONDS, 'max:60'];
    }

    private function rhymeDecisionResponseRules(): array
    {
        return [
            'responses' => ['required', 'array', 'size:10'],
            'responses.*.assessment_attempt_item_id' => ['required', 'integer', 'exists:assessment_attempt_items,id'],
            'responses.*.answer' => ['required', 'string', 'in:yes,no'],
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
            'duration_seconds.max' => 'Passage reading time is limited to 60 seconds.',
            'responses.*.duration_seconds.min' => 'That recording was too short. Please try again and speak clearly.',
        ];
    }
}
