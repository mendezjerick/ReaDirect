<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerModuleUsedTarget extends Model
{
    protected $fillable = [
        'learner_id',
        'module_key',
        'lesson_key',
        'target_type',
        'canonical_target',
        'canonical_target_hash',
        'cycle_number',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'cycle_number' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
