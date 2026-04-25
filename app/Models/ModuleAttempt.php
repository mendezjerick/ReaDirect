<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleAttempt extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'learner_id', 'module_id', 'status', 'score', 'mastery_decision', 'rule_applied', 'decision_reason', 'is_sandbox', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['score' => 'float', 'is_sandbox' => 'boolean', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ModuleAttemptItem::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ModuleActivityResponse::class);
    }
}
