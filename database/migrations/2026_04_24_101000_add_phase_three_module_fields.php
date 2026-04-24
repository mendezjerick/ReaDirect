<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            if (! Schema::hasColumn('learners', 'current_module_id')) {
                $table->foreignId('current_module_id')->nullable()->after('class_id')->constrained('modules')->nullOnDelete();
            }

            if (! Schema::hasColumn('learners', 'current_stage')) {
                $table->string('current_stage')->nullable()->after('current_module_id');
            }
        });

        Schema::table('module_activity_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('module_activity_responses', 'module_attempt_item_id')) {
                $table->foreignId('module_attempt_item_id')->nullable()->after('module_activity_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('module_activity_responses', 'learner_answer')) {
                $table->text('learner_answer')->nullable()->after('response_text');
            }

            if (! Schema::hasColumn('module_activity_responses', 'expected_answer')) {
                $table->string('expected_answer')->nullable()->after('learner_answer');
            }

            if (! Schema::hasColumn('module_activity_responses', 'feedback_text')) {
                $table->text('feedback_text')->nullable()->after('score');
            }

            if (! Schema::hasColumn('module_activity_responses', 'retry_count')) {
                $table->unsignedSmallInteger('retry_count')->default(0)->after('feedback_text');
            }

            if (! Schema::hasColumn('module_activity_responses', 'is_mastery_item')) {
                $table->boolean('is_mastery_item')->default(false)->after('retry_count');
            }

            if (! Schema::hasColumn('module_activity_responses', 'error_type')) {
                $table->string('error_type')->nullable()->after('is_mastery_item');
            }

            if (! Schema::hasColumn('module_activity_responses', 'metadata_json')) {
                $table->json('metadata_json')->nullable()->after('metadata');
            }

            $table->index(['module_attempt_item_id']);
            $table->index(['module_attempt_id', 'is_mastery_item']);
        });
    }

    public function down(): void
    {
        Schema::table('module_activity_responses', function (Blueprint $table) {
            $table->dropIndex(['module_attempt_item_id']);
            $table->dropIndex(['module_attempt_id', 'is_mastery_item']);

            if (Schema::hasColumn('module_activity_responses', 'module_attempt_item_id')) {
                $table->dropConstrainedForeignId('module_attempt_item_id');
            }

            $table->dropColumn([
                'learner_answer',
                'expected_answer',
                'feedback_text',
                'retry_count',
                'is_mastery_item',
                'error_type',
                'metadata_json',
            ]);
        });

        Schema::table('learners', function (Blueprint $table) {
            if (Schema::hasColumn('learners', 'current_module_id')) {
                $table->dropConstrainedForeignId('current_module_id');
            }

            if (Schema::hasColumn('learners', 'current_stage')) {
                $table->dropColumn('current_stage');
            }
        });
    }
};
