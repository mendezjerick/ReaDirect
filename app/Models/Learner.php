<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Learner extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'user_id',
        'school_id',
        'class_id',
        'current_module_id',
        'current_stage',
        'learner_code',
        'first_name',
        'last_name',
        'birthdate',
        'grade_level',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['birthdate' => 'date', 'metadata' => 'array', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function currentModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'current_module_id');
    }

    public function moduleAttempts(): HasMany
    {
        return $this->hasMany(ModuleAttempt::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(LearnerReward::class);
    }

    public function preference(): HasOne
    {
        return $this->hasOne(LearnerPreference::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
