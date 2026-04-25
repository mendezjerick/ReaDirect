<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleActivityResponse extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'module_attempt_id',
        'module_activity_id',
        'module_attempt_item_id',
        'audio_file_id',
        'response_text',
        'learner_answer',
        'transcript_source',
        'learner_transcript',
        'stt_confidence',
        'expected_answer',
        'is_correct',
        'score',
        'feedback_text',
        'agent_commentary_text',
        'agent_commentary_source',
        'agent_type',
        'retry_count',
        'is_mastery_item',
        'error_type',
        'metadata',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'score' => 'float',
            'stt_confidence' => 'float',
            'retry_count' => 'integer',
            'is_mastery_item' => 'boolean',
            'metadata' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function moduleAttempt(): BelongsTo
    {
        return $this->belongsTo(ModuleAttempt::class);
    }

    public function moduleActivity(): BelongsTo
    {
        return $this->belongsTo(ModuleActivity::class);
    }

    public function moduleAttemptItem(): BelongsTo
    {
        return $this->belongsTo(ModuleAttemptItem::class);
    }

    public function audioFile(): BelongsTo
    {
        return $this->belongsTo(AudioFile::class);
    }
}
