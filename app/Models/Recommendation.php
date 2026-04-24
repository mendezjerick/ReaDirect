<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'learner_id',
        'assessment_attempt_id',
        'module_id',
        'recommended_module_id',
        'recommendation_type',
        'source_type',
        'source_id',
        'decision',
        'rule_applied',
        'generated_by',
        'decision_reason',
        'input_scores',
    ];

    protected function casts(): array
    {
        return ['input_scores' => 'array'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function recommendedModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'recommended_module_id');
    }
}
