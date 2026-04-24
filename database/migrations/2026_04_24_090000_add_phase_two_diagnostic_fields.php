<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('incorrect_words')->nullable()->after('reading_classification');
            $table->unsignedTinyInteger('comprehension_correct_count')->nullable()->after('incorrect_words');
            $table->foreignId('assigned_module_id')->nullable()->after('comprehension_correct_count')->constrained('modules')->nullOnDelete();
            $table->string('placement_decision')->nullable()->after('assigned_module_id');
        });

        Schema::table('assessment_task_responses', function (Blueprint $table) {
            $table->foreignId('learner_id')->nullable()->after('assessment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_attempt_item_id')->nullable()->after('learning_content_id')->constrained()->nullOnDelete();
            $table->string('task_type')->nullable()->after('task_key');
            $table->string('expected_answer')->nullable()->after('prompt');
            $table->text('learner_transcript')->nullable()->after('expected_answer');
            $table->string('selected_answer')->nullable()->after('learner_transcript');
            $table->string('error_type')->nullable()->after('miscue_type');
            $table->unsignedSmallInteger('response_time_seconds')->nullable()->after('error_type');
            $table->json('metadata_json')->nullable()->after('metadata');

            $table->index(['learner_id', 'created_at']);
            $table->index(['assessment_attempt_item_id']);
            $table->index(['assessment_attempt_id', 'task_type']);
        });

        Schema::table('recommendations', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('recommendation_type');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('recommended_module_id')->nullable()->after('module_id')->constrained('modules')->nullOnDelete();
            $table->string('generated_by')->nullable()->after('rule_applied');

            $table->index(['source_type', 'source_id']);
            $table->index('recommended_module_id');
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropIndex(['recommended_module_id']);
            $table->dropConstrainedForeignId('recommended_module_id');
            $table->dropColumn(['source_type', 'source_id', 'generated_by']);
        });

        Schema::table('assessment_task_responses', function (Blueprint $table) {
            $table->dropIndex(['learner_id', 'created_at']);
            $table->dropIndex(['assessment_attempt_item_id']);
            $table->dropIndex(['assessment_attempt_id', 'task_type']);
            $table->dropConstrainedForeignId('learner_id');
            $table->dropConstrainedForeignId('assessment_attempt_item_id');
            $table->dropColumn([
                'task_type',
                'expected_answer',
                'learner_transcript',
                'selected_answer',
                'error_type',
                'response_time_seconds',
                'metadata_json',
            ]);
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_module_id');
            $table->dropColumn(['incorrect_words', 'comprehension_correct_count', 'placement_decision']);
        });
    }
};
