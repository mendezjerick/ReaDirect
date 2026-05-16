<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->textColumn('assessment_task_responses', 'prompt');
        $this->textColumn('assessment_task_responses', 'expected_answer');
        $this->textColumn('module_activity_responses', 'expected_answer');
    }

    public function down(): void
    {
        // Intentionally not shrinking these columns; passage and module prompts
        // can exceed 255 characters, and narrowing them would risk truncation.
    }

    private function textColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE TEXT"),
            'mysql', 'mariadb' => DB::statement("ALTER TABLE {$table} MODIFY {$column} TEXT NULL"),
            default => null,
        };
    }
};
