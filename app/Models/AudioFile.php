<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioFile extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'learner_id',
        'assessment_attempt_id',
        'module_attempt_id',
        'assessment_task_response_id',
        'module_activity_response_id',
        'disk',
        'path',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'file_size',
        'file_hash',
        'duration_ms',
        'duration_seconds',
        'recording_context',
        'sync_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'duration_seconds' => 'float'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function assessmentAttempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class);
    }

    public function moduleAttempt(): BelongsTo
    {
        return $this->belongsTo(ModuleAttempt::class);
    }

    public function assessmentTaskResponse(): BelongsTo
    {
        return $this->belongsTo(AssessmentTaskResponse::class);
    }

    public function moduleActivityResponse(): BelongsTo
    {
        return $this->belongsTo(ModuleActivityResponse::class);
    }
}
