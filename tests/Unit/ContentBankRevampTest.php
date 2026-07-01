<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ContentBankRevampTest extends TestCase
{
    public function test_task_two_b_has_ten_active_easy_sentence_items(): void
    {
        $rows = $this->activeRows($this->csv('task2b_word_in_sentence.csv'));

        $this->assertCount(10, $rows);

        $expected = [
            ['I see a cat.', 'cat'],
            ['The dog can run.', 'dog'],
            ['The sun is hot.', 'sun'],
            ['My hat is red.', 'hat'],
            ['I have a pen.', 'pen'],
            ['A bug is on the leaf.', 'bug'],
            ['The cup is full.', 'cup'],
            ['The red ball rolls.', 'red'],
            ['Put the toy in the box.', 'box'],
            ['The fish can swim.', 'fish'],
        ];

        $actual = array_map(fn (array $row): array => [$row['prompt_text'], $row['expected_text']], $rows);
        $this->assertSame($expected, $actual);

        foreach ($rows as $row) {
            $this->assertSame(1, preg_match_all('/\b'.preg_quote($row['expected_text'], '/').'\b/i', $row['prompt_text']));

            $metadata = json_decode($row['metadata'], true);
            $this->assertIsArray($metadata);
            $this->assertSame($row['prompt_text'], $metadata['sentence_text'] ?? null);
            $this->assertSame($row['expected_text'], $metadata['expected_answer'] ?? null);
            $this->assertSame($row['expected_text'], $metadata['target_word'] ?? null);
            $this->assertSame($row['expected_text'], ($row['onset_unit'] ?? '').($row['rime_unit'] ?? ''));
        }
    }

    public function test_only_two_assessment_passages_are_active_and_each_has_fifty_words(): void
    {
        $rows = $this->activeRows($this->csv('reading_passages.csv'));

        $this->assertCount(2, $rows);
        $this->assertSame(['PASS-001', 'PASS-002'], array_column($rows, 'prompt_id'));
        $this->assertSame(['Rosa and the Kite', 'Lena and the Seed'], array_column($rows, 'title'));

        foreach ($rows as $row) {
            $this->assertSame(50, $this->wordCount($row['prompt_text']));
            $this->assertSame('50', (string) (int) $row['word_count']);
        }
    }

    public function test_comprehension_questions_are_five_four_choice_items_for_each_active_story(): void
    {
        $rows = $this->activeRows($this->csv('comprehension_questions.csv'));

        $this->assertCount(10, $rows);

        $byPassage = [];
        foreach ($rows as $row) {
            $byPassage[$row['passage_id']][] = $row;
            $choices = [$row['choice_a'], $row['choice_b'], $row['choice_c'], $row['choice_d']];

            $this->assertCount(4, array_filter($choices, fn (string $choice): bool => trim($choice) !== ''));
            $this->assertContains($row['correct_choice'], ['A', 'B', 'C', 'D']);
            $this->assertSame($choices[ord($row['correct_choice']) - ord('A')], $row['expected_text']);
            $this->assertSame('', trim($row['accepted_answers']));
            $this->assertCount(4, array_unique($choices));
        }

        $this->assertCount(5, $byPassage['PASS-001'] ?? []);
        $this->assertCount(5, $byPassage['PASS-002'] ?? []);
    }

    public function test_active_module_content_uses_adaptive_v2_inventory_and_timing_targets(): void
    {
        $module1Rows = $this->csv('module1_letter_sound_activities_adaptive_v2.csv');
        $module1 = $this->activeRows($module1Rows);
        $inactiveHardLetters = array_filter($module1Rows, fn (array $row): bool => in_array($row['expected_text'], ['P', 'D', 'B', 'Z'], true)
            && ! in_array(strtolower((string) ($row['is_active'] ?? '')), ['1', 'true', 'yes'], true));

        $this->assertCount(110, $module1);
        $this->assertCount(20, $inactiveHardLetters);

        $module2 = $this->activeRows($this->csv('module2_word_reading_activities_adaptive_v2.csv'));
        $this->assertCount(164, $module2);
        $this->assertCount(25, array_filter($module2, fn (array $row): bool => $row['activity_type'] === 'mastery_check'));
        foreach ($module2 as $row) {
            $this->assertCvcWord($row['expected_text']);
        }

        $module3 = $this->activeRows($this->csv('module3_sentence_fluency_activities_adaptive_v2.csv'));
        $requiredModule3 = array_values(array_filter($module3, fn (array $row): bool => $row['module_key'] === 'module_3'));
        $advancedModule = array_values(array_filter($module3, fn (array $row): bool => $row['module_key'] === 'advanced_module'));
        $module2Words = array_values(array_unique(array_map(
            fn (array $row): string => strtolower($row['expected_text']),
            $module2,
        )));
        sort($module2Words);

        $this->assertCount(110, $requiredModule3);
        $this->assertCount(55, array_filter($requiredModule3, fn (array $row): bool => $row['activity_type'] === 'simple_sentence_reading'));
        $this->assertCount(55, array_filter($requiredModule3, fn (array $row): bool => $row['activity_type'] === 'mastery_check'));
        $requiredTypes = array_values(array_unique(array_column($requiredModule3, 'activity_type')));
        sort($requiredTypes);
        $this->assertSame(['mastery_check', 'simple_sentence_reading'], $requiredTypes);

        $this->assertCount(156, $advancedModule);
        $this->assertCount(50, array_filter($advancedModule, fn (array $row): bool => $row['activity_type'] === 'comma_pause_reading'));
        $this->assertCount(35, array_filter($advancedModule, fn (array $row): bool => $row['activity_type'] === 'full_stop_pause_reading'));
        $this->assertCount(35, array_filter($advancedModule, fn (array $row): bool => $row['activity_type'] === 'mixed_punctuation_fluency'));
        $this->assertCount(36, array_filter($advancedModule, fn (array $row): bool => $row['activity_type'] === 'mastery_check'));

        $simpleRows = array_values(array_filter($requiredModule3, fn (array $row): bool => $row['activity_type'] === 'simple_sentence_reading'));
        $masteryRows = array_values(array_filter($requiredModule3, fn (array $row): bool => $row['activity_type'] === 'mastery_check'));
        $simplePrompts = array_column($simpleRows, 'prompt_text');
        $masteryPrompts = array_column($masteryRows, 'prompt_text');
        $this->assertCount(count($simplePrompts), array_unique($simplePrompts));
        $this->assertCount(count($masteryPrompts), array_unique($masteryPrompts));
        sort($simplePrompts);
        sort($masteryPrompts);
        $this->assertSame($simplePrompts, $masteryPrompts);

        $seenWords = [];
        foreach ($simpleRows as $row) {
            $this->assertPromptUsesOnlyCvcWordsAndHelpers($row['prompt_text']);
            foreach ($this->words($row['prompt_text']) as $word) {
                $this->assertContains($word, array_merge($module2Words, ['the', 'is', 'and']));
                if (in_array($word, $module2Words, true)) {
                    $seenWords[$word] = true;
                }
            }
        }

        $seenWords = array_keys($seenWords);
        sort($seenWords);
        $this->assertSame($module2Words, $seenWords);

        foreach ($module3 as $row) {
            $this->assertNotSame('', trim((string) ($row['target_read_time_seconds'] ?? '')));
            $this->assertNotSame('', trim((string) ($row['min_fluent_time_seconds'] ?? '')));
            $this->assertNotSame('', trim((string) ($row['max_fluent_time_seconds'] ?? '')));
            $this->assertNotSame('', trim((string) ($row['target_wcpm'] ?? '')));
            $this->assertSame(
                $row['module_key'] === 'module_3' ? 'False' : 'True',
                (string) ($row['pace_mastery_required'] ?? '')
            );
        }
    }

    public function test_reinforcement_csvs_are_retained_and_loadable(): void
    {
        $root = dirname(__DIR__, 3);
        $wordPath = $root.'/ReaDirect-AI-ASR/reinforcement-learning/word-reinforcement.csv';
        $letterPath = $root.'/ReaDirect-AI-ASR/reinforcement-learning/letter-reinforcement.csv';

        $this->assertFileExists($wordPath);
        $this->assertFileExists($letterPath);
        $this->assertNotEmpty($this->readCsv($wordPath));
        $this->assertNotEmpty($this->readCsv($letterPath));
    }

    private function csv(string $file): array
    {
        return $this->readCsv(dirname(__DIR__, 2).'/database/seed-data/readirect/'.$file);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '\\');
        $rows = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function activeRows(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => in_array(strtolower((string) ($row['is_active'] ?? '')), ['1', 'true', 'yes'], true)));
    }

    private function wordCount(string $text): int
    {
        preg_match_all("/[a-z']+/i", strtolower($text), $matches);

        return count($matches[0]);
    }

    private function assertPromptUsesOnlyCvcWordsAndHelpers(string $prompt): void
    {
        foreach ($this->words($prompt) as $word) {
            if (in_array($word, ['the', 'is', 'and'], true)) {
                continue;
            }

            $this->assertCvcWord($word);
        }
    }

    private function assertCvcWord(string $word): void
    {
        $this->assertMatchesRegularExpression(
            '/\A[bcdfghjklmnpqrstvwxyz][aeiou][bcdfghjklmnpqrstvwxyz]\z/',
            strtolower($word),
            "{$word} should be a three-letter CVC word.",
        );
    }

    private function words(string $text): array
    {
        preg_match_all('/[a-z]+/i', strtolower($text), $matches);

        return $matches[0];
    }
}
