<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentTaskResponse extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'assessment_attempt_id',
        'learner_id',
        'learning_content_id',
        'assessment_attempt_item_id',
        'task_key',
        'task_type',
        'item_number',
        'prompt',
        'expected_answer',
        'learner_transcript',
        'selected_answer',
        'response_text',
        'is_correct',
        'score',
        'miscue_type',
        'error_type',
        'response_time_seconds',
        'rule_applied',
        'metadata',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'score' => 'float', 'metadata' => 'array', 'metadata_json' => 'array'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }

    public function selectedItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttemptItem::class, 'assessment_attempt_item_id');
    }
}
