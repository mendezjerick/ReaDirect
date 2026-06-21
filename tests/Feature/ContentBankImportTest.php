<?php

namespace Tests\Feature;

use App\Models\LearningContent;
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
        $this->assertCount(8, $questions);
        $this->assertCount(4, $questions->where('payload.passage_id', 'PASS-001'));
        $this->assertCount(4, $questions->where('payload.passage_id', 'PASS-002'));

        $moduleActivities = $this->activeContent('module_activity');
        $this->assertCount(50, $moduleActivities->where('payload.module_key', 'module_1'));
        $this->assertCount(50, $moduleActivities->where('payload.module_key', 'module_2'));
        $this->assertCount(60, $moduleActivities->where('payload.module_key', 'module_3'));

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

    private function activeContent(string $contentType)
    {
        return LearningContent::query()
            ->where('content_type', $contentType)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (LearningContent $content): int => (int) ($content->payload['sequence'] ?? $content->payload['story_number'] ?? 0))
            ->values();
    }
}
