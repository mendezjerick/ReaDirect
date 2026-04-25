<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasteryThreshold extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'module_id', 'min_score', 'max_score', 'decision', 'next_module_key', 'rule_key'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
