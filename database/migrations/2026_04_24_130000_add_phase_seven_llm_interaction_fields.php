<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_interactions', function (Blueprint $table) {
            if (! Schema::hasColumn('llm_interactions', 'source_type')) {
                $table->string('source_type')->nullable()->after('learner_id');
            }

            if (! Schema::hasColumn('llm_interactions', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            if (! Schema::hasColumn('llm_interactions', 'input_summary')) {
                $table->json('input_summary')->nullable()->after('model');
            }

            if (! Schema::hasColumn('llm_interactions', 'output_text')) {
                $table->text('output_text')->nullable()->after('input_summary');
            }

            if (! Schema::hasColumn('llm_interactions', 'fallback_used')) {
                $table->boolean('fallback_used')->default(false)->after('output_text');
            }

            if (! Schema::hasColumn('llm_interactions', 'safety_status')) {
                $table->string('safety_status')->nullable()->after('fallback_used');
            }

            if (! Schema::hasColumn('llm_interactions', 'error_message')) {
                $table->string('error_message', 500)->nullable()->after('safety_status');
            }

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('llm_interactions', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);

            foreach (['error_message', 'safety_status', 'fallback_used', 'output_text', 'input_summary', 'source_id', 'source_type'] as $column) {
                if (Schema::hasColumn('llm_interactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
