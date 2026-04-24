<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class AudioFile extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'learner_id', 'assessment_attempt_id', 'module_attempt_id', 'disk', 'path', 'mime_type', 'size_bytes', 'duration_ms', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
