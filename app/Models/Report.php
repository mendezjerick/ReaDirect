<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasPublicId;

    protected $fillable = ['public_id', 'learner_id', 'school_id', 'class_id', 'generated_by', 'report_type', 'status', 'file_path', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
