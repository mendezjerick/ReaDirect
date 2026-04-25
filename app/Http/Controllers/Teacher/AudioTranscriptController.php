<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AudioFile;
use App\Models\AuditLog;
use App\Services\STT\TranscriptSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AudioTranscriptController extends Controller
{
    public function update(Request $request, AudioFile $audioFile, TranscriptSanitizer $sanitizer): RedirectResponse
    {
        Gate::authorize('view', $audioFile);

        $validated = $request->validate([
            'transcript' => ['required', 'string', 'max:1000'],
        ]);

        $transcript = $sanitizer->sanitize($validated['transcript']);

        $audioFile->update([
            'transcript' => $transcript,
            'metadata' => array_merge($audioFile->metadata ?? [], [
                'transcript_reviewed_by_user_id' => $request->user()?->id,
                'transcript_reviewed_at' => now()->toDateTimeString(),
            ]),
        ]);

        if ($audioFile->assessmentTaskResponse) {
            $audioFile->assessmentTaskResponse->update([
                'learner_transcript' => $transcript,
                'response_text' => $transcript,
                'transcript_source' => 'teacher_review',
            ]);
        }

        if ($audioFile->moduleActivityResponse) {
            $audioFile->moduleActivityResponse->update([
                'learner_transcript' => $transcript,
                'learner_answer' => $transcript,
                'response_text' => $transcript,
                'transcript_source' => 'teacher_review',
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'teacher.updated_audio_transcript',
            'auditable_type' => AudioFile::class,
            'auditable_id' => $audioFile->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Transcript saved for review. Scores were not changed.');
    }
}
