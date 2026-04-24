<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleAttemptItem extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'module_attempt_id',
        'module_activity_id',
        'source_csv_id',
        'activity_type',
        'sequence',
        'prompt_snapshot',
        'is_mastery_item',
        'selected_at',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_snapshot' => 'array',
            'is_mastery_item' => 'boolean',
            'selected_at' => 'datetime',
            'answered_at' => 'datetime',
        ];
    }

    public function moduleAttempt(): BelongsTo
    {
        return $this->belongsTo(ModuleAttempt::class);
    }

    public function moduleActivity(): BelongsTo
    {
        return $this->belongsTo(ModuleActivity::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ModuleActivityResponse::class);
    }
}
