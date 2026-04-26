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
        'ai_transcript',
        'ai_normalized_transcript',
        'ai_similarity_label',
        'ai_character_similarity',
        'ai_token_similarity',
        'ai_expected_phonemes',
        'ai_actual_phonemes',
        'ai_phoneme_similarity',
        'ai_error_type',
        'ai_error_position',
        'ai_feedback_hint',
        'ai_coach_hint_key',
        'ai_skill_signal',
        'ai_target_phoneme',
        'ai_recommended_practice_focus',
        'ai_response',
        'ai_analyzed_at',
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
            'ai_character_similarity' => 'float',
            'ai_token_similarity' => 'float',
            'ai_expected_phonemes' => 'array',
            'ai_actual_phonemes' => 'array',
            'ai_phoneme_similarity' => 'float',
            'ai_response' => 'array',
            'ai_analyzed_at' => 'datetime',
            'retry_count' => 'integer',
            'is_mastery_item' => 'boolean',
            'metadata' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function hasAiAnalysis(): bool
    {
        return $this->ai_analyzed_at !== null || $this->ai_response !== null;
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
