<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedVoiceLine extends Model
{
    protected $fillable = [
        'line_key',
        'agent',
        'intent',
        'context',
        'source_repo',
        'source_file',
        'source_hash',
        'text',
        'synthesis_text',
        'text_hash',
        'voice_id',
        'engine',
        'expressive_engine',
        'selected_original_reference_audio_path',
        'selected_original_reference_duration_seconds',
        'selected_original_reference_priority',
        'selected_original_reference_weight',
        'reference_style_audio_path',
        'reference_style_duration_seconds',
        'reference_style_engine',
        'reference_style_status',
        'reference_style_error',
        'kokoro_identity_audio_path',
        'kokoro_identity_duration_seconds',
        'kokoro_identity_engine',
        'kokoro_identity_voice_id',
        'kokoro_identity_style_source_path',
        'kokoro_identity_status',
        'kokoro_identity_error',
        'defense_audio_path',
        'stage2_demo_audio_path',
        'active_audio_path',
        'active_audio_type',
        'speaker_reference_path',
        'emotion_prompt',
        'sample_rate',
        'channels',
        'format',
        'status',
        'generation_error',
        'cache_key',
        'checksum',
        'is_static',
        'is_dynamic_template',
        'is_defense_demo',
    ];

    protected $casts = [
        'selected_original_reference_duration_seconds' => 'float',
        'reference_style_duration_seconds' => 'float',
        'kokoro_identity_duration_seconds' => 'float',
        'selected_original_reference_weight' => 'integer',
        'sample_rate' => 'integer',
        'channels' => 'integer',
        'is_static' => 'boolean',
        'is_dynamic_template' => 'boolean',
        'is_defense_demo' => 'boolean',
    ];
}
