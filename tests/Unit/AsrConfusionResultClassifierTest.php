<?php

namespace Tests\Unit;

use App\Services\ASR\AsrConfusionResultClassifier;
use PHPUnit\Framework\TestCase;

class AsrConfusionResultClassifierTest extends TestCase
{
    public function test_confusion_matrix_labels_use_expected_and_actual_correctness(): void
    {
        $classifier = new AsrConfusionResultClassifier();

        $this->assertSame('True Positive', $classifier->classify(true, true, true)['label']);
        $this->assertSame('True Negative', $classifier->classify(false, true, false)['label']);
        $this->assertSame('False Positive', $classifier->classify(false, true, true)['label']);
        $this->assertSame('False Negative', $classifier->classify(true, true, false)['label']);
    }

    public function test_invalid_audio_is_not_answer_correctness_matrix_result(): void
    {
        $result = (new AsrConfusionResultClassifier())->classify(true, false, false);

        $this->assertSame('NA', $result['code']);
        $this->assertSame('Not Applicable', $result['label']);
        $this->assertSame('Invalid Audio', $result['recording_validity']);
    }

    public function test_true_gop_score_prefers_existing_overall_gop_fields(): void
    {
        $classifier = new AsrConfusionResultClassifier();

        $this->assertSame(0.391, $classifier->trueGopScore(
            ['gop_score' => 0.2],
            ['overall_gop_score' => 0.391],
        ));
        $this->assertSame(39.1, $classifier->confidencePercent(0.391));
    }
}
