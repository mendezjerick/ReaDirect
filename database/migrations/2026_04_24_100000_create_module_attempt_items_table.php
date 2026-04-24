<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_attempt_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('module_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_csv_id')->nullable();
            $table->string('activity_type');
            $table->unsignedSmallInteger('sequence');
            $table->json('prompt_snapshot')->nullable();
            $table->boolean('is_mastery_item')->default(false);
            $table->timestamp('selected_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['module_attempt_id', 'activity_type']);
            $table->index(['module_attempt_id', 'is_mastery_item']);
            $table->index('module_activity_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_attempt_items');
    }
};
