<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasPublicId;

    protected $table = 'classes';

    protected $fillable = ['public_id', 'school_id', 'teacher_id', 'name', 'grade_level', 'school_year'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function learners(): HasMany
    {
        return $this->hasMany(Learner::class, 'class_id');
    }
}
