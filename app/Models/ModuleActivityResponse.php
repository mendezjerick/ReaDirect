<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ModuleActivityResponse extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'module_attempt_id', 'module_activity_id', 'response_text', 'is_correct', 'score', 'metadata'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'score' => 'float', 'metadata' => 'array'];
    }
}
