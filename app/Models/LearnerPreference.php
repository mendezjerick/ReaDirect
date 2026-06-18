<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerPreference extends Model
{
    protected $fillable = [
        'learner_id',
        'listening_mode',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
