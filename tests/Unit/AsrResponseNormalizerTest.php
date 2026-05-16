<?php

namespace Tests\Unit;

use App\Services\ASR\AsrResponseNormalizer;
use App\Services\STT\TranscriptSanitizer;
use PHPUnit\Framework\TestCase;

class AsrResponseNormalizerTest extends TestCase
{
    private AsrResponseNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new AsrResponseNormalizer(new TranscriptSanitizer());
    }

    public function test_transcript_selection_order_is_shared(): void
    {
        $result = $this->normalizer->normalize([
            'raw_transcript' => 'raw word',
            'transcript' => 'plain word',
            'corrected_transcript' => 'corrected word',
            'displayed_transcript' => 'display word',
        ]);

        $this->assertSame('corrected word', $result['scoring_transcript']);
        $this->assertSame('display word', $result['display_transcript']);
        $this->assertSame('raw word', $result['debug_transcript']);
    }

    public function test_wrong_but_usable_word_can_complete(): void
    {
        $resolved = [
            'transcript' => 'banana',
            'ai_response' => ['accepted' => false, 'prompt_type' => 'word'],
        ];

        $this->assertTrue($this->normalizer->canComplete($resolved, [
            'expected_text' => 'tree',
            'task_type' => 'word_reading',
        ]));
    }

    public function test_retry_uncertain_and_bad_audio_do_not_complete(): void
    {
        $context = ['expected_text' => 'tree', 'task_type' => 'word_reading'];

        $this->assertFalse($this->normalizer->canComplete([
            'transcript' => 'tree',
            'ai_response' => ['retry_required' => true],
        ], $context));

        $this->assertFalse($this->normalizer->canComplete([
            'transcript' => 'tree',
            'ai_response' => ['uncertain' => true],
        ], $context));

        $this->assertFalse($this->normalizer->canComplete([
            'transcript' => 'tree',
            'ai_response' => ['audio_quality' => ['passed' => false]],
        ], $context));
    }
}
