<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('learning_contents', 'prompt')) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE learning_contents ALTER COLUMN prompt TYPE TEXT'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE learning_contents MODIFY prompt TEXT NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        // Intentionally not shrinking this column; enriched reading passages can
        // exceed 255 characters, and narrowing it would truncate usable content.
    }
};
