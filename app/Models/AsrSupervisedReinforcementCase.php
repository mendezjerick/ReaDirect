<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsrSupervisedReinforcementCase extends Model
{
    protected $fillable = [
        'case_hash',
        'status',
        'expected_text',
        'raw_transcript',
        'normalized_transcript',
        'corrected_transcript',
        'displayed_transcript',
        'prompt_type',
        'task_type',
        'activity_type',
        'assessment_type',
        'module_type',
        'item_id',
        'item_source',
        'similarity_scores',
        'decision_result',
        'request_context',
        'ai_response',
        'reinforcement_response',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'similarity_scores' => 'array',
            'decision_result' => 'array',
            'request_context' => 'array',
            'ai_response' => 'array',
            'reinforcement_response' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
