<?php

namespace Tests\Unit;

use App\Services\Scoring\AnswerSimilarityService;
use PHPUnit\Framework\TestCase;

class AnswerSimilarityServiceTest extends TestCase
{
    public function test_similarity_labels(): void
    {
        $service = new AnswerSimilarityService();

        $this->assertSame('exact', $service->classifySimilarity('cat', 'cat'));
        $this->assertSame('very_close', $service->classifySimilarity('cat', 'cap'));
        $this->assertContains($service->classifySimilarity('stamp', 'step'), ['close', 'somewhat_close']);
        $this->assertSame('far', $service->classifySimilarity('cat', 'dog'));
        $this->assertSame('blank', $service->classifySimilarity('cat', ' '));
    }

    public function test_error_type_detection_is_feedback_only(): void
    {
        $service = new AnswerSimilarityService();

        $this->assertSame('correct', $service->detectErrorType('cat', 'cat', true));
        $this->assertSame('final_sound_error', $service->detectErrorType('cat', 'cap'));
        $this->assertSame('initial_sound_error', $service->detectErrorType('cat', 'bat'));
        $this->assertSame('blank', $service->detectErrorType('cat', ''));
    }
}
