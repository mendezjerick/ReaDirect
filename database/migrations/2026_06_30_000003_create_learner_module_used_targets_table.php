<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_module_used_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->string('module_key');
            $table->string('lesson_key')->nullable();
            $table->string('target_type');
            $table->text('canonical_target');
            $table->string('canonical_target_hash', 64);
            $table->unsignedInteger('cycle_number')->default(1);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['learner_id', 'module_key', 'target_type', 'canonical_target_hash', 'cycle_number'],
                'learner_module_used_target_unique'
            );
            $table->index(['learner_id', 'module_key', 'target_type', 'cycle_number'], 'learner_module_used_target_cycle_index');
            $table->index(['lesson_key', 'used_at'], 'learner_module_used_target_lesson_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_module_used_targets');
    }
};
