<?php

namespace Tests\Feature;

use App\Services\AI\ReadirectAIService;
use App\Services\ASR\AsrConfusionFixtureService;
use App\Services\ASR\AsrConfusionMatrixRunner;
use App\Services\ASR\AsrResponseNormalizer;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AsrConfusionMatrixRunnerTest extends TestCase
{
    public function test_wrong_audible_fixture_accepted_and_scored_incorrect_is_true_negative(): void
    {
        $audioPath = $this->tempAudioPath();
        $fixture = $this->fixture('wrong_word');
        $fixtureService = \Mockery::mock(AsrConfusionFixtureService::class);
        $fixtureService->shouldReceive('loadManifest')->andReturn([
            'generated_at' => '2026-06-25T00:00:00Z',
            'fixtures' => [$fixture],
        ]);
        $fixtureService->shouldReceive('manifestPath')->andReturn('/tmp/manifest.json');
        $fixtureService->shouldReceive('absoluteAudioPath')->andReturn($audioPath);
        $ai = \Mockery::mock(ReadirectAIService::class);
        $ai->shouldReceive('analyzeAudio')->andReturn([
            'ok' => true,
            'expected_text' => 'A',
            'raw_transcript' => 'justice',
            'corrected_transcript' => 'justice',
            'displayed_transcript' => 'justice',
            'accepted' => false,
            'retry_required' => false,
            'uncertain' => false,
            'prompt_type' => 'letter',
            'beam_search' => true,
        ]);

        $run = (new AsrConfusionMatrixRunner($fixtureService, $ai, app(AsrResponseNormalizer::class)))
            ->run(save: false);

        $this->assertSame('TN', $run['rows'][0]['confusion_matrix_result']);
        $this->assertTrue($run['rows'][0]['recording_accepted']);
        $this->assertFalse($run['rows'][0]['final_correctness_result']);
        $this->assertSame(1, $run['summary']['overall']['TN']);
        $this->assertSame(1, $run['summary']['overall']['valid_audible_wrong_accepted']);
        $this->assertSame(0, $run['summary']['overall']['valid_audible_wrong_incorrectly_rejected']);
    }

    public function test_wrong_audible_fixture_rejected_is_reported_as_recording_validity_failure(): void
    {
        $audioPath = $this->tempAudioPath();
        $fixture = $this->fixture('wrong_sentence');
        $fixtureService = \Mockery::mock(AsrConfusionFixtureService::class);
        $fixtureService->shouldReceive('loadManifest')->andReturn([
            'generated_at' => '2026-06-25T00:00:00Z',
            'fixtures' => [$fixture],
        ]);
        $fixtureService->shouldReceive('manifestPath')->andReturn('/tmp/manifest.json');
        $fixtureService->shouldReceive('absoluteAudioPath')->andReturn($audioPath);
        $ai = \Mockery::mock(ReadirectAIService::class);
        $ai->shouldReceive('analyzeAudio')->andReturn([
            'ok' => true,
            'expected_text' => 'A',
            'raw_transcript' => '',
            'corrected_transcript' => '',
            'displayed_transcript' => '',
            'accepted' => false,
            'retry_required' => true,
            'uncertain' => true,
            'uncertainty_reasons' => ['blank_asr_transcript'],
            'prompt_type' => 'letter',
        ]);

        $run = (new AsrConfusionMatrixRunner($fixtureService, $ai, app(AsrResponseNormalizer::class)))
            ->run(save: false);

        $this->assertSame('wrong_audio_rejected', $run['rows'][0]['confusion_matrix_result']);
        $this->assertSame('WRONG_AUDIBLE_AUDIO_REJECTED_AS_INVALID', $run['rows'][0]['failure_reason']);
        $this->assertTrue($run['rows'][0]['wrong_audible_rejected_as_invalid']);
        $this->assertSame(0, $run['summary']['overall']['TN']);
        $this->assertSame(1, $run['summary']['overall']['valid_audible_wrong_incorrectly_rejected']);
    }

    public function test_invalid_audio_controls_are_tracked_outside_answer_matrix(): void
    {
        $audioPath = $this->tempAudioPath();
        $fixture = [
            ...$this->fixture('silence'),
            'spoken_text' => '',
            'expected_recording_valid' => false,
        ];
        $fixtureService = \Mockery::mock(AsrConfusionFixtureService::class);
        $fixtureService->shouldReceive('loadManifest')->andReturn([
            'generated_at' => '2026-06-25T00:00:00Z',
            'fixtures' => [$fixture],
        ]);
        $fixtureService->shouldReceive('manifestPath')->andReturn('/tmp/manifest.json');
        $fixtureService->shouldReceive('absoluteAudioPath')->andReturn($audioPath);
        $ai = \Mockery::mock(ReadirectAIService::class);
        $ai->shouldReceive('analyzeAudio')->andReturn([
            'ok' => true,
            'raw_transcript' => '',
            'corrected_transcript' => '',
            'displayed_transcript' => '',
            'accepted' => false,
            'retry_required' => true,
            'uncertain' => true,
            'uncertainty_reasons' => ['no_speech_detected'],
            'quality_gate_failed' => true,
            'prompt_type' => 'letter',
        ]);

        $run = (new AsrConfusionMatrixRunner($fixtureService, $ai, app(AsrResponseNormalizer::class)))
            ->run(save: false);

        $this->assertSame('invalid_audio_rejected', $run['rows'][0]['confusion_matrix_result']);
        $this->assertSame(0, $run['summary']['overall']['matrix_total']);
        $this->assertSame(1, $run['summary']['overall']['silence_rejected']);
        $this->assertSame(0, $run['summary']['overall']['TN']);
    }

    public function test_automated_matrix_command_outputs_summary(): void
    {
        $this->mock(AsrConfusionFixtureService::class, function ($mock): void {
            $mock->shouldReceive('loadManifest')->andReturn([
                'fixtures' => [$this->fixture('correct')],
            ]);
        });

        $this->mock(AsrConfusionMatrixRunner::class, function ($mock): void {
            $mock->shouldReceive('run')->with(null, null, false)->andReturn([
                'run_id' => 'asr_confusion_test',
                'total_tested_recordings' => 2,
                'summary' => [
                    'overall' => [
                        'TP' => 1,
                        'TN' => 1,
                        'FP' => 0,
                        'FN' => 0,
                        'accuracy' => 1.0,
                        'precision' => 1.0,
                        'recall' => 1.0,
                        'f1' => 1.0,
                        'valid_audible_wrong_accepted' => 1,
                        'valid_audible_wrong_incorrectly_rejected' => 0,
                        'silence_rejected' => 0,
                        'low_volume_rejected' => 0,
                        'silence_incorrectly_accepted' => 0,
                        'low_volume_incorrectly_accepted' => 0,
                    ],
                ],
            ]);
        });

        $exitCode = Artisan::call('asr:run-confusion-matrix', ['--no-save' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('ASR confusion matrix completed.', Artisan::output());
    }

    private function fixture(string $type): array
    {
        return [
            'category' => 'diagnostic',
            'task' => 'task_1a',
            'task_label' => 'Diagnostic Task 1A',
            'item_key' => 'T1-L001',
            'expected_answer' => 'A',
            'accepted_answers' => ['A'],
            'fixture_type' => $type,
            'spoken_text' => $type === 'wrong_word' ? 'justice' : 'I am a hero.',
            'expected_correct' => false,
            'expected_recording_valid' => true,
            'prompt_type' => 'letter',
            'task_type' => 'crla_task_1_letter',
            'activity_type' => 'letter',
            'assessment_type' => 'diagnostic',
            'module_key' => null,
            'audio_file_path' => 'asr_confusion_fixtures/diagnostic/task_1a/item_t1_l001/wrong/'.$type.'.wav',
        ];
    }

    private function tempAudioPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'asr_matrix_');
        file_put_contents($path, 'fake-audio');

        return $path;
    }
}
