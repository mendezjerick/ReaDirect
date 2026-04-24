<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'sequence', 'key', 'title', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ModuleActivity::class);
    }
}
