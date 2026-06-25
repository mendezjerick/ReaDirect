<?php

namespace App\Services\ASR;

use Illuminate\Support\Facades\File;

class AsrConfusionFixtureService
{
    public const RELATIVE_ROOT = 'asr_confusion_fixtures';

    public function rootPath(): string
    {
        return storage_path('app/'.self::RELATIVE_ROOT);
    }

    public function manifestPath(): string
    {
        return $this->rootPath().DIRECTORY_SEPARATOR.'manifest.json';
    }

    public function latestResultsPath(): string
    {
        return $this->rootPath().DIRECTORY_SEPARATOR.'latest_results.json';
    }

    public function runsPath(): string
    {
        return $this->rootPath().DIRECTORY_SEPARATOR.'runs';
    }

    public function ensureRoot(): void
    {
        File::ensureDirectoryExists($this->rootPath());
        File::ensureDirectoryExists($this->runsPath());

        $gitignore = $this->rootPath().DIRECTORY_SEPARATOR.'.gitignore';
        if (! is_file($gitignore)) {
            File::put($gitignore, "*\n!.gitignore\n");
        }
    }

    public function loadManifest(): array
    {
        if (! is_file($this->manifestPath())) {
            return [
                'version' => 1,
                'generated_at' => null,
                'root' => self::RELATIVE_ROOT,
                'fixtures' => [],
                'summary' => $this->summary([]),
            ];
        }

        $payload = json_decode((string) file_get_contents($this->manifestPath()), true);

        if (! is_array($payload)) {
            return [
                'version' => 1,
                'generated_at' => null,
                'root' => self::RELATIVE_ROOT,
                'fixtures' => [],
                'summary' => $this->summary([]),
            ];
        }

        $payload['fixtures'] = array_values($payload['fixtures'] ?? []);
        $payload['summary'] = $this->summary($payload['fixtures']);

        return $payload;
    }

    public function writeManifest(array $manifest): void
    {
        $this->ensureRoot();
        $manifest['summary'] = $this->summary($manifest['fixtures'] ?? []);
        File::put($this->manifestPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function latestResults(): ?array
    {
        if (! is_file($this->latestResultsPath())) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($this->latestResultsPath()), true);

        return is_array($payload) ? $payload : null;
    }

    public function saveRun(array $run): void
    {
        $this->ensureRoot();
        $runId = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string) ($run['run_id'] ?? now()->format('Ymd_His')));
        $json = json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        File::put($this->latestResultsPath(), $json);
        File::put($this->runsPath().DIRECTORY_SEPARATOR.$runId.'.json', $json);
    }

    public function findFixture(string $category, string $task, string $itemKey, string $fixtureType): ?array
    {
        foreach ($this->loadManifest()['fixtures'] ?? [] as $fixture) {
            if (($fixture['category'] ?? null) !== $category) {
                continue;
            }
            if (($fixture['task'] ?? null) !== $task) {
                continue;
            }
            if (($fixture['item_key'] ?? null) !== $itemKey) {
                continue;
            }
            if (($fixture['fixture_type'] ?? null) !== $fixtureType) {
                continue;
            }

            return $fixture;
        }

        return null;
    }

    public function absoluteAudioPath(array $fixture): string
    {
        return storage_path('app/'.ltrim((string) ($fixture['audio_file_path'] ?? ''), '/\\'));
    }

    public function fixtureOptions(): array
    {
        $fixtures = $this->loadManifest()['fixtures'] ?? [];
        $categories = [];

        foreach ($fixtures as $fixture) {
            $category = (string) ($fixture['category'] ?? '');
            $task = (string) ($fixture['task'] ?? '');
            $itemKey = (string) ($fixture['item_key'] ?? '');

            if ($category === '' || $task === '' || $itemKey === '') {
                continue;
            }

            $categories[$category] ??= [
                'key' => $category,
                'label' => ucfirst($category),
                'tasks' => [],
            ];
            $categories[$category]['tasks'][$task] ??= [
                'key' => $task,
                'label' => (string) ($fixture['task_label'] ?? $task),
                'items' => [],
            ];
            $categories[$category]['tasks'][$task]['items'][$itemKey] ??= [
                'key' => $itemKey,
                'label' => $itemKey.' - '.(string) ($fixture['expected_answer'] ?? ''),
                'expected_answer' => (string) ($fixture['expected_answer'] ?? ''),
                'fixtures' => [],
            ];
            $categories[$category]['tasks'][$task]['items'][$itemKey]['fixtures'][] = [
                'type' => (string) ($fixture['fixture_type'] ?? ''),
                'label' => ucwords(str_replace('_', ' ', (string) ($fixture['fixture_type'] ?? ''))),
                'spoken_text' => (string) ($fixture['spoken_text'] ?? ''),
                'audio_file_path' => (string) ($fixture['audio_file_path'] ?? ''),
            ];
        }

        foreach ($categories as &$category) {
            foreach ($category['tasks'] as &$task) {
                $task['items'] = array_values($task['items']);
            }
            $category['tasks'] = array_values($category['tasks']);
        }

        return array_values($categories);
    }

    public function summary(array $fixtures): array
    {
        $byCategory = [];
        $byTask = [];
        $byType = [];

        foreach ($fixtures as $fixture) {
            $category = (string) ($fixture['category'] ?? 'unknown');
            $task = $category.'/'.(string) ($fixture['task'] ?? 'unknown');
            $type = (string) ($fixture['fixture_type'] ?? 'unknown');

            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            $byTask[$task] = ($byTask[$task] ?? 0) + 1;
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        ksort($byCategory);
        ksort($byTask);
        ksort($byType);

        return [
            'total_fixtures' => count($fixtures),
            'by_category' => $byCategory,
            'by_task' => $byTask,
            'by_type' => $byType,
        ];
    }
}
