<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_files', function (Blueprint $table) {
            if (! Schema::hasColumn('audio_files', 'assessment_task_response_id')) {
                $table->foreignId('assessment_task_response_id')->nullable()->after('module_attempt_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('audio_files', 'module_activity_response_id')) {
                $table->foreignId('module_activity_response_id')->nullable()->after('assessment_task_response_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('audio_files', 'file_path')) {
                $table->string('file_path')->nullable()->after('path');
            }

            if (! Schema::hasColumn('audio_files', 'original_filename')) {
                $table->string('original_filename')->nullable()->after('file_path');
            }

            if (! Schema::hasColumn('audio_files', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('size_bytes');
            }

            if (! Schema::hasColumn('audio_files', 'file_hash')) {
                $table->string('file_hash')->nullable()->after('file_size');
            }

            if (! Schema::hasColumn('audio_files', 'duration_seconds')) {
                $table->float('duration_seconds')->nullable()->after('duration_ms');
            }

            if (! Schema::hasColumn('audio_files', 'recording_context')) {
                $table->string('recording_context')->nullable()->after('duration_seconds');
            }

            if (! Schema::hasColumn('audio_files', 'sync_status')) {
                $table->string('sync_status')->default('synced')->after('recording_context');
            }

            $table->index(['assessment_task_response_id']);
            $table->index(['module_activity_response_id']);
            $table->index(['learner_id', 'recording_context']);
        });

        Schema::table('assessment_task_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_task_responses', 'audio_file_id')) {
                $table->foreignId('audio_file_id')->nullable()->after('assessment_attempt_item_id')->constrained('audio_files')->nullOnDelete();
            }

            if (! Schema::hasColumn('assessment_task_responses', 'transcript_source')) {
                $table->string('transcript_source')->nullable()->after('learner_transcript');
            }

            $table->index(['audio_file_id']);
        });

        Schema::table('module_activity_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('module_activity_responses', 'audio_file_id')) {
                $table->foreignId('audio_file_id')->nullable()->after('module_attempt_item_id')->constrained('audio_files')->nullOnDelete();
            }

            if (! Schema::hasColumn('module_activity_responses', 'transcript_source')) {
                $table->string('transcript_source')->nullable()->after('learner_answer');
            }

            $table->index(['audio_file_id']);
        });
    }

    public function down(): void
    {
        Schema::table('module_activity_responses', function (Blueprint $table) {
            $table->dropIndex(['audio_file_id']);
            if (Schema::hasColumn('module_activity_responses', 'audio_file_id')) {
                $table->dropConstrainedForeignId('audio_file_id');
            }
            if (Schema::hasColumn('module_activity_responses', 'transcript_source')) {
                $table->dropColumn('transcript_source');
            }
        });

        Schema::table('assessment_task_responses', function (Blueprint $table) {
            $table->dropIndex(['audio_file_id']);
            if (Schema::hasColumn('assessment_task_responses', 'audio_file_id')) {
                $table->dropConstrainedForeignId('audio_file_id');
            }
            if (Schema::hasColumn('assessment_task_responses', 'transcript_source')) {
                $table->dropColumn('transcript_source');
            }
        });

        Schema::table('audio_files', function (Blueprint $table) {
            $table->dropIndex(['assessment_task_response_id']);
            $table->dropIndex(['module_activity_response_id']);
            $table->dropIndex(['learner_id', 'recording_context']);
            if (Schema::hasColumn('audio_files', 'assessment_task_response_id')) {
                $table->dropConstrainedForeignId('assessment_task_response_id');
            }
            if (Schema::hasColumn('audio_files', 'module_activity_response_id')) {
                $table->dropConstrainedForeignId('module_activity_response_id');
            }
            $table->dropColumn([
                'file_path',
                'original_filename',
                'file_size',
                'file_hash',
                'duration_seconds',
                'recording_context',
                'sync_status',
            ]);
        });
    }
};
