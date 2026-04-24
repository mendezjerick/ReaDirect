<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class LlmInteraction extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'learner_id',
        'agent_profile_id',
        'source_type',
        'source_id',
        'llm_prompt_template_id',
        'provider',
        'model',
        'sanitized_context',
        'input_summary',
        'response_summary',
        'output_text',
        'fallback_used',
        'safety_status',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sanitized_context' => 'array',
            'input_summary' => 'array',
            'fallback_used' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
