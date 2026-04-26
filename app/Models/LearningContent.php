<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class LearningContent extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'content_type', 'title', 'prompt', 'payload', 'accepted_answers', 'enrichment_metadata', 'difficulty', 'is_active'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'accepted_answers' => 'array', 'enrichment_metadata' => 'array', 'is_active' => 'boolean'];
    }
}
