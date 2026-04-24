<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAttemptItem extends Model
{
    protected $fillable = [
        'assessment_attempt_id',
        'learning_content_id',
        'source_csv_id',
        'task_type',
        'sequence',
        'prompt_snapshot',
        'selected_at',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_snapshot' => 'array',
            'selected_at' => 'datetime',
            'answered_at' => 'datetime',
        ];
    }

    public function assessmentAttempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class);
    }

    public function learningContent(): BelongsTo
    {
        return $this->belongsTo(LearningContent::class);
    }
}
