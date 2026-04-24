<?php

namespace Database\Seeders;

use App\Models\LearningContent;
use Illuminate\Database\Seeder;

class DiagnosticContentSeeder extends Seeder
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = database_path('seed-data/readirect');
    }

    public function run(): void
    {
        $this->seedTaskOneLetters();
        $this->seedTaskTwoARhymes();
        $this->seedTaskTwoBWords();
        $this->seedReadingPassages();
        $this->seedComprehensionQuestions();
    }

    private function seedTaskOneLetters(): void
    {
        foreach ($this->csv('task1_letter_pronunciation.csv') as $row) {
            LearningContent::updateOrCreate(
                ['content_type' => 'letter', 'title' => 'Letter '.$row['prompt_text']],
                [
                    'prompt' => $row['prompt_text'],
                    'payload' => [
                        'source_csv_id' => $row['id'],
                        'sequence' => (int) $row['sequence'],
                        'expected_answer' => $row['expected_answer'],
                        'expected_phoneme' => $row['expected_phoneme'],
                        'points' => (int) $row['points'],
                    ],
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedTaskTwoARhymes(): void
    {
        foreach ($this->csv('task2a_rhyming_words.csv') as $row) {
            LearningContent::updateOrCreate(
                ['content_type' => 'rhyme_prompt', 'title' => 'Rhyme '.$row['prompt_text']],
                [
                    'prompt' => $row['prompt_text'],
                    'payload' => [
                        'source_csv_id' => $row['id'],
                        'sequence' => (int) $row['sequence'],
                        'expected_rhyme_family' => $row['expected_rhyme_family'],
                        'points' => (int) $row['points'],
                    ],
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedTaskTwoBWords(): void
    {
        foreach ($this->csv('task2b_word_in_sentence.csv') as $row) {
            LearningContent::updateOrCreate(
                ['content_type' => 'word_sentence', 'title' => 'Word sentence '.$row['id']],
                [
                    'prompt' => $row['sentence_text'],
                    'payload' => [
                        'source_csv_id' => $row['id'],
                        'sequence' => (int) $row['sequence'],
                        'target_word' => $row['target_word'],
                        'expected_answer' => $row['expected_answer'],
                        'points' => (int) $row['points'],
                    ],
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedReadingPassages(): void
    {
        foreach ($this->csv('reading_passages.csv') as $row) {
            LearningContent::updateOrCreate(
                ['content_type' => 'reading_passage', 'title' => $row['title']],
                [
                    'prompt' => $row['passage_text'],
                    'payload' => [
                        'source_csv_id' => $row['id'],
                        'word_count' => (int) $row['word_count'],
                        'expected_reading_time_seconds' => (int) $row['expected_reading_time_seconds'],
                        'max_time_seconds' => (int) $row['max_time_seconds'],
                    ],
                    'accepted_answers' => null,
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedComprehensionQuestions(): void
    {
        foreach ($this->csv('comprehension_questions.csv') as $row) {
            LearningContent::updateOrCreate(
                ['content_type' => 'comprehension_question', 'title' => $row['id']],
                [
                    'prompt' => $row['question_text'],
                    'payload' => [
                        'source_csv_id' => $row['id'],
                        'passage_id' => $row['passage_id'],
                        'sequence' => (int) $row['sequence'],
                        'question_type' => $row['question_type'],
                        'correct_answer' => $row['correct_answer'],
                        'choices' => [
                            'A' => $row['choice_a'],
                            'B' => $row['choice_b'],
                            'C' => $row['choice_c'],
                            'D' => $row['choice_d'],
                        ],
                        'points' => (int) $row['points'],
                    ],
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function csv(string $file): array
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.$file;
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '\\');
        $rows = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function pipeList(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $value))));
    }

    private function active(string|int|null $value): bool
    {
        return (int) $value === 1;
    }
}
