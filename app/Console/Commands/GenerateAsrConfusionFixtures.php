<?php

namespace App\Console\Commands;

use App\Services\ASR\AsrConfusionContentRepository;
use App\Services\ASR\AsrConfusionFixtureGenerator;
use Illuminate\Console\Command;

class GenerateAsrConfusionFixtures extends Command
{
    protected $signature = 'asr:generate-fixtures
        {--force : Overwrite existing generated WAV files}
        {--category= : Limit to diagnostic, final, or modules}';

    protected $description = 'Generate Kokoro ASR confusion-matrix fixture audio and manifest.';

    public function handle(AsrConfusionFixtureGenerator $generator, AsrConfusionContentRepository $content): int
    {
        $category = trim((string) $this->option('category'));
        $category = $category === 'module' ? 'modules' : $category;

        if ($category !== '' && ! in_array($category, $content->availableCategories(), true)) {
            $this->error('Category must be one of: '.implode(', ', $content->availableCategories()));

            return self::FAILURE;
        }

        try {
            $result = $generator->generate($category !== '' ? $category : null, (bool) $this->option('force'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            $this->line('Confirm the Kokoro TTS service is running at '.config('readirect.tts.base_url').'.');

            return self::FAILURE;
        }

        $this->info('ASR confusion fixtures generated.');
        $this->line('Manifest: '.$result['manifest_path']);
        $this->line('Items: '.$result['item_count']);
        $this->line('Generated WAV files: '.$result['generated_audio_files']);
        $this->line('Skipped existing WAV files: '.$result['skipped_existing_audio_files']);
        $this->line('Total manifest fixtures: '.$result['manifest']['summary']['total_fixtures']);

        return self::SUCCESS;
    }
}
