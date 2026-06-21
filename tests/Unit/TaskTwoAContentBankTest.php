<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TaskTwoAContentBankTest extends TestCase
{
    public function test_task_two_a_csv_contains_ten_decision_items_with_six_rhyming_and_four_non_rhyming_pairs(): void
    {
        $path = dirname(__DIR__, 2).'/database/seed-data/readirect/task2a_rhyming_words.csv';
        $rows = $this->readCsv($path);

        $this->assertCount(10, $rows);
        $this->assertSame(6, count(array_filter($rows, fn (array $row): bool => strtolower($row['is_rhyme']) === 'true')));
        $this->assertSame(4, count(array_filter($rows, fn (array $row): bool => strtolower($row['is_rhyme']) === 'false')));

        foreach ($rows as $row) {
            $this->assertSame('rhyme_decision', $row['item_type']);
            $this->assertSame('task_2a', $row['assessment_part']);
            $this->assertContains($row['correct_answer'], ['yes', 'no']);
            $this->assertNotSame('', trim($row['word_1']));
            $this->assertNotSame('', trim($row['word_2']));
            $this->assertNotSame('', trim($row['audio_script']));
        }
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
}
