<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->string('reward_type');
            $table->unsignedInteger('amount')->default(1);
            $table->string('reason');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique([
                'learner_id',
                'reward_type',
                'reason',
                'source_type',
                'source_id',
            ], 'learner_rewards_unique_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_rewards');
    }
};
