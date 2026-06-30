<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleLessonProgress extends Model
{
    protected $table = 'module_lesson_progress';

    protected $fillable = [
        'module_attempt_id',
        'module_key',
        'activity_type',
        'lesson_attempt_number',
        'status',
        'item_count',
        'correct_count',
        'required_correct',
        'started_at',
        'completed_at',
        'mastered_at',
    ];

    protected function casts(): array
    {
        return [
            'lesson_attempt_number' => 'integer',
            'item_count' => 'integer',
            'correct_count' => 'integer',
            'required_correct' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'mastered_at' => 'datetime',
        ];
    }

    public function moduleAttempt(): BelongsTo
    {
        return $this->belongsTo(ModuleAttempt::class);
    }
}
