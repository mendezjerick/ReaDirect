<?php

namespace Tests\Unit;

use App\Services\AnswerMatchingService;
use PHPUnit\Framework\TestCase;

class AnswerMatchingServiceTest extends TestCase
{
    public function test_it_matches_pipe_separated_accepted_answers(): void
    {
        $service = new AnswerMatchingService();

        $this->assertTrue($service->isAcceptedAnswer(' Hat ', 'bat|hat|mat'));
    }

    public function test_it_normalizes_simple_punctuation_and_spacing(): void
    {
        $service = new AnswerMatchingService();

        $this->assertSame('red ball', $service->normalizeAnswer(' Red,   ball! '));
        $this->assertTrue($service->isAcceptedAnswer('Cat.', ['cat']));
    }

    public function test_it_rejects_incorrect_answers(): void
    {
        $service = new AnswerMatchingService();

        $this->assertFalse($service->isAcceptedAnswer('dog', 'bat|hat|mat'));
    }

    public function test_it_accepts_spoken_letter_alias_for_word_answer(): void
    {
        $service = new AnswerMatchingService();

        $this->assertTrue($service->isAcceptedAnswer('B', ['bee']));
        $this->assertTrue($service->isAcceptedAnswer('c', 'see|sea'));
        $this->assertFalse($service->isAcceptedAnswer('B', ['cat']));
    }
}
