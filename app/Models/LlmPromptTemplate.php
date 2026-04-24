<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class LlmPromptTemplate extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'agent_profile_id', 'key', 'version', 'status', 'template', 'variables'];

    protected function casts(): array
    {
        return ['variables' => 'array'];
    }
}
