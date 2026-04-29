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
use App\Services\ModuleMasteryService;
use App\Services\ModuleScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModuleMasteryController extends Controller
{
    public function show(Request $request, Module $module, ModuleActivitySelectionService $selection): Response
    {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $request->session()->put('module_attempt_id', $attempt->id);
        $items = $selection->selectMasteryItemsForAttempt($attempt, $selection->masteryCountFor($module));

        $attempt->update(['status' => 'mastery_started']);

        return Inertia::render('Learner/Modules/ModuleMasteryCheck', [
            'module' => $module->only('key', 'title', 'description'),
            'items' => $this->itemsForForm($items),
        ]);
    }

    public function store(
        Request $request,
        Module $module,
        ModuleActivitySelectionService $selection,
        ModuleScoringService $scoring,
        ModuleFeedbackService $feedback,
        ModuleMasteryService $mastery,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AgentCommentaryService $commentary
    ): RedirectResponse {
        $learner = $this->learner($request);
        $this->authorizeModule($learner, $module);
        $attempt = $this->attempt($request, $learner, $module, $selection);
        $items = $selection->getLockedItemsForAttempt($attempt)->where('is_mastery_item', true)->values();

        $validated = $request->validate($this->responseRules($items->count()), $this->friendlyValidationMessages());
        $this->validateSubmittedItemSet($items, $validated['responses']);

        foreach ($items as $item) {
            $submittedIndex = collect($validated['responses'])->search(fn ($response) => (int) ($response['module_attempt_item_id'] ?? 0) === (int) $item->id);
            $submitted = $submittedIndex === false ? [] : $validated['responses'][$submittedIndex];
            $audioFile = null;

            if (isset($submitted['audio'])) {
                $audioFile = $audioStorage->store(
                    $submitted['audio'],
                    $attempt->learner,
                    'module_mastery_check',
                    moduleAttempt: $attempt,
                    durationSeconds: isset($submitted['duration_seconds']) ? (float) $submitted['duration_seconds'] : null,
                    metadata: [
                        'module_attempt_item_id' => $item->id,
                        'activity_type' => 'mastery_check',
                    ],
                );
            }

            $expectedAnswer = $this->expectedAnswer($item);
            $acceptedAnswers = $item->prompt_snapshot['accepted_answers'] ?? [];
            $resolved = $analysis->resolve(
                $submitted['answer'] ?? null,
                $audioFile,
                $this->analysisContext($item, $module, $expectedAnswer, $acceptedAnswers)
            );
            $answer = $resolved['transcript'];

            if (trim($answer) === '') {
                throw ValidationException::withMessages([
                    'responses.'.($submittedIndex === false ? 0 : $submittedIndex).'.answer' => 'Let us answer this first.',
                ]);
            }

            $score = $scoring->scoreAnswer($item, $answer);
            $template = $score['is_correct']
                ? $feedback->feedbackForCorrect($module->key, 'mastery_check')
                : $feedback->feedbackForIncorrect($module->key, 'mastery_check', $score['error_type'] ?? 'incorrect_general');
            $templateFeedback = $score['is_correct'] ? $template['success_text'] : $template['feedback_text'];

            $response = ModuleActivityResponse::updateOrCreate(
                ['module_attempt_id' => $attempt->id, 'module_attempt_item_id' => $item->id],
                array_merge([
                    'module_activity_id' => $item->module_activity_id,
                    'audio_file_id' => $audioFile?->id,
                    'transcript_source' => $resolved['source'],
                    'stt_confidence' => $resolved['confidence'],
                    'response_text' => $answer,
                    'learner_answer' => $answer,
                    'learner_transcript' => $answer,
                    'expected_answer' => $score['expected_answer'],
                    'is_correct' => $score['is_correct'],
                    'score' => $score['score'],
                    'feedback_text' => $templateFeedback,
                    'retry_count' => 0,
                    'is_mastery_item' => true,
                    'error_type' => $score['error_type'],
                    'metadata' => ['source_csv_id' => $item->source_csv_id],
                    'metadata_json' => ['prompt_snapshot' => $item->prompt_snapshot],
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
                'activity_type' => 'mastery_check',
                'expected_answer' => $score['expected_answer'],
                'learner_answer' => $answer,
                'is_correct' => $score['is_correct'],
                'score' => $score['score'],
                'max_score' => $score['possible_score'],
                'error_type' => $score['error_type'] ?? null,
                'recommended_action' => $score['is_correct'] ? 'continue' : 'try_again',
                'template_feedback' => $templateFeedback,
                'retry_instruction' => $template['retry_instruction'] ?? '',
                'is_module' => true,
                'can_give_hint' => false,
            ]);

            $response->update([
                'feedback_text' => $agentCommentary['message'],
                'agent_commentary_text' => $agentCommentary['message'],
                'agent_commentary_source' => $agentCommentary['source'],
                'agent_type' => $agentCommentary['agent_type'],
            ]);

            $item->update(['answered_at' => now()]);
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

    public function result(Request $request, Module $module, ModuleMasteryService $mastery): Response
    {
        $learner = $this->learner($request);
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
            'nextModule' => $nextModule?->only('key', 'title', 'description'),
        ]);
    }

    private function applyLearnerDecision(Learner $learner, array $decision): void
    {
        $nextModule = $decision['next_module_key'] ? Module::where('key', $decision['next_module_key'])->first() : null;
        $stage = match ($decision['decision_key']) {
            'extra_phoneme_drills' => 'extra_phoneme_drills',
            'proceed_to_reassessment' => 'final_reassessment_pending',
            default => 'module_assigned',
        };

        $learner->update([
            'current_module_id' => $nextModule?->id,
            'current_stage' => $stage,
        ]);
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

    private function expectedAnswer(ModuleAttemptItem $item): ?string
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];

        return $payload['expected_answer'] ?? $payload['target_word'] ?? $item->prompt_snapshot['prompt'] ?? null;
    }

    private function analysisContext(ModuleAttemptItem $item, Module $module, ?string $expectedAnswer, array $acceptedAnswers): array
    {
        return [
            'expected_text' => $expectedAnswer,
            'accepted_answers' => $acceptedAnswers,
            'prompt_id' => $item->source_csv_id,
            'module_key' => $module->key,
            'activity_type' => 'mastery_check',
            'task_type' => 'module_mastery',
            'content_metadata' => ['prompt_snapshot' => $item->prompt_snapshot],
            'debug' => (bool) config('readirect_ai.debug.show_admin_debug'),
        ];
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

    private function responseRules(int $requiredCount): array
    {
        return [
            'responses' => ['required', 'array', 'size:'.$requiredCount],
            'responses.*.module_attempt_item_id' => ['required', 'integer', 'exists:module_attempt_items,id'],
            'responses.*.answer' => ['nullable', 'string', 'max:255'],
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
            'responses.*.duration_seconds.min' => 'Record at least 1 second so the transcript can be generated.',
        ];
    }
}
