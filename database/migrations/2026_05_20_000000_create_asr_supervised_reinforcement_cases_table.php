<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asr_supervised_reinforcement_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_hash', 64)->unique();
            $table->string('status')->default('approved');
            $table->text('expected_text');
            $table->text('raw_transcript');
            $table->text('normalized_transcript')->nullable();
            $table->text('corrected_transcript')->nullable();
            $table->text('displayed_transcript')->nullable();
            $table->string('prompt_type', 100)->nullable();
            $table->string('task_type', 100)->nullable();
            $table->string('activity_type')->nullable();
            $table->string('assessment_type', 100)->nullable();
            $table->string('module_type', 100)->nullable();
            $table->string('item_id', 100)->nullable();
            $table->string('item_source', 50)->nullable();
            $table->json('similarity_scores')->nullable();
            $table->json('decision_result')->nullable();
            $table->json('request_context')->nullable();
            $table->json('ai_response')->nullable();
            $table->json('reinforcement_response')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['prompt_type', 'assessment_type']);
            $table->index('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asr_supervised_reinforcement_cases');
    }
};
