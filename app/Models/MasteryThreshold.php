<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class MasteryThreshold extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'module_id', 'min_score', 'max_score', 'decision', 'next_module_key', 'rule_key'];
}
