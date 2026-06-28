<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_voice_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('line_key')->unique();
            $table->string('agent', 32)->index();
            $table->string('intent', 64)->nullable()->index();
            $table->string('context')->nullable();
            $table->string('source_repo')->nullable();
            $table->string('source_file')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->text('text')->nullable();
            $table->string('text_hash', 64)->nullable()->index();
            $table->string('voice_id', 64)->nullable();
            $table->string('engine', 64)->nullable();
            $table->string('expressive_engine', 64)->nullable();

            $table->string('selected_original_reference_audio_path')->nullable();
            $table->decimal('selected_original_reference_duration_seconds', 8, 3)->nullable();
            $table->string('selected_original_reference_priority', 32)->nullable();
            $table->unsignedSmallInteger('selected_original_reference_weight')->nullable();

            $table->string('reference_style_audio_path')->nullable();
            $table->decimal('reference_style_duration_seconds', 8, 3)->nullable();
            $table->string('reference_style_engine', 64)->nullable();
            $table->string('reference_style_status', 64)->default('pending')->index();
            $table->text('reference_style_error')->nullable();

            $table->string('kokoro_identity_audio_path')->nullable();
            $table->decimal('kokoro_identity_duration_seconds', 8, 3)->nullable();
            $table->string('kokoro_identity_engine', 64)->nullable();
            $table->string('kokoro_identity_voice_id', 64)->nullable();
            $table->string('kokoro_identity_style_source_path')->nullable();
            $table->string('kokoro_identity_status', 64)->default('pending')->index();
            $table->text('kokoro_identity_error')->nullable();

            $table->string('defense_audio_path')->nullable();
            $table->string('stage2_demo_audio_path')->nullable();
            $table->string('active_audio_path')->nullable();
            $table->string('active_audio_type', 64)->nullable()->index();
            $table->string('speaker_reference_path')->nullable();
            $table->string('emotion_prompt')->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedTinyInteger('channels')->nullable();
            $table->string('format', 16)->default('wav');
            $table->string('status', 64)->default('pending')->index();
            $table->text('generation_error')->nullable();
            $table->string('cache_key', 128)->nullable()->index();
            $table->string('checksum', 64)->nullable();
            $table->boolean('is_static')->default(true)->index();
            $table->boolean('is_dynamic_template')->default(false)->index();
            $table->boolean('is_defense_demo')->default(false)->index();
            $table->timestamps();

            $table->index(['agent', 'intent']);
            $table->index(['is_static', 'is_defense_demo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_voice_lines');
    }
};
