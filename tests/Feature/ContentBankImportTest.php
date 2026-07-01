<?php

namespace Tests\Feature;

use App\Models\LearningContent;
use App\Models\ModuleActivity;
use Database\Seeders\DiagnosticContentSeeder;
use Database\Seeders\ModuleContentSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentBankImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_revised_content_bank_csvs_import_through_seeders(): void
    {
        $this->seed(ModuleSeeder::class);
        $this->seed(ModuleContentSeeder::class);
        $this->seed(DiagnosticContentSeeder::class);

        $this->assertSame(10, $this->activeContent('rhyme_decision')->count());
        $this->assertSame(10, $this->activeContent('word_sentence')->count());

        $passages = $this->activeContent('reading_passage');
        $this->assertSame(['PASS-001', 'PASS-002'], $passages->map(fn (LearningContent $content): string => $content->payload['source_csv_id'])->values()->all());

        $questions = $this->activeContent('comprehension_question');
        $this->assertCount(10, $questions);
        $this->assertCount(5, $questions->where('payload.passage_id', 'PASS-001'));
        $this->assertCount(5, $questions->where('payload.passage_id', 'PASS-002'));
        $questions->each(function (LearningContent $content): void {
            $this->assertSame('multiple_choice', $content->payload['question_type']);
            $this->assertCount(4, array_filter($content->payload['choices']));
            $this->assertContains($content->payload['correct_choice'], ['A', 'B', 'C', 'D']);
            $this->assertSame([], $content->accepted_answers);
        });

        $moduleActivities = $this->activeContent('module_activity');
        $this->assertCount(110, $moduleActivities->where('payload.module_key', 'module_1'));
        $this->assertCount(164, $moduleActivities->where('payload.module_key', 'module_2'));
        $this->assertCount(110, $moduleActivities->where('payload.module_key', 'module_3'));
        $this->assertCount(156, $moduleActivities->where('payload.module_key', 'advanced_module'));

        $practiceRules = $this->activeContent('module_activity_selection_rule')
            ->filter(fn (LearningContent $content): bool => (int) ($content->payload['practice_item_count'] ?? 0) > 0)
            ->groupBy(fn (LearningContent $content): string => $content->payload['module_key'] ?? '');

        $this->assertSame(['letter_pair_identification', 'highlighted_first_letter', 'first_letter_identification', 'missing_first_letter'], $this->activityTypesFor($practiceRules, 'module_1'));
        $this->assertSame(['display_word_reading', 'split_word_reading', 'highlighted_rhyme_word', 'highlighted_sentence_word'], $this->activityTypesFor($practiceRules, 'module_2'));
        $this->assertSame(['simple_sentence_reading'], $this->activityTypesFor($practiceRules, 'module_3'));
        $this->assertSame(['comma_pause_reading', 'full_stop_pause_reading', 'mixed_punctuation_fluency'], $this->activityTypesFor($practiceRules, 'advanced_module'));

        $this->activeContent('module_activity_selection_rule')
            ->each(fn (LearningContent $content) => $this->assertNotEmpty($content->payload['source_csv_id'] ?? null));
    }

    public function test_imported_task_two_a_records_keep_no_repeated_active_words(): void
    {
        $this->seed(DiagnosticContentSeeder::class);

        $words = $this->activeContent('rhyme_decision')
            ->flatMap(fn (LearningContent $content): array => [
                $content->payload['word_1'],
                $content->payload['word_2'],
            ])
            ->values()
            ->all();

        $this->assertCount(20, $words);
        $this->assertCount(20, array_unique($words));
        $this->assertSame(6, $this->activeContent('rhyme_decision')->where('payload.is_rhyme', true)->count());
        $this->assertSame(4, $this->activeContent('rhyme_decision')->where('payload.is_rhyme', false)->count());
    }

    public function test_module_content_seeders_are_idempotent_on_reseed(): void
    {
        $this->seed(ModuleSeeder::class);
        $this->seed(ModuleContentSeeder::class);

        $firstSnapshot = $this->moduleContentSnapshot();

        $this->seed(ModuleSeeder::class);
        $this->seed(ModuleContentSeeder::class);

        $this->assertSame($firstSnapshot, $this->moduleContentSnapshot());
    }

    private function activeContent(string $contentType)
    {
        return LearningContent::query()
            ->where('content_type', $contentType)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (LearningContent $content): int => (int) ($content->payload['sequence'] ?? $content->payload['story_number'] ?? 0))
            ->values();
    }

    private function activityTypesFor($practiceRules, string $moduleKey): array
    {
        return ($practiceRules[$moduleKey] ?? collect())
            ->sortBy(fn (LearningContent $content): string => $content->payload['source_csv_id'] ?? '')
            ->pluck('payload.activity_type')
            ->values()
            ->all();
    }

    private function moduleContentSnapshot(): array
    {
        return [
            'learning_content_total' => LearningContent::query()->count(),
            'module_activity_total' => ModuleActivity::query()->count(),
            'module_activity_content_total' => ModuleActivity::query()->whereNotNull('learning_content_id')->count(),
            'module_activity_static_total' => ModuleActivity::query()->whereNull('learning_content_id')->count(),
            'module_3_active_content' => $this->activeContent('module_activity')->where('payload.module_key', 'module_3')->count(),
            'advanced_active_content' => $this->activeContent('module_activity')->where('payload.module_key', 'advanced_module')->count(),
            'selection_rule_content' => $this->activeContent('module_activity_selection_rule')->count(),
        ];
    }
}
