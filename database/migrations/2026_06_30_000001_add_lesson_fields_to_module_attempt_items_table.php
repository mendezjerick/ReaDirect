<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_attempt_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('lesson_attempt_number')->default(1);
            $table->unsignedSmallInteger('lesson_item_number')->default(1);

            $table->index(['module_attempt_id', 'activity_type', 'lesson_attempt_number'], 'module_attempt_lesson_items_index');
        });
    }

    public function down(): void
    {
        Schema::table('module_attempt_items', function (Blueprint $table): void {
            $table->dropIndex('module_attempt_lesson_items_index');
            $table->dropColumn(['lesson_attempt_number', 'lesson_item_number']);
        });
    }
};
