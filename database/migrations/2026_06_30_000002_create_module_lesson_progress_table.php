<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('module_key');
            $table->string('activity_type');
            $table->unsignedSmallInteger('lesson_attempt_number')->default(1);
            $table->string('status')->default('in_progress');
            $table->unsignedSmallInteger('item_count')->default(5);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('required_correct')->default(4);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('mastered_at')->nullable();
            $table->timestamps();

            $table->unique(['module_attempt_id', 'activity_type', 'lesson_attempt_number'], 'module_lesson_attempt_unique');
            $table->index(['module_attempt_id', 'activity_type', 'status'], 'module_lesson_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_lesson_progress');
    }
};
