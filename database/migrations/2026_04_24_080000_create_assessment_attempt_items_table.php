<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_content_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_csv_id')->nullable();
            $table->string('task_type');
            $table->unsignedSmallInteger('sequence');
            $table->json('prompt_snapshot')->nullable();
            $table->timestamp('selected_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_attempt_id', 'task_type', 'sequence'], 'assessment_attempt_task_sequence_unique');
            $table->index(['assessment_attempt_id', 'task_type'], 'assessment_attempt_task_type_index');
            $table->index('learning_content_id');
            $table->index('source_csv_id');
            $table->index('selected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_items');
    }
};
