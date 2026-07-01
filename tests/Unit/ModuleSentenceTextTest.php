<?php

namespace Tests\Unit;

use App\Support\ModuleSentenceText;
use PHPUnit\Framework\TestCase;

class ModuleSentenceTextTest extends TestCase
{
    public function test_display_capitalizes_sentence_starts_and_adds_terminal_dot(): void
    {
        $this->assertSame('I see a cat.', ModuleSentenceText::display('i see a cat'));
        $this->assertSame('I see a cat. It runs.', ModuleSentenceText::display('i see a cat. it runs'));
    }

    public function test_simple_sentence_reading_removes_display_punctuation_from_scoring_target(): void
    {
        $this->assertSame(
            'I see a cat',
            ModuleSentenceText::scoringTarget('I see a cat.', 'module_3', 'simple_sentence_reading'),
        );
    }

    public function test_punctuation_lesson_scoring_keeps_punctuation(): void
    {
        $this->assertSame(
            'I see a cat, and it looks at me.',
            ModuleSentenceText::scoringTarget('I see a cat, and it looks at me.', 'module_3', 'comma_pause_reading'),
        );

        $this->assertSame(
            'I see a cat. It walks away.',
            ModuleSentenceText::scoringTarget('I see a cat. It walks away.', 'module_3', 'full_stop_pause_reading'),
        );
    }
}
