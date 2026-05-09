<?php

namespace App\Http\Controllers;

use App\Services\AssessmentModeService;
use App\Services\TTS\AgentTtsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentVoiceController extends Controller
{
    public function synthesize(Request $request, AgentTtsService $tts, AssessmentModeService $mode): JsonResponse
    {
        $validated = $request->validate([
            'agent' => ['required', 'string', 'max:64'],
            'text' => ['required', 'string', 'max:600'],
        ]);

        $payload = $tts->speechPayload(
            $validated['agent'],
            $validated['text'],
            $mode->canShowAssessmentDebug($request)
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
}
