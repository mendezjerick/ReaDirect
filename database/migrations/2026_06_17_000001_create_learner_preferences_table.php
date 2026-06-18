<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learner_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('listening_mode', 32)->default('manual');
            $table->timestamps();

            $table->index('listening_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_preferences');
    }
};
