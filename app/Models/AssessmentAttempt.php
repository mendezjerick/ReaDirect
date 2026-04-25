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
        'baseline_assessment_attempt_id',
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
        'incorrect_words',
        'comprehension_correct_count',
        'assigned_module_id',
        'placement_decision',
        'rule_applied',
        'decision_reason',
        'comparison_summary',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'reading_accuracy' => 'float',
            'comprehension_percentage' => 'float',
            'final_reading_score' => 'float',
            'incorrect_words' => 'integer',
            'comprehension_correct_count' => 'integer',
            'comparison_summary' => 'array',
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

    public function selectedItems(): HasMany
    {
        return $this->hasMany(AssessmentAttemptItem::class);
    }

    public function assignedModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'assigned_module_id');
    }

    public function baselineAssessment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baseline_assessment_attempt_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
