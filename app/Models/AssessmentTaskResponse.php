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
        'learning_content_id',
        'task_key',
        'item_number',
        'prompt',
        'response_text',
        'is_correct',
        'score',
        'miscue_type',
        'rule_applied',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'score' => 'float', 'metadata' => 'array'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }
}
