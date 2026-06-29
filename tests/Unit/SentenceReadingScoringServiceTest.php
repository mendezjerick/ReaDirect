<?php

namespace Tests\Unit;

use App\Services\SentenceReadingScoringService;
use PHPUnit\Framework\TestCase;

class SentenceReadingScoringServiceTest extends TestCase
{
    public function test_alignment_exact_match(): void
    {
        $alignment = (new SentenceReadingScoringService())->align('the red hen', 'the red hen');

        $this->assertSame(0, $alignment['substitutions']);
        $this->assertSame(0, $alignment['deletions']);
        $this->assertSame(0, $alignment['insertions']);
        $this->assertSame(3, $alignment['correct']);
        $this->assertSame('match', $alignment['alignment'][0]['operation']);
    }

    public function test_alignment_substitution(): void
    {
        $alignment = (new SentenceReadingScoringService())->align('the red hen', 'the read hen');

        $this->assertSame(1, $alignment['substitutions']);
        $this->assertSame(0, $alignment['deletions']);
        $this->assertSame(0, $alignment['insertions']);
    }

    public function test_alignment_deletion(): void
    {
        $alignment = (new SentenceReadingScoringService())->align('the red hen', 'the hen');

        $this->assertSame(0, $alignment['substitutions']);
        $this->assertSame(1, $alignment['deletions']);
        $this->assertSame(0, $alignment['insertions']);
    }

    public function test_alignment_insertion(): void
    {
        $alignment = (new SentenceReadingScoringService())->align('the red hen', 'the big red hen');

        $this->assertSame(0, $alignment['substitutions']);
        $this->assertSame(0, $alignment['deletions']);
        $this->assertSame(1, $alignment['insertions']);
    }

    public function test_wpm_uses_actual_words_and_duration(): void
    {
        $actual = implode(' ', ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten']);
        $result = (new SentenceReadingScoringService())->evaluate($actual, $actual, 30);

        $this->assertSame(20.0, $result['wpm']);
    }

    public function test_wcpm_uses_alignment_correct_words_and_duration(): void
    {
        $expected = 'one two three four five six seven eight nine ten';
        $actual = 'one two three four five six seven eight wrong wrong';
        $result = (new SentenceReadingScoringService())->evaluate($expected, $actual, 30);

        $this->assertSame(8, $result['correct_words']);
        $this->assertSame(16.0, $result['wcpm']);
    }

    public function test_wcpm_counts_ai_accepted_split_merge_alignment(): void
    {
        $result = (new SentenceReadingScoringService())->evaluate('time after lunch', 'timeafter lunch', 30, [
            'word_alignment' => [
                [
                    'expected_word' => 'time',
                    'recognized_word' => 'timeafter',
                    'status' => 'accepted_by_split_merge',
                    'counts_as_correct' => true,
                    'chunk_match_id' => 'chunk_001',
                ],
                [
                    'expected_word' => 'after',
                    'recognized_word' => 'timeafter',
                    'status' => 'accepted_by_split_merge',
                    'counts_as_correct' => true,
                    'chunk_match_id' => 'chunk_001',
                ],
                [
                    'expected_word' => 'lunch',
                    'recognized_word' => 'lunch',
                    'status' => 'exact_correct',
                    'counts_as_correct' => true,
                ],
            ],
        ]);

        $this->assertSame(3, $result['correct_words']);
        $this->assertSame(6.0, $result['wcpm']);
        $this->assertSame(100, $result['text_accuracy_percentage']);
    }

    public function test_fluency_score_with_no_pauses(): void
    {
        $result = (new SentenceReadingScoringService())->evaluate('the red hen', 'the red hen', 2, [
            'pause_metrics' => [
                'pause_count' => 0,
                'long_pause_count' => 0,
                'very_long_pause_count' => 0,
                'longest_pause_seconds' => 0,
                'pause_ratio' => 0,
                'total_pause_seconds' => 0,
            ],
        ]);

        $this->assertSame(100, $result['pause_score']);
        $this->assertSame('fluent', $result['fluency_label']);
        $this->assertTrue($result['pause_metrics_available']);
    }

    public function test_wcpm_stays_numeric_fluency_when_long_pauses_are_present(): void
    {
        $service = new SentenceReadingScoringService();
        $withoutPauses = $service->evaluate('the red hen', 'the red hen', 3);
        $withLongPauses = $service->evaluate('the red hen', 'the red hen', 3, [
            'pause_metrics' => [
                'pause_count' => 3,
                'long_pause_count' => 3,
                'very_long_pause_count' => 1,
                'longest_pause_seconds' => 4.2,
                'pause_ratio' => 0.5,
                'total_pause_seconds' => 5.4,
            ],
        ]);

        $this->assertLessThan(100, $withLongPauses['pause_score']);
        $this->assertSame($withoutPauses['wcpm'], $withLongPauses['wcpm']);
        $this->assertSame($withoutPauses['wcpm'], $withLongPauses['fluency_score']);
        $this->assertNotNull($withLongPauses['long_pause_warning']);
    }

    public function test_module_timing_targets_classify_pace_without_changing_wcpm(): void
    {
        $service = new SentenceReadingScoringService();
        $targets = [
            'target_read_time_seconds' => 2.0,
            'min_fluent_time_seconds' => 1.0,
            'max_fluent_time_seconds' => 3.0,
            'target_wcpm' => 120.0,
        ];

        $fluent = $service->evaluate('I see a cat.', 'I see a cat.', 2.0, [], $targets);
        $tooFast = $service->evaluate('I see a cat.', 'I see a cat.', 0.75, [], $targets);
        $tooSlow = $service->evaluate('I see a cat.', 'I see a cat.', 3.5, [], $targets);

        $this->assertSame(4, $fluent['total_words_read']);
        $this->assertSame(0, $fluent['errors']);
        $this->assertSame(4, $fluent['correct_words']);
        $this->assertSame(120.0, $fluent['wcpm']);
        $this->assertSame(120.0, $fluent['fluency_score']);
        $this->assertSame('fluent', $fluent['pace_label']);
        $this->assertSame('too_fast', $tooFast['pace_label']);
        $this->assertSame('too_slow', $tooSlow['pace_label']);
    }

    public function test_missing_duration_does_not_crash(): void
    {
        $result = (new SentenceReadingScoringService())->evaluate('the red hen', 'the red hen');

        $this->assertNull($result['wpm']);
        $this->assertNull($result['wcpm']);
        $this->assertNull($result['words_per_second']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_retry_required_from_ai_is_preserved_and_surfaced(): void
    {
        $result = (new SentenceReadingScoringService())->evaluate('the red hen', 'the red hen', 3, [
            'retry_required' => true,
            'uncertain' => true,
            'uncertainty_reasons' => ['too_quiet'],
            'audio_quality' => ['level' => 'too_quiet'],
            'learner_retry_message' => 'Please try again. Your recording was too quiet.',
        ]);

        $this->assertTrue($result['retry_required']);
        $this->assertTrue($result['uncertain']);
        $this->assertSame(['too_quiet'], $result['uncertainty_reasons']);
        $this->assertSame('Please try again. Your recording was too quiet.', $result['learner_retry_message']);
        $this->assertSame('retry_needed', $result['fluency_label']);
    }
}
