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

    public function test_dynamic_correction_metadata_is_preserved_when_present_and_missing_fields_are_safe(): void
    {
        $missing = $this->normalizer->normalize([
            'raw_transcript' => 'shild',
            'corrected_transcript' => 'shild',
        ]);

        $this->assertNull($missing['dynamic_correction_applied']);
        $this->assertNull($missing['dynamic_suspicious_fragment']);
        $this->assertSame([], $missing['word_alignment']);

        $result = $this->normalizer->normalize([
            'raw_transcript' => 'shild',
            'corrected_transcript' => 'shield',
            'displayed_transcript' => 'shield',
            'dynamic_correction_enabled' => true,
            'dynamic_correction_applied' => true,
            'dynamic_correction_strategy' => 'dynamic_expected_word_correction',
            'dynamic_correction_sub_strategy' => 'spelling_context_expected_match',
            'dynamic_correction_confidence' => 0.91,
            'dynamic_correction_threshold' => 0.78,
            'dynamic_spelling_similarity' => 0.9,
            'dynamic_phoneme_similarity' => 0.95,
            'dynamic_gop_score' => 0.82,
            'dynamic_homophone_match' => false,
            'dynamic_context_score' => 1.0,
            'dynamic_correction_reason' => 'raw transcript is close to expected word',
            'dynamic_suspicious_fragment' => true,
            'dynamic_fragment_reasons' => ['raw_looks_like_consonant_skeleton'],
            'dynamic_phoneme_coverage' => 0.86,
            'asr_spelling_variant_enabled' => true,
            'asr_spelling_variant_applied' => true,
            'asr_spelling_variant_strategy' => 'dynamic_asr_spelling_variant',
            'asr_spelling_variant_sub_strategy' => 'vowel_tolerant_consonant_skeleton_match',
            'asr_spelling_variant_confidence' => 0.88,
            'asr_spelling_variant_threshold' => 0.78,
            'consonant_skeleton_similarity' => 1.0,
            'vowel_tolerant_similarity' => 0.91,
            'expected_phoneme_coverage' => 0.86,
            'variant_edit_similarity' => 0.75,
            'variant_reason' => 'raw transcript appears to be a noisy ASR spelling of the expected word',
            'word_alignment' => [
                [
                    'expected_word' => 'shield',
                    'recognized_word' => 'shild',
                    'status' => 'accepted_by_dynamic_expected_word_correction',
                    'counts_as_correct' => true,
                ],
            ],
        ]);

        $this->assertTrue($result['dynamic_correction_applied']);
        $this->assertSame('spelling_context_expected_match', $result['dynamic_correction_sub_strategy']);
        $this->assertSame(0.91, $result['dynamic_correction_confidence']);
        $this->assertTrue($result['dynamic_suspicious_fragment']);
        $this->assertSame(['raw_looks_like_consonant_skeleton'], $result['dynamic_fragment_reasons']);
        $this->assertSame(0.86, $result['dynamic_phoneme_coverage']);
        $this->assertTrue($result['asr_spelling_variant_applied']);
        $this->assertSame('vowel_tolerant_consonant_skeleton_match', $result['asr_spelling_variant_sub_strategy']);
        $this->assertSame(1.0, $result['consonant_skeleton_similarity']);
        $this->assertSame(0.91, $result['vowel_tolerant_similarity']);
        $this->assertSame('shield', $result['word_alignment'][0]['expected_word']);
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

    public function test_spoken_letter_alias_for_short_word_can_complete(): void
    {
        $resolved = [
            'transcript' => 'B',
            'ai_response' => [
                'raw_transcript' => 'B',
                'transcript' => 'B',
                'prompt_type' => 'word',
                'retry_required' => false,
                'uncertain' => false,
            ],
        ];

        $this->assertTrue($this->normalizer->canComplete($resolved, [
            'expected_text' => 'bee',
            'task_type' => 'crla_task_2b_sentence',
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

    public function test_letter_transcript_can_complete_when_uncertain_but_not_retry_required(): void
    {
        $context = ['expected_text' => 'Z', 'task_type' => 'crla_task_1_letter'];

        $this->assertTrue($this->normalizer->canComplete([
            'transcript' => 'they',
            'ai_response' => [
                'raw_transcript' => 'they',
                'corrected_transcript' => 'they',
                'retry_required' => false,
                'uncertain' => true,
                'audio_quality' => ['passed' => false],
                'prompt_type' => 'letter',
            ],
        ], $context));

        $this->assertFalse($this->normalizer->canComplete([
            'transcript' => '',
            'ai_response' => [
                'retry_required' => true,
                'uncertain' => true,
                'prompt_type' => 'letter',
            ],
        ], $context));
    }
}
