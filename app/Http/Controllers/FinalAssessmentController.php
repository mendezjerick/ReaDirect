<?php

namespace App\Http\Controllers;

use App\Models\AgentProfile;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\AssessmentTaskResponse;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Services\AnswerMatchingService;
use App\Services\Assessment\FinalAssessmentComparisonService;
use App\Services\AssessmentItemSelectionService;
use App\Services\AudioStorageService;
use App\Services\CrlaScoringService;
use App\Services\ReadingComprehensionScoringService;
use App\Services\STT\AudioTranscriptionService;
use App\Services\STT\TranscriptResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinalAssessmentController extends Controller
{
    public function start(): Response
    {
        return Inertia::render('Learner/FinalAssessment/Start');
    }

    public function storeStart(Request $request): RedirectResponse
    {
        $learner = $this->learner($request);
        $baseline = $this->baselineDiagnostic($learner);
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

        return redirect()->route('final-assessment.task', 'task-1');
    }

    public function showTask(Request $request, string $taskKey, AssessmentItemSelectionService $itemSelection, CrlaScoringService $crla): Response|RedirectResponse
    {
        $attempt = $this->attempt($request);

        return match ($taskKey) {
            'task-1' => Inertia::render('Learner/FinalAssessment/Task1LetterPronunciation', [
                'items' => $this->itemsForForm($this->taskItems($attempt, $itemSelection, AssessmentItemSelectionService::TASK_1_LETTER)),
            ]),
            'task-2a' => $this->showTaskTwoA($attempt, $itemSelection, $crla),
            'task-2b' => Inertia::render('Learner/FinalAssessment/Task2BWordInSentence', [
                'items' => $this->itemsForForm($this->taskItems($attempt, $itemSelection, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE)),
            ]),
            'passage' => Inertia::render('Learner/FinalAssessment/PassageReading', [
                'passage' => $this->itemForForm($this->readingPassage($attempt, $itemSelection)),
            ]),
            'comprehension' => Inertia::render('Learner/FinalAssessment/ComprehensionQuestions', [
                'questions' => $this->questionsForPassage($this->readingPassage($attempt, $itemSelection)),
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
        TranscriptResolver $transcripts,
        CrlaScoringService $crla,
        ReadingComprehensionScoringService $reading,
        AudioTranscriptionService $audioTranscription,
        FinalAssessmentComparisonService $comparison
    ): RedirectResponse {
        $attempt = $this->attempt($request);

        return match ($taskKey) {
            'task-1' => $this->submitTaskOne($request, $attempt, $itemSelection, $answerMatching, $audioStorage, $transcripts, $crla),
            'task-2a' => $this->submitTaskTwoA($request, $attempt, $itemSelection, $answerMatching, $audioStorage, $transcripts),
            'task-2b' => $this->submitTaskTwoB($request, $attempt, $itemSelection, $answerMatching, $audioStorage, $transcripts, $crla),
            'passage' => $this->submitPassage($request, $attempt, $itemSelection, $reading, $audioStorage, $audioTranscription),
            'comprehension' => $this->submitComprehension($request, $attempt, $itemSelection, $answerMatching, $reading, $comparison),
            default => abort(404),
        };
    }

    public function summary(Request $request, FinalAssessmentComparisonService $comparison): Response
    {
        $attempt = $this->attempt($request)->load('baselineAssessment');
        $comparisonSummary = $attempt->comparison_summary ?: $comparison->compareAttempts($attempt->baselineAssessment, $attempt);

        return Inertia::render('Learner/FinalAssessment/Summary', [
            'attempt' => $attempt->only([
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'crla_classification',
                'reading_accuracy',
                'comprehension_percentage',
                'final_reading_score',
                'reading_classification',
                'completed_at',
            ]),
            'comparison' => $comparisonSummary,
        ]);
    }

    private function submitTaskOne(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        TranscriptResolver $transcripts,
        CrlaScoringService $crla
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_1_LETTER);
        $score = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, $transcripts, 'FINAL_CRLA_TASK_1_SCORING_V1');
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
        TranscriptResolver $transcripts
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2A_RHYME);
        $score = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, $transcripts, 'FINAL_CRLA_TASK_2A_SCORING_V1');

        $attempt->update(['task_2a_score' => $score, 'status' => 'task_2a_completed']);

        return redirect()->route('final-assessment.task', 'task-2b');
    }

    private function submitTaskTwoB(
        Request $request,
        AssessmentAttempt $attempt,
        AssessmentItemSelectionService $itemSelection,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        TranscriptResolver $transcripts,
        CrlaScoringService $crla
    ): RedirectResponse {
        $validated = $request->validate($this->textResponseRules(10), $this->friendlyValidationMessages());
        $items = $itemSelection->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE);
        $taskTwoBScore = $this->scoreTextResponses($attempt, $items, $validated['responses'], $answerMatching, $audioStorage, $transcripts, 'FINAL_CRLA_TASK_2B_SCORING_V1');
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
        AudioTranscriptionService $audioTranscription
    ): RedirectResponse {
        $validated = $request->validate([
            'incorrect_words' => ['required', 'integer', 'min:0', 'max:50'],
            'audio' => AudioStorageService::validationRules(),
            'audio_file_id' => ['nullable', 'integer', 'exists:audio_files,id'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:600'],
        ], $this->friendlyValidationMessages());
        $passage = $itemSelection->selectReadingPassageForAttempt($attempt);
        $incorrectWords = (int) $validated['incorrect_words'];
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

        if ($audioFile) {
            $transcriptText = trim((string) $audioFile->transcript);

            if ($transcriptText === '') {
                $sttResult = $audioTranscription->transcribeAudioFile($audioFile, $this->sttOptionsForPassage($passage));
                $transcriptText = trim((string) $sttResult->transcript);
            }

            if ($transcriptText !== '') {
                $incorrectWords = $reading->calculateIncorrectWordCount(
                    (string) ($passage?->prompt_snapshot['prompt'] ?? ''),
                    $transcriptText,
                );
            }
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
        $attempt->learner->update(['current_stage' => 'final_reassessment_completed']);

        return redirect()->route('final-assessment.summary');
    }

    private function scoreTextResponses(
        AssessmentAttempt $attempt,
        Collection $items,
        array $responses,
        AnswerMatchingService $answerMatching,
        AudioStorageService $audioStorage,
        TranscriptResolver $transcripts,
        string $rule
    ): int {
        $score = 0;

        foreach ($items as $item) {
            $submittedIndex = collect($responses)->search(fn ($response) => (int) ($response['assessment_attempt_item_id'] ?? 0) === (int) $item->id);
            $submitted = $submittedIndex === false ? [] : $responses[$submittedIndex];
            $audioFile = isset($submitted['audio']) && $submitted['audio']
                ? $audioStorage->store(
                    file: $submitted['audio'],
                    learner: $attempt->learner,
                    recordingContext: 'final_assessment_task',
                    assessmentAttempt: $attempt,
                    durationSeconds: isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
                    metadata: ['assessment_attempt_item_id' => $item->id, 'task_type' => $item->task_type]
                )
                : null;
            $resolved = $transcripts->resolve($submitted['answer'] ?? null, $audioFile);
            $answer = $resolved['transcript'];

            if (trim($answer) === '') {
                throw ValidationException::withMessages([
                    'responses.'.($submittedIndex === false ? 0 : $submittedIndex).'.answer' => 'Let us answer this first.',
                ]);
            }

            $isCorrect = $answerMatching->isAcceptedAnswer($answer, $item->prompt_snapshot['accepted_answers'] ?? []);
            $score += $isCorrect ? 1 : 0;

            $response = AssessmentTaskResponse::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'assessment_attempt_item_id' => $item->id],
                [
                    'learner_id' => $attempt->learner_id,
                    'learning_content_id' => $item->learning_content_id,
                    'audio_file_id' => $audioFile?->id,
                    'task_key' => 'final_'.$item->task_type,
                    'task_type' => $item->task_type,
                    'item_number' => $item->sequence,
                    'prompt' => $item->prompt_snapshot['prompt'] ?? null,
                    'expected_answer' => $this->expectedAnswer($item),
                    'learner_transcript' => $answer,
                    'transcript_source' => $resolved['source'],
                    'stt_confidence' => $resolved['confidence'],
                    'response_text' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? 1 : 0,
                    'error_type' => $isCorrect ? null : 'incorrect_general',
                    'rule_applied' => $rule,
                    'metadata' => ['source_csv_id' => $item->source_csv_id, 'is_final_reassessment' => true],
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

    private function showTaskTwoA(AssessmentAttempt $attempt, AssessmentItemSelectionService $itemSelection, CrlaScoringService $crla): Response|RedirectResponse
    {
        if (! $crla->shouldRequireTask2A((int) $attempt->task_1_score)) {
            return redirect()->route('final-assessment.task', 'task-2b');
        }

        return Inertia::render('Learner/FinalAssessment/Task2ARhymingWords', [
            'items' => $this->itemsForForm($this->taskItems($attempt, $itemSelection, AssessmentItemSelectionService::TASK_2A_RHYME)),
        ]);
    }

    private function learner(Request $request): Learner
    {
        return Learner::find($request->session()->get('learner_id')) ?? Learner::firstOrFail();
    }

    private function attempt(Request $request): AssessmentAttempt
    {
        return AssessmentAttempt::with(['selectedItems', 'baselineAssessment'])->findOrFail($request->session()->get('final_assessment_attempt_id'));
    }

    private function baselineDiagnostic(Learner $learner): ?AssessmentAttempt
    {
        return AssessmentAttempt::where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->where('status', 'module_placement_completed')
            ->latest()
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

    private function sttOptionsForPassage(?AssessmentAttemptItem $passage): array
    {
        return [];
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
            'responses.*.answer' => ['nullable', 'string', 'max:255'],
            'responses.*.transcript_source' => ['nullable', 'string', 'in:manual,stt_auto,stt_placeholder,teacher_review,future_asr'],
            'responses.*.audio' => AudioStorageService::validationRules(),
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
