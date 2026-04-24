<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'learner_id', 'assessment_attempt_id', 'module_id', 'recommendation_type', 'decision', 'rule_applied', 'decision_reason', 'input_scores'];

    protected function casts(): array
    {
        return ['input_scores' => 'array'];
    }
}
