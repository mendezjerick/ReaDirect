<?php

namespace Tests\Unit;

use App\Services\CrlaScoringService;
use App\Services\ModuleMasteryService;
use App\Services\ModulePlacementService;
use App\Services\ReadingComprehensionScoringService;
use App\Services\TaskTwoARhymeDecisionScoringService;
use PHPUnit\Framework\TestCase;

class ScoringRulesTest extends TestCase
{
    public function test_crla_classification_boundaries(): void
    {
        $service = new CrlaScoringService;

        $this->assertSame('Full Refresher', $service->classifyTotalScore(10));
        $this->assertSame('Moderate Refresher', $service->classifyTotalScore(16));
        $this->assertSame('Light Refresher', $service->classifyTotalScore(26));
        $this->assertSame('Grade Ready', $service->classifyTotalScore(27));
    }

    public function test_task_one_routing(): void
    {
        $service = new CrlaScoringService;

        $this->assertTrue($service->routeTaskOne(6)['requires_task_2a']);
        $this->assertFalse($service->routeTaskOne(7)['requires_task_2a']);
        $this->assertSame(10, $service->routeTaskOne(7)['assigned_task_2a_score']);
    }

    public function test_crla_total_formula_examples(): void
    {
        $service = new CrlaScoringService;

        $this->assertSame(13, $service->calculateTotalScore(5, 8, 0));
        $this->assertSame(25, $service->calculateTotalScore(8, 10, 7));
    }

    public function test_passage_eligibility_rules(): void
    {
        $service = new CrlaScoringService;

        $this->assertFalse($service->shouldAdministerPassage(6, 16));
        $this->assertFalse($service->shouldAdministerPassage(7, 16));
        $this->assertTrue($service->shouldAdministerPassage(7, 17));
        $this->assertTrue($service->shouldAdministerPassage(10, 30));
    }

    public function test_low_task_one_completion_sets_task_two_b_and_passage_scores_to_zero(): void
    {
        $service = new CrlaScoringService;
        $fields = $service->completeWithoutTask2BOrPassage(5, 8);

        $this->assertSame(0, $fields['task_2b_score']);
        $this->assertSame(13, $fields['crla_total_score']);
        $this->assertSame('Moderate Refresher', $fields['crla_classification']);
        $this->assertSame(0.0, $fields['reading_accuracy']);
        $this->assertSame(0.0, $fields['final_reading_score']);
    }

    public function test_task_two_a_button_answer_normalization(): void
    {
        $service = new TaskTwoARhymeDecisionScoringService;

        $this->assertSame('yes', $service->normalizeAnswer('YES'));
        $this->assertSame('no', $service->normalizeAnswer('No'));
        $this->assertSame('no', $service->normalizeAnswer('anything else'));
    }

    public function test_reading_accuracy_formula(): void
    {
        $service = new ReadingComprehensionScoringService;

        $this->assertSame(94.0, $service->calculateAccuracyPercentage(3));
        $this->assertSame(0.0, $service->calculateAccuracyPercentage(60));
    }

    public function test_passage_incorrect_count_uses_ai_split_merge_alignment(): void
    {
        $service = new ReadingComprehensionScoringService;
        $expected = 'time after lunch';
        $raw = 'timeafter lunch';
        $alignment = [
            [
                'expected_word' => 'time',
                'recognized_word' => 'timeafter',
                'status' => 'accepted_by_split_merge',
                'counts_as_correct' => true,
            ],
            [
                'expected_word' => 'after',
                'recognized_word' => 'timeafter',
                'status' => 'accepted_by_split_merge',
                'counts_as_correct' => true,
            ],
            [
                'expected_word' => 'lunch',
                'recognized_word' => 'lunch',
                'status' => 'exact_correct',
                'counts_as_correct' => true,
            ],
        ];

        $this->assertSame(0, $service->calculateIncorrectWordCount($expected, $raw, $alignment));
    }

    public function test_passage_incorrect_count_accepts_legacy_correct_alignment_status(): void
    {
        $service = new ReadingComprehensionScoringService;

        $this->assertSame(0, $service->calculateIncorrectWordCount('a proud carabao', 'a proud carabao', [
            ['expected_word' => 'a', 'recognized_word' => 'a', 'status' => 'correct'],
            ['expected_word' => 'proud', 'recognized_word' => 'proud', 'status' => 'correct'],
            ['expected_word' => 'carabao', 'recognized_word' => 'carabao', 'status' => 'correct'],
        ]));
    }

    public function test_passage_incorrect_count_falls_back_when_alignment_is_missing(): void
    {
        $service = new ReadingComprehensionScoringService;

        $this->assertSame(2, $service->calculateIncorrectWordCount('time after lunch', 'timeafter lunch'));
    }

    public function test_comprehension_percentage_formula(): void
    {
        $service = new ReadingComprehensionScoringService;

        $this->assertSame(100.0, $service->calculateComprehensionPercentage(5));
        $this->assertSame(80.0, $service->calculateComprehensionPercentage(4));
        $this->assertSame(60.0, $service->calculateComprehensionPercentage(3));
    }

    public function test_multiple_choice_comprehension_scores_selected_choice_only(): void
    {
        $service = new ReadingComprehensionScoringService;
        $choices = ['A' => 'Rosa', 'B' => 'Lena', 'C' => 'Ben', 'D' => 'Sam'];

        $this->assertTrue($service->isCorrectMultipleChoiceAnswer('A', 'A', $choices));
        $this->assertFalse($service->isCorrectMultipleChoiceAnswer('Rosa', 'A', $choices));
        $this->assertFalse($service->isCorrectMultipleChoiceAnswer('Lena', 'A', $choices));
    }

    public function test_final_reading_score_formula(): void
    {
        $service = new ReadingComprehensionScoringService;

        $this->assertSame(85.6, $service->calculateFinalReadingScore(80, 94));
    }

    public function test_reading_classification_uses_final_score_only(): void
    {
        $service = new ReadingComprehensionScoringService;

        $accuracy = $service->calculateAccuracyPercentage(0);
        $comprehension = $service->calculateComprehensionPercentage(0);
        $final = $service->calculateFinalReadingScore($comprehension, $accuracy);

        $this->assertSame(40.0, $final);
        $this->assertSame('High Emerging Reader', $service->classifyReadingLevelFromFinalScore($final));
    }

    public function test_reading_classification_boundaries(): void
    {
        $service = new ReadingComprehensionScoringService;

        $this->assertSame('Low Emerging Reader', $service->classifyReadingLevelFromFinalScore(25));
        $this->assertSame('High Emerging Reader', $service->classifyReadingLevelFromFinalScore(26));
        $this->assertSame('High Emerging Reader', $service->classifyReadingLevelFromFinalScore(50));
        $this->assertSame('Developing Reader', $service->classifyReadingLevelFromFinalScore(51));
        $this->assertSame('Developing Reader', $service->classifyReadingLevelFromFinalScore(75));
        $this->assertSame('Transitioning Reader', $service->classifyReadingLevelFromFinalScore(76));
        $this->assertSame('Transitioning Reader', $service->classifyReadingLevelFromFinalScore(90));
        $this->assertSame('Reading at Grade Level', $service->classifyReadingLevelFromFinalScore(91));
        $this->assertSame('Reading at Grade Level', $service->classifyReadingLevelFromFinalScore(100));
    }

    public function test_module_placement(): void
    {
        $service = new ModulePlacementService;

        $this->assertSame('module_1', $service->place('Light Refresher', 'Reading at Grade Level')['module_key']);
        $this->assertSame('module_2', $service->place('Grade Ready', 'Low Emerging Reader')['module_key']);
        $this->assertSame('module_3', $service->place('Grade Ready', 'Developing Reader')['module_key']);
        $this->assertNull($service->place('Grade Ready', 'Reading at Grade Level')['module_key']);
    }

    public function test_module_mastery_decisions(): void
    {
        $service = new ModuleMasteryService;

        $this->assertSame('move_to_module_2', $service->decide('module_1', 90)['decision']);
        $this->assertSame('repeat_module_1', $service->decide('module_1', 89)['decision']);
        $this->assertSame('repeat_module_1', $service->decide('module_1', 60)['decision']);
        $this->assertSame('repeat_module_1', $service->decide('module_1', 59)['decision']);
        $this->assertSame('repeat_module_1', $service->decide('module_1', 0)['decision']);
        $this->assertSame('move_to_module_3', $service->decide('module_2', 90)['decision']);
        $this->assertSame('repeat_module_2', $service->decide('module_2', 89)['decision']);
        $this->assertSame('repeat_module_2', $service->decide('module_2', 60)['decision']);
        $this->assertSame('return_to_module_1', $service->decide('module_2', 59)['decision']);
        $this->assertSame('proceed_to_reassessment', $service->decide('module_3', 90)['decision']);
        $this->assertSame('repeat_module_3', $service->decide('module_3', 89)['decision']);
        $this->assertSame('repeat_module_3', $service->decide('module_3', 70)['decision']);
        $this->assertSame('return_to_module_2', $service->decide('module_3', 69)['decision']);
        $this->assertSame('advanced_module_complete', $service->decide('advanced_module', 90)['decision']);
        $this->assertSame('repeat_advanced_module', $service->decide('advanced_module', 89)['decision']);
    }
}
