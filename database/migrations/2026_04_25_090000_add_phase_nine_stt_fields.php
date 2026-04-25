<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_files', function (Blueprint $table) {
            if (! Schema::hasColumn('audio_files', 'transcript')) {
                $table->text('transcript')->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('audio_files', 'stt_confidence')) {
                $table->float('stt_confidence')->nullable()->after('transcript');
            }

            if (! Schema::hasColumn('audio_files', 'stt_phonemes')) {
                $table->json('stt_phonemes')->nullable()->after('stt_confidence');
            }

            if (! Schema::hasColumn('audio_files', 'stt_timestamps')) {
                $table->json('stt_timestamps')->nullable()->after('stt_phonemes');
            }

            if (! Schema::hasColumn('audio_files', 'stt_error')) {
                $table->text('stt_error')->nullable()->after('stt_timestamps');
            }

            if (! Schema::hasColumn('audio_files', 'stt_completed_at')) {
                $table->timestamp('stt_completed_at')->nullable()->after('stt_error');
            }
        });

        Schema::table('assessment_task_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_task_responses', 'stt_confidence')) {
                $table->float('stt_confidence')->nullable()->after('transcript_source');
            }
        });

        Schema::table('module_activity_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('module_activity_responses', 'learner_transcript')) {
                $table->text('learner_transcript')->nullable()->after('learner_answer');
            }

            if (! Schema::hasColumn('module_activity_responses', 'stt_confidence')) {
                $table->float('stt_confidence')->nullable()->after('transcript_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('module_activity_responses', function (Blueprint $table) {
            foreach (['learner_transcript', 'stt_confidence'] as $column) {
                if (Schema::hasColumn('module_activity_responses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('assessment_task_responses', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_task_responses', 'stt_confidence')) {
                $table->dropColumn('stt_confidence');
            }
        });

        Schema::table('audio_files', function (Blueprint $table) {
            foreach (['transcript', 'stt_confidence', 'stt_phonemes', 'stt_timestamps', 'stt_error', 'stt_completed_at'] as $column) {
                if (Schema::hasColumn('audio_files', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
