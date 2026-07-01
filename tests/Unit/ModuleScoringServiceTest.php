<?php

namespace Tests\Unit;

use App\Models\ModuleAttemptItem;
use App\Services\AnswerMatchingService;
use App\Services\ModuleScoringService;
use App\Services\SentenceReadingScoringService;
use PHPUnit\Framework\TestCase;

class ModuleScoringServiceTest extends TestCase
{
    public function test_module_three_simple_sentence_scoring_uses_dotless_target(): void
    {
        $result = $this->service()->scoreAnswer(
            $this->item('simple_sentence_reading', 'I see a cat.'),
            'I see a cat',
            2.0,
        );

        $this->assertTrue($result['is_correct']);
        $this->assertSame('I see a cat', $result['expected_answer']);
    }

    public function test_module_three_pace_lesson_scoring_keeps_punctuation_target(): void
    {
        $expected = 'I see a cat, and it looks at me.';

        $result = $this->service()->scoreAnswer(
            $this->item('comma_pause_reading', $expected),
            $expected,
            4.0,
        );

        $this->assertTrue($result['is_correct']);
        $this->assertSame($expected, $result['expected_answer']);
    }

    private function service(): ModuleScoringService
    {
        return new ModuleScoringService(
            new AnswerMatchingService(),
            new SentenceReadingScoringService(),
        );
    }

    private function item(string $activityType, string $expectedAnswer): ModuleAttemptItem
    {
        $item = new ModuleAttemptItem();
        $item->activity_type = $activityType;
        $item->prompt_snapshot = [
            'payload' => [
                'module_key' => 'module_3',
                'activity_type' => $activityType,
                'expected_answer' => $expectedAnswer,
                'points' => 1,
            ],
        ];

        return $item;
    }
}
