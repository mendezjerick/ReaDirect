<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentTaskResponse extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'assessment_attempt_id',
        'learner_id',
        'learning_content_id',
        'assessment_attempt_item_id',
        'audio_file_id',
        'task_key',
        'task_type',
        'item_number',
        'prompt',
        'expected_answer',
        'learner_transcript',
        'transcript_source',
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
        'selected_answer',
        'response_text',
        'is_correct',
        'score',
        'miscue_type',
        'error_type',
        'response_time_seconds',
        'rule_applied',
        'agent_commentary_text',
        'agent_commentary_source',
        'agent_type',
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
            'metadata' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function hasAiAnalysis(): bool
    {
        return $this->ai_analyzed_at !== null || $this->ai_response !== null;
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }

    public function selectedItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttemptItem::class, 'assessment_attempt_item_id');
    }

    public function audioFile(): BelongsTo
    {
        return $this->belongsTo(AudioFile::class);
    }
}
