<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class LlmInteraction extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'learner_id', 'agent_profile_id', 'llm_prompt_template_id', 'provider', 'model', 'sanitized_context', 'response_summary', 'metadata'];

    protected function casts(): array
    {
        return ['sanitized_context' => 'array', 'metadata' => 'array'];
    }
}
