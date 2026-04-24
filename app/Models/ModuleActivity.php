<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleActivity extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'module_id', 'learning_content_id', 'sequence', 'activity_type', 'title', 'configuration'];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
