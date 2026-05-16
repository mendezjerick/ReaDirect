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

    public function test_gop_metadata_is_preserved_when_present_and_missing_fields_are_safe(): void
    {
        $missing = $this->normalizer->normalize([
            'raw_transcript' => 'Layo',
            'corrected_transcript' => 'Layo',
        ]);

        $this->assertNull($missing['gop_score']);
        $this->assertSame([], $missing['gop_expected_phonemes']);

        $result = $this->normalizer->normalize([
            'raw_transcript' => 'Layo',
            'corrected_transcript' => 'Leo',
            'displayed_transcript' => 'Leo',
            'gop_enabled' => true,
            'gop_available' => true,
            'gop_score' => 0.82,
            'gop_decision' => 'accepted_by_pronunciation_evidence',
            'gop_threshold' => 0.75,
            'gop_expected_phonemes' => ['L', 'IY', 'OW'],
            'gop_observed_phonemes' => ['L', 'EY', 'OW'],
            'gop_correction_applied' => true,
        ]);

        $this->assertSame(0.82, $result['gop_score']);
        $this->assertSame('accepted_by_pronunciation_evidence', $result['gop_decision']);
        $this->assertSame(['L', 'IY', 'OW'], $result['gop_expected_phonemes']);
        $this->assertTrue($result['gop_correction_applied']);
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
