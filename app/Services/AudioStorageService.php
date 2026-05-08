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
    public const MIN_TRANSCRIBABLE_SECONDS = 0.5;

    public const ALLOWED_MIME_TYPES = [
        'audio/webm',
        'video/webm',
        'audio/wav',
        'audio/x-wav',
        'audio/mpeg',
        'audio/mp4',
        'audio/ogg',
        'audio/flac',
        'audio/x-flac',
    ];

    public const ALLOWED_EXTENSIONS = [
        'webm',
        'wav',
        'mp3',
        'm4a',
        'ogg',
        'flac',
    ];

    public static function validationRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:10240',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $mimeType = strtolower((string) $value->getMimeType());
                $extension = strtolower((string) $value->getClientOriginalExtension());

                if (in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    return;
                }

                if ($extension === '' && in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                    return;
                }

                $fail('The '.$attribute.' field must be a file of type: '.implode(', ', self::ALLOWED_EXTENSIONS).'.');
            },
        ];
    }

    public static function durationValidationRules(): array
    {
        return ['nullable', 'numeric', 'min:'.self::MIN_TRANSCRIBABLE_SECONDS, 'max:600'];
    }

    public static function durationValidationMessages(string $attribute = 'duration_seconds'): array
    {
        return [
            $attribute.'.min' => 'That recording was too short. Please try again and speak clearly.',
        ];
    }

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
