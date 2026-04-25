<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class AgentProfile extends Model
{
    use HasPublicId;

    public const ASSESSMENT = 'assessment';
    public const COACH_FEEDBACK = 'coach_feedback';
    public const EVALUATOR_RECOMMENDATION = 'evaluator_recommendation';

    protected $fillable = ['public_id', 'key', 'name', 'agent_type', 'purpose', 'guardrails', 'sprite_path', 'default_state', 'voice_settings', 'uses_llm', 'is_fixed', 'is_active'];

    protected function casts(): array
    {
        return ['guardrails' => 'array', 'voice_settings' => 'array', 'uses_llm' => 'boolean', 'is_fixed' => 'boolean', 'is_active' => 'boolean'];
    }
}
