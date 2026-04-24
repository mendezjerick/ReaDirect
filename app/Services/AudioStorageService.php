<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AudioStorageService
{
    public const ALLOWED_MIME_TYPES = [
        'audio/webm',
        'audio/wav',
        'audio/x-wav',
        'audio/mpeg',
        'audio/mp4',
        'audio/ogg',
    ];

    public function store(
        UploadedFile $file,
        Learner $learner,
        string $recordingContext,
        ?AssessmentAttempt $assessmentAttempt = null,
        ?ModuleAttempt $moduleAttempt = null,
        ?float $durationSeconds = null,
        array $metadata = []
    ): AudioFile {
        $disk = 'local';
        $path = $file->store('audio/learners/'.$learner->public_id, $disk);
        $absolutePath = Storage::disk($disk)->path($path);

        return AudioFile::create([
            'learner_id' => $learner->id,
            'assessment_attempt_id' => $assessmentAttempt?->id,
            'module_attempt_id' => $moduleAttempt?->id,
            'disk' => $disk,
            'path' => $path,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'file_size' => $file->getSize(),
            'file_hash' => hash_file('sha256', $absolutePath),
            'duration_ms' => $durationSeconds ? (int) round($durationSeconds * 1000) : null,
            'duration_seconds' => $durationSeconds,
            'recording_context' => $recordingContext,
            'sync_status' => 'synced',
            'metadata' => $metadata,
        ]);
    }

    public function attachToAssessmentResponse(AudioFile $audioFile, int $responseId): void
    {
        $audioFile->update(['assessment_task_response_id' => $responseId]);
    }

    public function attachToModuleResponse(AudioFile $audioFile, int $responseId): void
    {
        $audioFile->update(['module_activity_response_id' => $responseId]);
    }
}
