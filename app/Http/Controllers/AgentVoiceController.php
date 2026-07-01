<?php

namespace App\Http\Controllers;

use App\Services\AssessmentModeService;
use App\Services\TTS\AgentTtsService;
use App\Services\VoiceLines\VoicePlaybackStageService;
use App\Models\GeneratedVoiceLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentVoiceController extends Controller
{
    public function synthesize(Request $request, AgentTtsService $tts, AssessmentModeService $mode): JsonResponse
    {
        $validated = $request->validate([
            'agent' => ['required', 'string', 'max:64'],
            'text' => ['required', 'string', 'max:600'],
            'intent' => ['nullable', 'string', 'max:64'],
            'line_key' => ['nullable', 'string', 'max:128'],
            'metadata' => ['nullable', 'array'],
        ]);

        $payload = $tts->speechPayload(
            $validated['agent'],
            $validated['text'],
            $mode->canShowAssessmentDebug($request),
            [
                'intent' => $validated['intent'] ?? null,
                'line_key' => $validated['line_key'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ]
        );

        return response()->json($payload);
    }

    public function show(string $cacheKey, AgentTtsService $tts): BinaryFileResponse
    {
        $path = $tts->pathForCacheKey($cacheKey);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }

    public function showGenerated(GeneratedVoiceLine $line, ?string $stage = null): BinaryFileResponse
    {
        $stage = app(VoicePlaybackStageService::class)->current();
        $path = $stage === VoicePlaybackStageService::KOKORO
            ? $line->kokoro_identity_audio_path
            : $line->reference_style_audio_path;

        abort_if(! $path || ! Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'public, max-age=604800, immutable',
            'X-ReaDirect-Voice-Line' => $line->line_key,
            'X-ReaDirect-Voice-Stage' => $stage,
        ]);
    }
}
