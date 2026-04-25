<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_attempts', 'is_sandbox')) {
                $table->boolean('is_sandbox')->default(false)->after('comparison_summary');
            }

            $table->index(['is_sandbox', 'attempt_type']);
        });

        Schema::table('module_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('module_attempts', 'is_sandbox')) {
                $table->boolean('is_sandbox')->default(false)->after('decision_reason');
            }

            $table->index(['is_sandbox', 'module_id']);
        });

        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('metadata');
            }
        });

        Schema::table('learners', function (Blueprint $table) {
            if (! Schema::hasColumn('learners', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('metadata');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password');
            }
        });

        Schema::table('agent_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_profiles', 'sprite_path')) {
                $table->string('sprite_path')->nullable()->after('guardrails');
            }

            if (! Schema::hasColumn('agent_profiles', 'default_state')) {
                $table->string('default_state')->default('idle')->after('sprite_path');
            }

            if (! Schema::hasColumn('agent_profiles', 'voice_settings')) {
                $table->json('voice_settings')->nullable()->after('default_state');
            }

            if (! Schema::hasColumn('agent_profiles', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_fixed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            foreach (['sprite_path', 'default_state', 'voice_settings', 'is_active'] as $column) {
                if (Schema::hasColumn('agent_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('learners', function (Blueprint $table) {
            if (Schema::hasColumn('learners', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('module_attempts', function (Blueprint $table) {
            $table->dropIndex(['is_sandbox', 'module_id']);
            if (Schema::hasColumn('module_attempts', 'is_sandbox')) {
                $table->dropColumn('is_sandbox');
            }
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropIndex(['is_sandbox', 'attempt_type']);
            if (Schema::hasColumn('assessment_attempts', 'is_sandbox')) {
                $table->dropColumn('is_sandbox');
            }
        });
    }
};
