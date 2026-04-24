<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('district')->nullable();
            $table->string('division')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('grade_level')->default('Grade 1');
            $table->string('school_year')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'created_at']);
            $table->index('teacher_id');
        });

        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('learner_code')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('grade_level')->default('Grade 1');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'created_at']);
            $table->index(['class_id', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('agent_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('agent_type');
            $table->text('purpose');
            $table->json('guardrails')->nullable();
            $table->boolean('uses_llm')->default(false);
            $table->boolean('is_fixed')->default(true);
            $table->timestamps();
        });

        Schema::create('learning_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('content_type');
            $table->string('title');
            $table->string('prompt')->nullable();
            $table->json('payload')->nullable();
            $table->json('accepted_answers')->nullable();
            $table->string('difficulty')->default('grade_1');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['content_type', 'created_at']);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedTinyInteger('sequence')->unique();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('module_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_content_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('activity_type');
            $table->string('title');
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->index(['module_id', 'sequence']);
            $table->index('created_at');
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attempt_type');
            $table->string('status')->default('in_progress');
            $table->unsignedTinyInteger('task_1_score')->nullable();
            $table->unsignedTinyInteger('task_2a_score')->nullable();
            $table->unsignedTinyInteger('task_2b_score')->nullable();
            $table->unsignedTinyInteger('crla_total_score')->nullable();
            $table->string('crla_classification')->nullable();
            $table->float('reading_accuracy')->nullable();
            $table->float('comprehension_percentage')->nullable();
            $table->float('final_reading_score')->nullable();
            $table->string('reading_classification')->nullable();
            $table->string('rule_applied')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['learner_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('assessment_task_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('assessment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_content_id')->nullable()->constrained()->nullOnDelete();
            $table->string('task_key');
            $table->unsignedSmallInteger('item_number');
            $table->string('prompt')->nullable();
            $table->text('response_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->float('score')->default(0);
            $table->string('miscue_type')->nullable();
            $table->string('rule_applied')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['assessment_attempt_id', 'task_key']);
            $table->index('created_at');
        });

        Schema::create('module_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('in_progress');
            $table->float('score')->nullable();
            $table->string('mastery_decision')->nullable();
            $table->string('rule_applied')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['learner_id', 'created_at']);
            $table->index(['module_id', 'created_at']);
        });

        Schema::create('module_activity_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('module_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_activity_id')->constrained()->cascadeOnDelete();
            $table->text('response_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->float('score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['module_attempt_id', 'created_at']);
        });

        Schema::create('mastery_thresholds', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->float('min_score');
            $table->float('max_score')->nullable();
            $table->string('decision');
            $table->string('next_module_key')->nullable();
            $table->string('rule_key');
            $table->timestamps();
            $table->index(['module_id', 'min_score']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recommendation_type');
            $table->string('decision');
            $table->string('rule_applied');
            $table->text('decision_reason');
            $table->json('input_scores')->nullable();
            $table->timestamps();
            $table->index(['learner_id', 'created_at']);
            $table->index('assessment_attempt_id');
        });

        Schema::create('audio_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('module_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['learner_id', 'created_at']);
            $table->index('assessment_attempt_id');
            $table->index('module_attempt_id');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('learner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type');
            $table->string('status')->default('draft');
            $table->string('file_path')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['learner_id', 'created_at']);
            $table->index(['school_id', 'created_at']);
            $table->index('class_id');
        });

        Schema::create('llm_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('agent_profile_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->unsignedSmallInteger('version');
            $table->string('status')->default('draft');
            $table->text('template');
            $table->json('variables')->nullable();
            $table->timestamps();
            $table->unique(['key', 'version']);
            $table->index(['agent_profile_id', 'created_at']);
        });

        Schema::create('llm_interactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('learner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('llm_prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('sanitized_context')->nullable();
            $table->text('response_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['learner_id', 'created_at']);
            $table->index(['agent_profile_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('llm_interactions');
        Schema::dropIfExists('llm_prompt_templates');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('audio_files');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('mastery_thresholds');
        Schema::dropIfExists('module_activity_responses');
        Schema::dropIfExists('module_attempts');
        Schema::dropIfExists('assessment_task_responses');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('module_activities');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('learning_contents');
        Schema::dropIfExists('agent_profiles');
        Schema::dropIfExists('learners');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('schools');
    }
};
