<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccessService;
use App\Services\ASR\AsrConfusionContentRepository;
use App\Services\ASR\AsrConfusionFixtureService;
use App\Services\ASR\AsrConfusionMatrixRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminConfusionMatrixController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AsrConfusionFixtureService $fixtures): Response
    {
        $access->ensureTesting($request->user());
        $manifest = $fixtures->loadManifest();

        return Inertia::render('Admin/ConfusionMatrix', [
            'manifest' => [
                'generated_at' => $manifest['generated_at'] ?? null,
                'path' => $fixtures->manifestPath(),
                'summary' => $manifest['summary'] ?? [],
            ],
            'latestRun' => $fixtures->latestResults(),
            'fixtureOptions' => $fixtures->fixtureOptions(),
            'routes' => [
                'fixtures' => route('admin.confusion-matrix.fixtures'),
                'results' => route('admin.confusion-matrix.results'),
                'runFixture' => route('admin.confusion-matrix.run-fixture'),
            ],
        ]);
    }

    public function fixtures(Request $request, AdminAccessService $access, AsrConfusionFixtureService $fixtures): JsonResponse
    {
        $access->ensureTesting($request->user());
        $manifest = $fixtures->loadManifest();

        return response()->json([
            'manifest' => [
                'generated_at' => $manifest['generated_at'] ?? null,
                'path' => $fixtures->manifestPath(),
                'summary' => $manifest['summary'] ?? [],
            ],
            'fixtureOptions' => $fixtures->fixtureOptions(),
        ]);
    }

    public function results(Request $request, AdminAccessService $access, AsrConfusionFixtureService $fixtures): JsonResponse
    {
        $access->ensureTesting($request->user());

        return response()->json([
            'latestRun' => $fixtures->latestResults(),
        ]);
    }

    public function runFixture(
        Request $request,
        AdminAccessService $access,
        AsrConfusionContentRepository $content,
        AsrConfusionFixtureService $fixtures,
        AsrConfusionMatrixRunner $runner
    ): JsonResponse {
        $access->ensureTesting($request->user());
        @set_time_limit(max(30, ((int) config('readirect_ai.timeout_seconds', 60)) + 15));

        $validated = $request->validate([
            'category' => ['required', Rule::in($content->availableCategories())],
            'task' => ['required', 'string', 'max:100'],
            'item_key' => ['required', 'string', 'max:100'],
            'fixture_type' => ['required', Rule::in(['correct', 'wrong_letter', 'wrong_word', 'wrong_sentence', 'silence', 'low_volume'])],
        ]);

        $fixture = $fixtures->findFixture(
            $validated['category'],
            $validated['task'],
            $validated['item_key'],
            $validated['fixture_type'],
        );

        abort_unless($fixture !== null, 404, 'Fixture was not found in the manifest.');

        return response()->json([
            'result' => $runner->runFixture($fixture),
        ]);
    }
}
