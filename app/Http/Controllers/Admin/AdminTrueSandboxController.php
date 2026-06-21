<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Services\Admin\AdminAccessService;
use App\Services\AI\ReadirectAIService;
use App\Services\ASR\AsrResponseNormalizer;
use App\Services\ASR\SupervisedReinforcementService;
use App\Services\AudioStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminTrueSandboxController extends Controller
{
    private const SECTIONS = [
        'diagnostic_letters' => [
            'label' => 'Diagnostic: Isolated letters',
            'description' => 'Task 1 letter pronunciation items from the assessment content bank.',
            'source' => 'content',
            'content_types' => ['letter', 'crla_task_1_letter', 'task_1_letter'],
            'prompt_type' => 'letter',
            'task_type' => 'crla_task_1_letter',
            'assessment_type' => 'diagnostic',
        ],
        'diagnostic_rhymes' => [
            'label' => 'Diagnostic: Rhyming words',
            'description' => 'Task 2A rhyme prompts from the assessment content bank.',
            'source' => 'content',
            'content_types' => ['rhyme_decision', 'crla_task_2a_rhyme_decision', 'rhyme_prompt', 'crla_task_2a_rhyme', 'task_2a_rhyme'],
            'prompt_type' => 'rhyme',
            'task_type' => 'crla_task_2a_rhyme',
            'assessment_type' => 'diagnostic',
        ],
        'diagnostic_task2b' => [
            'label' => 'Diagnostic: Task 2B highlighted words',
            'description' => 'Word-in-sentence prompts scored against the highlighted target word.',
            'source' => 'content',
            'content_types' => ['word_sentence', 'crla_task_2b_word_sentence', 'task_2b_word_sentence'],
            'prompt_type' => 'word',
            'task_type' => 'crla_task_2b_sentence',
            'assessment_type' => 'diagnostic',
        ],
        'diagnostic_passages' => [
            'label' => 'Diagnostic: Passage reading',
            'description' => 'Reading passage items with sentence/passage fluency metadata.',
            'source' => 'content',
            'content_types' => ['reading_passage'],
            'prompt_type' => 'reading_passage',
            'task_type' => 'reading_passage',
            'assessment_type' => 'diagnostic',
        ],
        'word_pronunciation' => [
            'label' => 'Word pronunciation bank',
            'description' => 'Standalone word-like content across assessment and practice banks.',
            'source' => 'content',
            'content_types' => ['word', 'word_pronunciation', 'module_word', 'decodable_word', 'sight_word'],
            'prompt_type' => 'word',
            'task_type' => 'word_pronunciation',
            'assessment_type' => 'true_sandbox',
        ],
        'sentence_reading' => [
            'label' => 'Sentence reading bank',
            'description' => 'Sentence prompts without learner flow prerequisites.',
            'source' => 'content',
            'content_types' => ['sentence', 'sentence_reading'],
            'prompt_type' => 'sentence',
            'task_type' => 'sentence_reading',
            'assessment_type' => 'true_sandbox',
        ],
        'module_activities' => [
            'label' => 'Module activities',
            'description' => 'Practice activities from all modules, loaded directly from module content.',
            'source' => 'module',
            'prompt_type' => null,
            'task_type' => null,
            'assessment_type' => 'module_activity',
            'mastery_only' => false,
        ],
        'module_mastery' => [
            'label' => 'Module mastery checks',
            'description' => 'Mastery-check module items without creating a module attempt.',
            'source' => 'module',
            'prompt_type' => null,
            'task_type' => 'mastery_check',
            'assessment_type' => 'module_mastery',
            'mastery_only' => true,
        ],
        'final_letters' => [
            'label' => 'Final assessment: Isolated letters',
            'description' => 'Final/reassessment letter testing using the same item contract.',
            'source' => 'content',
            'content_types' => ['letter', 'crla_task_1_letter', 'task_1_letter'],
            'prompt_type' => 'letter',
            'task_type' => 'crla_task_1_letter',
            'assessment_type' => 'final_reassessment',
        ],
        'final_rhymes' => [
            'label' => 'Final assessment: Rhyming words',
            'description' => 'Final/reassessment Task 2A rhyme testing.',
            'source' => 'content',
            'content_types' => ['rhyme_decision', 'crla_task_2a_rhyme_decision', 'rhyme_prompt', 'crla_task_2a_rhyme', 'task_2a_rhyme'],
            'prompt_type' => 'rhyme',
            'task_type' => 'crla_task_2a_rhyme',
            'assessment_type' => 'final_reassessment',
        ],
        'final_task2b' => [
            'label' => 'Final assessment: Task 2B highlighted words',
            'description' => 'Final/reassessment word-in-sentence testing.',
            'source' => 'content',
            'content_types' => ['word_sentence', 'crla_task_2b_word_sentence', 'task_2b_word_sentence'],
            'prompt_type' => 'word',
            'task_type' => 'crla_task_2b_sentence',
            'assessment_type' => 'final_reassessment',
        ],
        'final_passages' => [
            'label' => 'Final assessment: Passage reading',
            'description' => 'Final/reassessment passage testing with passage-level ASR output.',
            'source' => 'content',
            'content_types' => ['reading_passage'],
            'prompt_type' => 'reading_passage',
            'task_type' => 'final_reading_passage',
            'assessment_type' => 'final_reassessment',
        ],
    ];

    public function index(Request $request, AdminAccessService $access): Response
    {
        $access->ensureTesting($request->user());

        return Inertia::render('Admin/Testing/TrueSandbox', [
            'sections' => $this->sections(),
            'modules' => Module::query()->orderBy('sequence')->get(['id', 'key', 'title']),
            'activityTypes' => ModuleActivity::query()
                ->select('activity_type')
                ->distinct()
                ->orderBy('activity_type')
                ->pluck('activity_type')
                ->filter()
                ->values(),
            'initialItems' => $this->loadItems('diagnostic_letters'),
            'routes' => [
                'items' => route('admin.testing.true-sandbox.items'),
                'analyze' => route('admin.testing.true-sandbox.analyze'),
                'reinforcement' => route('admin.testing.true-sandbox.reinforcement.store'),
            ],
        ]);
    }

    public function items(Request $request, AdminAccessService $access): JsonResponse
    {
        $access->ensureTesting($request->user());

        $validated = $request->validate([
            'section' => ['nullable', Rule::in(array_keys(self::SECTIONS))],
            'search' => ['nullable', 'string', 'max:120'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'activity_type' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'items' => $this->loadItems(
                $validated['section'] ?? 'diagnostic_letters',
                trim((string) ($validated['search'] ?? '')),
                $validated['module_id'] ?? null,
                trim((string) ($validated['activity_type'] ?? '')),
            ),
        ]);
    }

    public function analyze(
        Request $request,
        AdminAccessService $access,
        ReadirectAIService $ai,
        AsrResponseNormalizer $normalizer
    ): JsonResponse {
        $access->ensureTesting($request->user());
        $this->extendAudioRequestTime();

        $validated = $request->validate([
            'audio' => AudioStorageService::validationRules(true),
            'section' => ['required', Rule::in(array_keys(self::SECTIONS))],
            'item_id' => ['nullable', 'string', 'max:100'],
            'item_source' => ['nullable', 'string', 'max:50'],
            'expected_text' => ['required', 'string', 'max:6000'],
            'prompt_text' => ['nullable', 'string', 'max:6000'],
            'prompt_type' => ['required', 'string', 'max:100'],
            'task_type' => ['nullable', 'string', 'max:100'],
            'activity_type' => ['nullable', 'string', 'max:255'],
            'assessment_type' => ['nullable', 'string', 'max:100'],
            'module_key' => ['nullable', 'string', 'max:100'],
            'duration_seconds' => AudioStorageService::durationValidationRules(),
            'accepted_answers' => ['nullable', 'array'],
            'accepted_answers.*' => ['nullable', 'string', 'max:500'],
            'content_metadata' => ['nullable', 'array'],
            'audio_metadata' => ['nullable', 'array'],
        ], AudioStorageService::durationValidationMessages());

        $path = $validated['audio']->store('audio/true-sandbox', 'local');
        $absolutePath = Storage::disk('local')->path($path);

        try {
            $payload = [
                'audio_path' => $absolutePath,
                'expected_text' => trim($validated['expected_text']),
                'prompt_type' => $validated['prompt_type'],
                'accepted_answers' => array_values($validated['accepted_answers'] ?? []),
                'prompt_id' => $validated['item_id'] ?? null,
                'module_key' => $validated['module_key'] ?? null,
                'module_type' => $validated['module_key'] ?? null,
                'activity_type' => $validated['activity_type'] ?? null,
                'assessment_type' => $validated['assessment_type'] ?? 'true_sandbox',
                'task_type' => $validated['task_type'] ?? null,
                'item_id' => $validated['item_id'] ?? null,
                'content_metadata' => [
                    'true_sandbox' => true,
                    'item_source' => $validated['item_source'] ?? null,
                    'section' => $validated['section'],
                    'prompt_text' => $validated['prompt_text'] ?? null,
                    'duration_seconds' => $validated['duration_seconds'] ?? null,
                    'audio_metadata' => $validated['audio_metadata'] ?? [],
                    'content' => $validated['content_metadata'] ?? [],
                ],
                'debug' => true,
            ];

            $aiResponse = $ai->analyzeAudio($payload);
            $normalized = $normalizer->normalize($aiResponse);

            return response()->json([
                'ok' => (bool) ($aiResponse['ok'] ?? false),
                'message' => $this->messageFor($aiResponse, $normalized),
                'expected_text' => $payload['expected_text'],
                'prompt_text' => $validated['prompt_text'] ?? null,
                'prompt_type' => $validated['prompt_type'],
                'task_type' => $validated['task_type'] ?? null,
                'activity_type' => $validated['activity_type'] ?? null,
                'assessment_type' => $payload['assessment_type'],
                'duration_seconds' => isset($validated['duration_seconds']) ? (float) $validated['duration_seconds'] : null,
                'can_submit' => $this->canUseResult($normalized),
                'scoring' => $this->scoringSummary($aiResponse, $normalized),
                ...$normalized,
                'transcript' => $normalized['scoring_transcript'],
                'corrected_transcript' => $aiResponse['corrected_transcript'] ?? $normalized['scoring_transcript'],
                'displayed_transcript' => $normalized['display_transcript'],
                'raw_transcript' => $normalized['debug_transcript'],
                'ai_response' => $aiResponse,
                'request_context' => $payload,
            ]);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function storeReinforcement(
        Request $request,
        AdminAccessService $access,
        SupervisedReinforcementService $reinforcement
    ): JsonResponse {
        $access->ensureTesting($request->user());

        $validated = $request->validate([
            'result' => ['required', 'array'],
            'result.expected_text' => ['required', 'string', 'max:6000'],
            'result.raw_transcript' => ['required', 'string', 'max:6000'],
            'result.normalized_transcript' => ['nullable', 'string', 'max:6000'],
            'result.corrected_transcript' => ['nullable', 'string', 'max:6000'],
            'result.displayed_transcript' => ['nullable', 'string', 'max:6000'],
            'result.prompt_type' => ['required', 'string', 'max:100'],
            'result.task_type' => ['nullable', 'string', 'max:100'],
            'result.activity_type' => ['nullable', 'string', 'max:255'],
            'result.assessment_type' => ['nullable', 'string', 'max:100'],
            'result.accepted' => ['nullable', 'boolean'],
            'result.retry_required' => ['nullable', 'boolean'],
            'result.uncertain' => ['nullable', 'boolean'],
            'result.scoring' => ['nullable', 'array'],
            'result.request_context' => ['nullable', 'array'],
            'result.ai_response' => ['nullable', 'array'],
            'word' => ['nullable', 'array'],
            'word.index' => ['nullable', 'integer'],
            'word.expected_word' => ['nullable', 'string', 'max:500'],
            'word.recognized_word' => ['nullable', 'string', 'max:500'],
            'word.expected_text' => ['nullable', 'string', 'max:500'],
            'word.raw_transcript' => ['nullable', 'string', 'max:500'],
            'word.status' => ['nullable', 'string', 'max:100'],
            'word.operation' => ['nullable', 'string', 'max:100'],
            'word.counts_as_correct' => ['nullable', 'boolean'],
            'word.alignment_confidence' => ['nullable', 'numeric'],
            'word.dynamic_correction_confidence' => ['nullable', 'numeric'],
            'word.spelling_similarity' => ['nullable', 'numeric'],
            'word.phoneme_similarity' => ['nullable', 'numeric'],
            'word.gop_score' => ['nullable', 'numeric'],
        ]);

        $case = $reinforcement->approveFalseRejection($validated['result'], $request->user(), $validated['word'] ?? null);

        return response()->json([
            'ok' => true,
            'message' => ($case->reinforcement_response['saved'] ?? false)
                ? 'Supervised reinforcement case saved.'
                : (string) ($case->reinforcement_response['reason'] ?? 'Supervised case recorded, but correction memory was not updated.'),
            'case' => [
                'id' => $case->id,
                'status' => $case->status,
                'duplicate' => (bool) ($case->reinforcement_response['duplicate'] ?? false),
                'reinforcement_saved' => (bool) ($case->reinforcement_response['saved'] ?? false),
                'reinforcement_reason' => (string) ($case->reinforcement_response['reason'] ?? ''),
                'target_file' => (string) ($case->reinforcement_response['target_file'] ?? ''),
                'confirmed_at' => $case->confirmed_at?->toISOString(),
            ],
        ]);
    }

    private function loadItems(string $section, string $search = '', ?int $moduleId = null, string $activityType = ''): array
    {
        $config = self::SECTIONS[$section] ?? self::SECTIONS['diagnostic_letters'];

        if (($config['source'] ?? 'content') === 'module') {
            return ModuleActivity::query()
                ->with(['module', 'learningContent'])
                ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
                ->when($activityType !== '', fn ($query) => $query->where('activity_type', $activityType))
                ->when((bool) ($config['mastery_only'] ?? false), fn ($query) => $query
                    ->where(fn ($inner) => $inner
                        ->where('activity_type', 'mastery_check')
                        ->orWhere('configuration->is_mastery_item', true)))
                ->when(! (bool) ($config['mastery_only'] ?? false), fn ($query) => $query
                    ->where(fn ($inner) => $inner
                        ->where('activity_type', '!=', 'mastery_check')
                        ->orWhereNull('activity_type')))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('activity_type', 'like', "%{$search}%")
                    ->orWhereHas('module', fn ($module) => $module->where('title', 'like', "%{$search}%")->orWhere('key', 'like', "%{$search}%"))
                    ->orWhereHas('learningContent', fn ($content) => $content->where('title', 'like', "%{$search}%")->orWhere('prompt', 'like', "%{$search}%"))))
                ->orderBy('module_id')
                ->orderBy('sequence')
                ->limit(80)
                ->get()
                ->map(fn (ModuleActivity $activity): array => $this->serializeModuleActivity($activity, $section, $config))
                ->values()
                ->all();
        }

        return LearningContent::query()
            ->whereIn('content_type', $config['content_types'] ?? [])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('prompt', 'like', "%{$search}%")
                ->orWhere('content_type', 'like', "%{$search}%")))
            ->orderBy('content_type')
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn (LearningContent $content): array => $this->serializeContent($content, $section, $config))
            ->values()
            ->all();
    }

    private function serializeContent(LearningContent $content, string $section, array $config): array
    {
        $payload = $content->payload ?? [];
        $expectedText = $this->expectedText($content->prompt, $payload, $content->accepted_answers ?? [], (string) ($config['prompt_type'] ?? ''), $section);

        return [
            'id' => 'content:'.$content->id,
            'source' => 'learning_content',
            'source_id' => $content->id,
            'title' => $content->title ?: $content->prompt ?: 'Content '.$content->id,
            'content_type' => $content->content_type,
            'prompt' => $content->prompt,
            'expected_text' => $expectedText,
            'prompt_type' => $config['prompt_type'] ?? $this->inferPromptType($expectedText, $content->content_type, null),
            'task_type' => $config['task_type'] ?? $content->content_type,
            'activity_type' => $config['task_type'] ?? $content->content_type,
            'assessment_type' => $config['assessment_type'] ?? 'true_sandbox',
            'accepted_answers' => array_values($content->accepted_answers ?? []),
            'payload' => $payload,
            'metadata' => [
                'public_id' => $content->public_id,
                'difficulty' => $content->difficulty,
                'is_active' => $content->is_active,
                'enrichment_metadata' => $content->enrichment_metadata ?? [],
            ],
        ];
    }

    private function serializeModuleActivity(ModuleActivity $activity, string $section, array $config): array
    {
        $content = $activity->learningContent;
        $contentPayload = $content?->payload ?? [];
        $activityConfig = $activity->configuration ?? [];
        $payload = array_replace_recursive($contentPayload, $activityConfig);
        $prompt = (string) ($content?->prompt ?? $payload['prompt'] ?? $activity->title ?? '');
        $expectedText = $this->expectedText($prompt, $payload, $content?->accepted_answers ?? [], '', $section);
        $promptType = $this->inferPromptType($expectedText, $content?->content_type, $activity->activity_type);

        return [
            'id' => 'module_activity:'.$activity->id,
            'source' => 'module_activity',
            'source_id' => $activity->id,
            'title' => $activity->title ?: $content?->title ?: 'Module activity '.$activity->id,
            'content_type' => $content?->content_type,
            'activity_type' => $activity->activity_type,
            'prompt' => $prompt,
            'expected_text' => $expectedText,
            'prompt_type' => $promptType,
            'task_type' => $activity->activity_type,
            'assessment_type' => $config['assessment_type'] ?? 'module_activity',
            'module' => $activity->module ? [
                'id' => $activity->module->id,
                'key' => $activity->module->key,
                'title' => $activity->module->title,
            ] : null,
            'accepted_answers' => array_values($content?->accepted_answers ?? []),
            'payload' => $payload,
            'metadata' => [
                'public_id' => $activity->public_id,
                'sequence' => $activity->sequence,
                'learning_content_id' => $activity->learning_content_id,
                'content_public_id' => $content?->public_id,
                'configuration' => $activityConfig,
            ],
        ];
    }

    private function expectedText(?string $prompt, array $payload, array $acceptedAnswers, string $promptType, string $section): string
    {
        if (str_contains($section, 'task2b')) {
            return trim((string) ($payload['target_word'] ?? $payload['expected_answer'] ?? $acceptedAnswers[0] ?? $prompt ?? ''));
        }

        if (in_array($promptType, ['sentence', 'passage', 'paragraph', 'reading_passage'], true)) {
            return trim((string) ($payload['expected_text'] ?? $payload['expected_answer'] ?? $prompt ?? ''));
        }

        return trim((string) ($payload['expected_answer'] ?? $payload['target_word'] ?? $payload['expected_text'] ?? $acceptedAnswers[0] ?? $prompt ?? ''));
    }

    private function inferPromptType(string $expectedText, ?string $contentType, ?string $activityType): string
    {
        $haystack = strtolower(trim(($contentType ?? '').' '.($activityType ?? '')));

        if (str_contains($haystack, 'passage')) {
            return 'reading_passage';
        }

        if (str_contains($haystack, 'paragraph')) {
            return 'paragraph';
        }

        if (str_contains($haystack, 'sentence')) {
            return 'sentence';
        }

        if (str_contains($haystack, 'rhyme')) {
            return 'rhyme';
        }

        if (str_contains($haystack, 'letter') || mb_strlen($expectedText) === 1) {
            return 'letter';
        }

        return str_contains($expectedText, ' ') ? 'sentence' : 'word';
    }

    private function sections(): array
    {
        return collect(self::SECTIONS)
            ->map(fn (array $section, string $key): array => [
                'key' => $key,
                'label' => $section['label'],
                'description' => $section['description'],
                'source' => $section['source'],
            ])
            ->values()
            ->all();
    }

    private function canUseResult(array $normalized): bool
    {
        return $normalized['retry_required'] !== true
            && $normalized['uncertain'] !== true
            && trim($normalized['display_transcript']) !== '';
    }

    private function scoringSummary(array $aiResponse, array $normalized): array
    {
        $alignment = is_array($normalized['word_alignment'] ?? null) ? $normalized['word_alignment'] : [];
        $correctStatuses = [
            'exact_correct',
            'accepted_by_dynamic_expected_word_correction',
            'accepted_by_homophone',
            'accepted_by_phoneme_similarity',
            'accepted_by_gop',
            'accepted_by_split_merge',
            'accepted_by_asr_spelling_variant',
            'accepted_by_reinforcement_match',
        ];
        $correctWords = collect($alignment)->filter(function (array $item) use ($correctStatuses): bool {
            return ($item['counts_as_correct'] ?? false) === true
                || in_array((string) ($item['status'] ?? ''), $correctStatuses, true);
        })->count();
        $totalWords = collect($alignment)->filter(fn (array $item): bool => array_key_exists('expected_word', $item))->count();

        return [
            'accepted' => $normalized['accepted'],
            'is_correct' => $normalized['accepted'] === true,
            'retry_required' => $normalized['retry_required'],
            'uncertain' => $normalized['uncertain'],
            'correct_words' => $correctWords,
            'total_expected_words' => $totalWords,
            'word_accuracy' => $totalWords > 0 ? round(($correctWords / $totalWords) * 100, 2) : null,
            'raw_wer' => $normalized['raw_wer'],
            'corrected_wer' => $normalized['corrected_wer'],
            'raw_cer' => $normalized['raw_cer'],
            'corrected_cer' => $normalized['corrected_cer'],
            'phonetic_similarity_score' => $normalized['phonetic_similarity_score'],
            'correction_strategy_used' => $normalized['correction_strategy_used'],
            'correction_reason' => $aiResponse['dynamic_correction_reason'] ?? $aiResponse['variant_reason'] ?? data_get($aiResponse, 'debug_metadata.reason'),
        ];
    }

    private function messageFor(array $aiResponse, array $normalized): string
    {
        if (($aiResponse['ok'] ?? false) !== true) {
            return implode(' ', array_filter($aiResponse['warnings'] ?? [])) ?: 'The ASR service did not return a successful response.';
        }

        if ($normalized['retry_required']) {
            return (string) ($aiResponse['learner_retry_message'] ?? 'Retry required by ASR quality checks.');
        }

        return $normalized['display_transcript'] !== ''
            ? 'ASR analysis completed.'
            : 'ASR completed, but no usable transcript was returned.';
    }

    private function extendAudioRequestTime(): void
    {
        @set_time_limit(max(30, ((int) config('readirect_ai.timeout_seconds', 60)) + 15));
    }
}
