<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'name', 'district', 'division', 'metadata', 'is_active'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'is_active' => 'boolean'];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function learners(): HasMany
    {
        return $this->hasMany(Learner::class);
    }
}
