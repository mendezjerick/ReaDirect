<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_activity_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('module_activity_responses', 'agent_commentary_text')) {
                $table->text('agent_commentary_text')->nullable()->after('feedback_text');
            }

            if (! Schema::hasColumn('module_activity_responses', 'agent_commentary_source')) {
                $table->string('agent_commentary_source')->nullable()->after('agent_commentary_text');
            }

            if (! Schema::hasColumn('module_activity_responses', 'agent_type')) {
                $table->string('agent_type')->nullable()->after('agent_commentary_source');
            }
        });

        Schema::table('assessment_task_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_task_responses', 'agent_commentary_text')) {
                $table->text('agent_commentary_text')->nullable()->after('rule_applied');
            }

            if (! Schema::hasColumn('assessment_task_responses', 'agent_commentary_source')) {
                $table->string('agent_commentary_source')->nullable()->after('agent_commentary_text');
            }

            if (! Schema::hasColumn('assessment_task_responses', 'agent_type')) {
                $table->string('agent_type')->nullable()->after('agent_commentary_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_task_responses', function (Blueprint $table) {
            foreach (['agent_type', 'agent_commentary_source', 'agent_commentary_text'] as $column) {
                if (Schema::hasColumn('assessment_task_responses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('module_activity_responses', function (Blueprint $table) {
            foreach (['agent_type', 'agent_commentary_source', 'agent_commentary_text'] as $column) {
                if (Schema::hasColumn('module_activity_responses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
