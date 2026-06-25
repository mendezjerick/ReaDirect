<?php

namespace App\Services\ASR;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsrConfusionFixtureGenerator
{
    public function __construct(
        private readonly AsrConfusionContentRepository $content,
        private readonly AsrConfusionFixtureService $fixtures,
    ) {
    }

    public function generate(?string $category = null, bool $force = false): array
    {
        $this->fixtures->ensureRoot();

        $items = $this->content->items($category);
        $manifestFixtures = [];
        $generated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            foreach ($this->fixtureDefinitions($item['expected_answer']) as $definition) {
                $relativePath = $this->relativePath($item, $definition);
                $absolutePath = storage_path('app/'.$relativePath);
                File::ensureDirectoryExists(dirname($absolutePath));

                if (is_file($absolutePath) && ! $force) {
                    $skipped++;
                } else {
                    $this->writeFixtureAudio($absolutePath, $definition);
                    $generated++;
                }

                $manifestFixtures[] = [
                    'category' => $item['category'],
                    'task' => $item['task'],
                    'task_label' => $item['task_label'],
                    'item_key' => $item['item_key'],
                    'item_slug' => $item['item_slug'],
                    'expected_answer' => $item['expected_answer'],
                    'accepted_answers' => $item['accepted_answers'],
                    'audio_file_path' => $relativePath,
                    'fixture_type' => $definition['type'],
                    'spoken_text' => $definition['spoken_text'],
                    'expected_correct' => $definition['expected_correct'],
                    'expected_recording_valid' => $definition['expected_recording_valid'],
                    'prompt_type' => $item['prompt_type'],
                    'task_type' => $item['task_type'],
                    'activity_type' => $item['activity_type'],
                    'assessment_type' => $item['assessment_type'],
                    'module_key' => $item['module_key'],
                    'source_file' => $item['source_file'],
                    'prompt_text' => $item['prompt_text'],
                    'metadata' => $item['metadata'],
                ];
            }
        }

        $manifest = [
            'version' => 1,
            'generated_at' => now()->toISOString(),
            'root' => AsrConfusionFixtureService::RELATIVE_ROOT,
            'tts' => [
                'provider' => config('readirect.tts.provider', 'kokoro'),
                'base_url' => rtrim((string) config('readirect.tts.base_url'), '/'),
                'agent' => 'miss_ciel',
                'voice' => config('readirect.tts.voices.miss_ciel'),
                'speed' => (float) config('readirect.tts.speeds.miss_ciel', 1.0),
            ],
            'source_counts' => collect($items)->countBy(fn (array $item) => $item['category'].'/'.$item['task'])->all(),
            'fixtures' => $manifestFixtures,
        ];

        $this->fixtures->writeManifest($manifest);

        return [
            'manifest' => $this->fixtures->loadManifest(),
            'generated_audio_files' => $generated,
            'skipped_existing_audio_files' => $skipped,
            'item_count' => count($items),
            'manifest_path' => $this->fixtures->manifestPath(),
        ];
    }

    private function fixtureDefinitions(string $expected): array
    {
        return [
            [
                'type' => 'correct',
                'directory' => 'correct',
                'filename' => 'correct.wav',
                'spoken_text' => $expected,
                'expected_correct' => true,
                'expected_recording_valid' => true,
                'audio_kind' => 'tts',
            ],
            [
                'type' => 'wrong_letter',
                'directory' => 'wrong',
                'filename' => 'wrong_letter.wav',
                'spoken_text' => $this->wrongLetter($expected),
                'expected_correct' => false,
                'expected_recording_valid' => true,
                'audio_kind' => 'tts',
            ],
            [
                'type' => 'wrong_word',
                'directory' => 'wrong',
                'filename' => 'wrong_word.wav',
                'spoken_text' => $this->wrongWord($expected),
                'expected_correct' => false,
                'expected_recording_valid' => true,
                'audio_kind' => 'tts',
            ],
            [
                'type' => 'wrong_sentence',
                'directory' => 'wrong',
                'filename' => 'wrong_sentence.wav',
                'spoken_text' => $this->wrongSentence($expected),
                'expected_correct' => false,
                'expected_recording_valid' => true,
                'audio_kind' => 'tts',
            ],
            [
                'type' => 'silence',
                'directory' => 'invalid',
                'filename' => 'silence.wav',
                'spoken_text' => '',
                'expected_correct' => false,
                'expected_recording_valid' => false,
                'audio_kind' => 'silence',
            ],
            [
                'type' => 'low_volume',
                'directory' => 'invalid',
                'filename' => 'low_volume.wav',
                'spoken_text' => 'low volume control',
                'expected_correct' => false,
                'expected_recording_valid' => false,
                'audio_kind' => 'low_volume',
            ],
        ];
    }

    private function relativePath(array $item, array $definition): string
    {
        return implode('/', [
            AsrConfusionFixtureService::RELATIVE_ROOT,
            $item['category'],
            $item['task'],
            'item_'.$item['item_slug'],
            $definition['directory'],
            $definition['filename'],
        ]);
    }

    private function writeFixtureAudio(string $absolutePath, array $definition): void
    {
        match ($definition['audio_kind']) {
            'tts' => $this->writeTts($absolutePath, (string) $definition['spoken_text']),
            'silence' => $this->writeControlWav($absolutePath, 0.0),
            'low_volume' => $this->writeControlWav($absolutePath, 0.0005),
            default => throw new RuntimeException('Unknown fixture audio kind.'),
        };
    }

    private function writeTts(string $absolutePath, string $text): void
    {
        $safeText = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        if ($safeText === '') {
            throw new RuntimeException('Cannot generate Kokoro audio for empty text.');
        }

        $response = Http::timeout(max(10, (int) config('readirect.tts.timeout_seconds', 10) * 3))
            ->accept('audio/wav')
            ->asJson()
            ->post(rtrim((string) config('readirect.tts.base_url', 'http://127.0.0.1:8002'), '/').'/synthesize', [
                'agent' => 'miss_ciel',
                'text' => $safeText,
                'voice' => null,
                'speed' => (float) config('readirect.tts.speeds.miss_ciel', 1.0),
                'cache' => true,
            ]);

        if (! $response->successful() || strlen($response->body()) < 44) {
            throw new RuntimeException('Kokoro fixture generation failed for text: '.$safeText);
        }

        File::put($absolutePath, $response->body());
    }

    private function writeControlWav(string $absolutePath, float $amplitude, int $seconds = 2, int $sampleRate = 16000): void
    {
        $sampleCount = $seconds * $sampleRate;
        $pcm = '';

        for ($i = 0; $i < $sampleCount; $i++) {
            $sample = $amplitude === 0.0
                ? 0
                : (int) round(sin(2 * pi() * 220 * ($i / $sampleRate)) * $amplitude * 32767);
            $pcm .= pack('v', $sample < 0 ? $sample + 65536 : $sample);
        }

        $dataSize = strlen($pcm);
        $byteRate = $sampleRate * 2;
        $header = 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, 1, $sampleRate, $byteRate, 2, 16)
            .'data'
            .pack('V', $dataSize);

        File::put($absolutePath, $header.$pcm);
    }

    private function wrongLetter(string $expected): string
    {
        $first = mb_strtoupper(mb_substr(trim($expected), 0, 1));

        return $first === 'B' ? 'A' : 'B';
    }

    private function wrongWord(string $expected): string
    {
        return mb_strtolower(trim($expected)) === 'justice' ? 'banana' : 'justice';
    }

    private function wrongSentence(string $expected): string
    {
        return mb_strtolower(trim($expected)) === 'i am a hero' ? 'The sky is blue.' : 'I am a hero.';
    }
}
