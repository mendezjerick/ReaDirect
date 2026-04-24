<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\AssessmentTaskResponse;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\Recommendation;
use App\Services\AnswerMatchingService;
use App\Services\AssessmentItemSelectionService;
use App\Services\AudioStorageService;
use App\Services\CrlaScoringService;
use App\Services\ModulePlacementService;
use App\Services\ReadingComprehensionScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosticAssessmentController extends Controller
{
    public function start(): Response
    {
        return Inertia::render('Learner/DiagnosticStart');
    }

    public function storeStart(Request $request, AssessmentItemSelectionService $itemSelection): RedirectResponse
    {
        $learner = $this->learner($request);
        $agent = AgentProfile::where('key', AgentProfile::ASSESSMENT)->first();

        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'agent_profile_id' => $agent?->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'started_at' => now(),
        ]);

        $itemSelection->selectTask1LettersForAttempt($attempt);
        $request->session()->put('assessment_attempt_id', $attempt->id);

        return redirect()->route('learner.diagnostic.task-1');
    }

    public function taskOne(Request $request, AssessmentItemSelectionService $itemSelection): Response
    {
        $attempt = $this->attempt($request);
        $items = $itemSelection->selectTask1LettersForAttempt($attempt);

        return Inertia::render('Learner/Task1LetterPronunciation', [
            'items' => $this->itemsForForm($items),
        ]);
    }

    public function storeTaskOne(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        CrlaScoringService $crla
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());

        $attempt = $this->attempt($request);
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_1_LETTER);
        $score = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, 'CRLA_TASK_1_SCORING_V1');
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

    public function taskRouting(Request $request): Response
    {
        $attempt = $this->attempt($request);

        return Inertia::render('Learner/TaskRoutingResult', [
            'attempt' => $attempt->only('task_1_score', 'task_2a_score', 'decision_reason'),
            'route' => $request->session()->get('task_one_route', []),
        ]);
    }

    public function taskTwoA(Request $request, AssessmentItemSelectionService $itemSelection, CrlaScoringService $crla): Response|RedirectResponse
    {
        $attempt = $this->attempt($request);

        if (! $crla->shouldRequireTask2A((int) $attempt->task_1_score)) {
            return redirect()->route('learner.diagnostic.task-2b');
        }

        $items = $itemSelection->selectTask2ARhymingPromptsForAttempt($attempt);

        return Inertia::render('Learner/Task2ARhymingWords', [
            'items' => $this->itemsForForm($items),
        ]);
    }

    public function storeTaskTwoA(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());

        $attempt = $this->attempt($request);
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2A_RHYME);
        $score = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, 'CRLA_TASK_2A_SCORING_V1');

        $attempt->update(['task_2a_score' => $score, 'status' => 'task_2a_completed']);

        return redirect()->route('learner.diagnostic.task-2b');
    }

    public function taskTwoB(Request $request, AssessmentItemSelectionService $itemSelection): Response
    {
        $attempt = $this->attempt($request);
        $items = $itemSelection->selectTask2BWordSentenceItemsForAttempt($attempt);

        return Inertia::render('Learner/Task2BWordInSentence', [
            'items' => $this->itemsForForm($items),
        ]);
    }

    public function storeTaskTwoB(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        CrlaScoringService $crla
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());

        $attempt = $this->attempt($request);
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE);
        $taskTwoBScore = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, 'CRLA_TASK_2B_SCORING_V1');
        $taskTwoAScore = (int) $attempt->task_2a_score;
        $totalScore = $crla->calculateTotalScore((int) $attempt->task_1_score, $taskTwoAScore, $taskTwoBScore);
        $classification = $crla->classifyTotalScore($totalScore);

        $attempt->update([
            'task_2b_score' => $taskTwoBScore,
            'crla_total_score' => $totalScore,
            'crla_classification' => $classification,
            'status' => 'crla_completed',
        ]);

        return redirect()->route('learner.diagnostic.crla-summary');
    }

    public function crlaSummary(Request $request): Response
    {
        $attempt = $this->attempt($request);

        return Inertia::render('Learner/CrlaSummary', [
            'attempt' => $attempt->only('task_1_score', 'task_2a_score', 'task_2b_score', 'crla_total_score', 'crla_classification'),
        ]);
    }

    public function readingIntro(): Response
    {
        return Inertia::render('Learner/ReadingIntro');
    }

    public function passage(Request $request, AssessmentItemSelectionService $itemSelection): Response
    {
        $attempt = $this->attempt($request);
        $passage = $itemSelection->selectReadingPassageForAttempt($attempt);

        return Inertia::render('Learner/PassageReading', [
            'passage' => $this->itemForForm($passage),
        ]);
    }

    public function storePassage(Request $request, ReadingComprehensionScoringService $reading, AudioStorageService $audioStorage): RedirectResponse
    {
        $validated = $request->validate([
            'incorrect_words' => ['required', 'integer', 'min:0', 'max:50'],
            'audio' => ['nullable', 'file', 'max:10240', 'mimetypes:'.implode(',', AudioStorageService::ALLOWED_MIME_TYPES)],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:600'],
        ], $this->friendlyValidationMessages());

        $attempt = $this->attempt($request);
        if ($request->hasFile('audio')) {
            $audioStorage->store(
                file: $request->file('audio'),
                learner: $attempt->learner,
                recordingContext: 'passage_reading',
                assessmentAttempt: $attempt,
                durationSeconds: isset($validated['duration_seconds']) ? (float) $validated['duration_seconds'] : null
            );
        }
        $accuracy = $reading->calculateAccuracyPercentage((int) $validated['incorrect_words']);

        $attempt->update([
            'incorrect_words' => (int) $validated['incorrect_words'],
            'reading_accuracy' => $accuracy,
            'status' => 'passage_completed',
        ]);

        return redirect()->route('learner.diagnostic.comprehension');
    }

    public function comprehension(Request $request, AssessmentItemSelectionService $itemSelection): Response
    {
        $attempt = $this->attempt($request);
        $passage = $itemSelection->selectReadingPassageForAttempt($attempt);

        return Inertia::render('Learner/ComprehensionQuestions', [
            'questions' => $this->questionsForPassage($passage),
        ]);
    }

    public function storeComprehension(
        Request $request,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        ReadingComprehensionScoringService $reading
    ): RedirectResponse {
        $validated = $request->validate([
            'responses' => ['required', 'array', 'size:5'],
            'responses.*.question_id' => ['required', 'string'],
            'responses.*.answer' => ['required', 'string', 'max:255', 'regex:/\S/'],
        ], $this->friendlyValidationMessages());

        $attempt = $this->attempt($request);
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

    public function readingSummary(Request $request): Response
    {
        $attempt = $this->attempt($request);

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

    public function modulePlacement(Request $request, ModulePlacementService $placementService): Response
    {
        $attempt = $this->attempt($request);
        $decision = $placementService->place($attempt->crla_classification, $attempt->reading_classification);
        $module = $decision['module_key'] ? Module::where('key', $decision['module_key'])->first() : null;

        $attempt->update([
            'assigned_module_id' => $module?->id,
            'placement_decision' => $decision['decision'],
            'status' => 'module_placement_completed',
            'completed_at' => now(),
        ]);

        Recommendation::updateOrCreate(
            ['assessment_attempt_id' => $attempt->id, 'recommendation_type' => 'module_placement'],
            [
                'learner_id' => $attempt->learner_id,
                'module_id' => $module?->id,
                'recommended_module_id' => $module?->id,
                'source_type' => 'diagnostic_assessment',
                'source_id' => $attempt->id,
                'decision' => $decision['decision'],
                'rule_applied' => $decision['rule_applied'],
                'generated_by' => AgentProfile::EVALUATOR_RECOMMENDATION,
                'decision_reason' => $decision['decision_reason'],
                'input_scores' => [
                    'crla_classification' => $attempt->crla_classification,
                    'reading_classification' => $attempt->reading_classification,
                    'final_reading_score' => $attempt->final_reading_score,
                ],
            ]
        );

        $attempt->learner->update([
            'current_module_id' => $module?->id,
            'current_stage' => $module ? 'module_assigned' : 'grade_ready',
        ]);

        return Inertia::render('Learner/ModulePlacementResult', [
            'decision' => $decision,
            'module' => $module?->only('key', 'title', 'description'),
        ]);
    }

    private function learner(Request $request): Learner
    {
        return Learner::find($request->session()->get('learner_id')) ?? Learner::firstOrFail();
    }

    private function attempt(Request $request): AssessmentAttempt
    {
        return AssessmentAttempt::with('selectedItems')->findOrFail($request->session()->get('assessment_attempt_id'));
    }

    private function scoreTextResponses(
        AssessmentAttempt $attempt,
        Collection $items,
        array $responses,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        string $rule
    ): int {
        $score = 0;

        foreach ($items as $item) {
            $submitted = collect($responses)->firstWhere('assessment_attempt_item_id', $item->id);
            $answer = $submitted['answer'] ?? '';
            $transcriptSource = $submitted['transcript_source'] ?? 'manual';
            $acceptedAnswers = $item->prompt_snapshot['accepted_answers'] ?? [];
            $isCorrect = $answerMatching->isAcceptedAnswer($answer, $acceptedAnswers);
            $score += $isCorrect ? 1 : 0;
            $audioFile = isset($submitted['audio']) && $submitted['audio']
                ? $audioStorage->store(
                    file: $submitted['audio'],
                    learner: $attempt->learner,
                    recordingContext: 'assessment_task',
                    assessmentAttempt: $attempt,
                    durationSeconds: isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
                    metadata: ['assessment_attempt_item_id' => $item->id, 'task_type' => $item->task_type]
                )
                : null;

            $response = AssessmentTaskResponse::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'assessment_attempt_item_id' => $item->id],
                [
                    'learner_id' => $attempt->learner_id,
                    'learning_content_id' => $item->learning_content_id,
                    'audio_file_id' => $audioFile?->id,
                    'task_key' => $item->task_type,
                    'task_type' => $item->task_type,
                    'item_number' => $item->sequence,
                    'prompt' => $item->prompt_snapshot['prompt'] ?? null,
                    'expected_answer' => $this->expectedAnswer($item),
                    'learner_transcript' => $answer,
                    'transcript_source' => $transcriptSource,
                    'response_text' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? 1 : 0,
                    'error_type' => $isCorrect ? null : 'incorrect_general',
                    'rule_applied' => $rule,
                    'metadata' => ['source_csv_id' => $item->source_csv_id],
                    'metadata_json' => ['prompt_snapshot' => $item->prompt_snapshot],
                ]
            );

            if ($audioFile) {
                $audioStorage->attachToAssessmentResponse($audioFile, $response->id);
            }

            $item->update(['answered_at' => now()]);
        }

        return $score;
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

        return [
            'id' => $item->id,
            'sequence' => $item->sequence,
            'source_csv_id' => $item->source_csv_id,
            'prompt' => $item->prompt_snapshot['prompt'] ?? '',
            'title' => $item->prompt_snapshot['title'] ?? '',
            'payload' => $item->prompt_snapshot['payload'] ?? [],
            'accepted_answers' => $item->prompt_snapshot['accepted_answers'] ?? [],
        ];
    }

    private function expectedAnswer(AssessmentAttemptItem $item): ?string
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];

        return $payload['expected_answer'] ?? $payload['target_word'] ?? $item->prompt_snapshot['prompt'] ?? null;
    }

    private function questionsForPassage(?AssessmentAttemptItem $passage): array
    {
        $passageCsvId = $passage?->source_csv_id;

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
            ])
            ->all();
    }

    private function textResponseRules(int $requiredCount): array
    {
        return [
            'responses' => ['required', 'array', 'size:'.$requiredCount],
            'responses.*.assessment_attempt_item_id' => ['required', 'integer', 'exists:assessment_attempt_items,id'],
            'responses.*.answer' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'responses.*.transcript_source' => ['nullable', 'string', 'in:manual,stt_placeholder,teacher_review,future_asr'],
            'responses.*.audio' => ['nullable', 'file', 'max:10240', 'mimetypes:'.implode(',', AudioStorageService::ALLOWED_MIME_TYPES)],
            'responses.*.duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:600'],
        ];
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
        ];
    }
}
