<?php

use App\Support\LearnerStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('learners')->whereNull('current_stage')->update(['current_stage' => LearnerStage::NEW]);
        DB::table('learners')->where('current_stage', 'module_practice')->update(['current_stage' => LearnerStage::MODULE_PRACTICE_IN_PROGRESS]);
        DB::table('learners')->where('current_stage', 'module_mastery')->update(['current_stage' => LearnerStage::MODULE_MASTERY_IN_PROGRESS]);

        Schema::table('learners', function (Blueprint $table): void {
            $table->index('current_stage');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table): void {
            $table->dropIndex(['current_stage']);
        });
    }
};
