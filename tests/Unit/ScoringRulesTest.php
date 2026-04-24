<?php

namespace Tests\Unit;

use App\Services\CrlaScoringService;
use App\Services\ModuleMasteryService;
use App\Services\ModulePlacementService;
use App\Services\ReadingComprehensionScoringService;
use PHPUnit\Framework\TestCase;

class ScoringRulesTest extends TestCase
{
    public function test_crla_classification_boundaries(): void
    {
        $service = new CrlaScoringService();

        $this->assertSame('Full Refresher', $service->classifyTotalScore(10));
        $this->assertSame('Moderate Refresher', $service->classifyTotalScore(16));
        $this->assertSame('Light Refresher', $service->classifyTotalScore(26));
        $this->assertSame('Grade Ready', $service->classifyTotalScore(27));
    }

    public function test_task_one_routing(): void
    {
        $service = new CrlaScoringService();

        $this->assertTrue($service->routeTaskOne(6)['requires_task_2a']);
        $this->assertFalse($service->routeTaskOne(7)['requires_task_2a']);
        $this->assertSame(10, $service->routeTaskOne(7)['assigned_task_2a_score']);
    }

    public function test_reading_accuracy_formula(): void
    {
        $service = new ReadingComprehensionScoringService();

        $this->assertSame(94.0, $service->calculateAccuracyPercentage(3));
        $this->assertSame(0.0, $service->calculateAccuracyPercentage(60));
    }

    public function test_comprehension_percentage_formula(): void
    {
        $service = new ReadingComprehensionScoringService();

        $this->assertSame(80.0, $service->calculateComprehensionPercentage(4));
    }

    public function test_final_reading_score_formula(): void
    {
        $service = new ReadingComprehensionScoringService();

        $this->assertSame(85.6, $service->calculateFinalReadingScore(80, 94));
    }

    public function test_reading_classification_uses_final_score_only(): void
    {
        $service = new ReadingComprehensionScoringService();

        $accuracy = $service->calculateAccuracyPercentage(0);
        $comprehension = $service->calculateComprehensionPercentage(0);
        $final = $service->calculateFinalReadingScore($comprehension, $accuracy);

        $this->assertSame(40.0, $final);
        $this->assertSame('High Emerging Reader', $service->classifyReadingLevelFromFinalScore($final));
    }

    public function test_reading_classification_boundaries(): void
    {
        $service = new ReadingComprehensionScoringService();

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
        $service = new ModulePlacementService();

        $this->assertSame('module_1', $service->place('Light Refresher', 'Reading at Grade Level')['module_key']);
        $this->assertSame('module_2', $service->place('Grade Ready', 'Low Emerging Reader')['module_key']);
        $this->assertSame('module_3', $service->place('Grade Ready', 'Developing Reader')['module_key']);
        $this->assertNull($service->place('Grade Ready', 'Reading at Grade Level')['module_key']);
    }

    public function test_module_mastery_decisions(): void
    {
        $service = new ModuleMasteryService();

        $this->assertSame('move_to_module_2', $service->decide('module_1', 90)['decision']);
        $this->assertSame('repeat_module_1', $service->decide('module_1', 89)['decision']);
        $this->assertSame('repeat_module_1', $service->decide('module_1', 60)['decision']);
        $this->assertSame('extra_phoneme_drills', $service->decide('module_1', 59)['decision']);
        $this->assertSame('move_to_module_3', $service->decide('module_2', 90)['decision']);
        $this->assertSame('repeat_module_2', $service->decide('module_2', 89)['decision']);
        $this->assertSame('repeat_module_2', $service->decide('module_2', 60)['decision']);
        $this->assertSame('return_to_module_1', $service->decide('module_2', 59)['decision']);
        $this->assertSame('proceed_to_reassessment', $service->decide('module_3', 90)['decision']);
        $this->assertSame('repeat_module_3', $service->decide('module_3', 89)['decision']);
        $this->assertSame('repeat_module_3', $service->decide('module_3', 70)['decision']);
        $this->assertSame('return_to_module_2', $service->decide('module_3', 69)['decision']);
    }
}
