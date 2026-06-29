<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_voice_lines', function (Blueprint $table): void {
            $table->text('synthesis_text')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('generated_voice_lines', function (Blueprint $table): void {
            $table->dropColumn('synthesis_text');
        });
    }
};
