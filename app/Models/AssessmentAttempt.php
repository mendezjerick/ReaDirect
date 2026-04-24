<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentAttempt extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'learner_id',
        'agent_profile_id',
        'attempt_type',
        'status',
        'task_1_score',
        'task_2a_score',
        'task_2b_score',
        'crla_total_score',
        'crla_classification',
        'reading_accuracy',
        'comprehension_percentage',
        'final_reading_score',
        'reading_classification',
        'rule_applied',
        'decision_reason',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'reading_accuracy' => 'float',
            'comprehension_percentage' => 'float',
            'final_reading_score' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentTaskResponse::class);
    }
}
