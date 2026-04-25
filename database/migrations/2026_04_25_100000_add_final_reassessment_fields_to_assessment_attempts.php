<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_attempts', 'baseline_assessment_attempt_id')) {
                $table->foreignId('baseline_assessment_attempt_id')
                    ->nullable()
                    ->after('agent_profile_id')
                    ->constrained('assessment_attempts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('assessment_attempts', 'comparison_summary')) {
                $table->json('comparison_summary')->nullable()->after('decision_reason');
            }

            $table->index(['learner_id', 'attempt_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropIndex(['learner_id', 'attempt_type', 'status']);

            if (Schema::hasColumn('assessment_attempts', 'baseline_assessment_attempt_id')) {
                $table->dropConstrainedForeignId('baseline_assessment_attempt_id');
            }

            if (Schema::hasColumn('assessment_attempts', 'comparison_summary')) {
                $table->dropColumn('comparison_summary');
            }
        });
    }
};
