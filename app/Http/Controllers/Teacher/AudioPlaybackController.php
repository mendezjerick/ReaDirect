<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AudioFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AudioPlaybackController extends Controller
{
    public function __invoke(Request $request, AudioFile $audioFile): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('view', $audioFile);

        $path = $audioFile->file_path ?? $audioFile->path;
        abort_unless($path && Storage::disk($audioFile->disk)->exists($path), 404);

        return Storage::disk($audioFile->disk)->response($path, $audioFile->original_filename, [
            'Content-Type' => $audioFile->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=0',
        ]);
    }
}
