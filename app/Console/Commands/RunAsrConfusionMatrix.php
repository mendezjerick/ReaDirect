<?php

namespace App\Console\Commands;

use App\Services\ASR\AsrConfusionContentRepository;
use App\Services\ASR\AsrConfusionFixtureService;
use App\Services\ASR\AsrConfusionMatrixRunner;
use Illuminate\Console\Command;

class RunAsrConfusionMatrix extends Command
{
    protected $signature = 'asr:run-confusion-matrix
        {--category= : Limit to diagnostic, final, or modules}
        {--limit= : Limit number of fixtures for smoke testing}
        {--no-save : Do not write latest_results.json or a timestamped run file}';

    protected $description = 'Run generated ASR fixture audio through the existing ASR pathway and compute confusion metrics.';

    public function handle(
        AsrConfusionMatrixRunner $runner,
        AsrConfusionContentRepository $content,
        AsrConfusionFixtureService $fixtures
    ): int {
        $category = trim((string) $this->option('category'));
        $category = $category === 'module' ? 'modules' : $category;

        if ($category !== '' && ! in_array($category, $content->availableCategories(), true)) {
            $this->error('Category must be one of: '.implode(', ', $content->availableCategories()));

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? max(1, (int) $this->option('limit'))
            : null;

        $availableFixtures = collect($fixtures->loadManifest()['fixtures'] ?? [])
            ->filter(fn (array $fixture) => $category === '' || ($fixture['category'] ?? null) === $category)
            ->count();

        if ($availableFixtures === 0) {
            $this->error('No ASR confusion fixtures found. Run php artisan asr:generate-fixtures first.');

            return self::FAILURE;
        }

        $run = $runner->run(
            category: $category !== '' ? $category : null,
            limit: $limit,
            save: ! (bool) $this->option('no-save'),
        );
        $overall = $run['summary']['overall'];

        $this->info('ASR confusion matrix completed.');
        $this->line('Run ID: '.$run['run_id']);
        $this->line('Total tested recordings: '.$run['total_tested_recordings']);
        $this->table(
            ['TP', 'TN', 'FP', 'FN', 'Accuracy', 'Precision', 'Recall', 'F1'],
            [[
                $overall['TP'],
                $overall['TN'],
                $overall['FP'],
                $overall['FN'],
                $overall['accuracy'] ?? '-',
                $overall['precision'] ?? '-',
                $overall['recall'] ?? '-',
                $overall['f1'] ?? '-',
            ]]
        );
        $this->table(
            ['Wrong Audible Accepted', 'Wrong Audible Rejected', 'Silence Rejected', 'Low Volume Rejected', 'Silence Accepted', 'Low Volume Accepted'],
            [[
                $overall['valid_audible_wrong_accepted'],
                $overall['valid_audible_wrong_incorrectly_rejected'],
                $overall['silence_rejected'],
                $overall['low_volume_rejected'],
                $overall['silence_incorrectly_accepted'],
                $overall['low_volume_incorrectly_accepted'],
            ]]
        );

        if (! (bool) $this->option('no-save')) {
            $this->line('Latest results: '.storage_path('app/asr_confusion_fixtures/latest_results.json'));
        }

        return ($overall['FP'] > 0
            || $overall['FN'] > 0
            || $overall['valid_audible_wrong_incorrectly_rejected'] > 0
            || $overall['silence_incorrectly_accepted'] > 0
            || $overall['low_volume_incorrectly_accepted'] > 0)
                ? self::FAILURE
                : self::SUCCESS;
    }
}
