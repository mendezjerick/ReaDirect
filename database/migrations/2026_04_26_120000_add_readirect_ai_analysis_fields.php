<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_files', function (Blueprint $table) {
            foreach ($this->audioColumns() as $name => $definition) {
                if (! Schema::hasColumn('audio_files', $name)) {
                    $definition($table);
                }
            }
        });

        foreach (['assessment_task_responses', 'module_activity_responses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach ($this->responseColumns() as $name => $definition) {
                    if (! Schema::hasColumn($tableName, $name)) {
                        $definition($table);
                    }
                }
            });
        }

        Schema::table('learning_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_contents', 'enrichment_metadata')) {
                $table->json('enrichment_metadata')->nullable()->after('accepted_answers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_contents', function (Blueprint $table) {
            if (Schema::hasColumn('learning_contents', 'enrichment_metadata')) {
                $table->dropColumn('enrichment_metadata');
            }
        });

        foreach (['module_activity_responses', 'assessment_task_responses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = array_keys($this->responseColumns());
                $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn($tableName, $column)));

                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }

        Schema::table('audio_files', function (Blueprint $table) {
            $columns = array_keys($this->audioColumns());
            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('audio_files', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }

    private function audioColumns(): array
    {
        return [
            'ai_provider' => fn (Blueprint $table) => $table->string('ai_provider')->nullable()->after('stt_completed_at'),
            'ai_model' => fn (Blueprint $table) => $table->string('ai_model')->nullable()->after('ai_provider'),
            'ai_request_id' => fn (Blueprint $table) => $table->string('ai_request_id')->nullable()->after('ai_model'),
            'ai_transcript' => fn (Blueprint $table) => $table->text('ai_transcript')->nullable()->after('ai_request_id'),
            'ai_normalized_transcript' => fn (Blueprint $table) => $table->text('ai_normalized_transcript')->nullable()->after('ai_transcript'),
            'ai_confidence' => fn (Blueprint $table) => $table->float('ai_confidence')->nullable()->after('ai_normalized_transcript'),
            'ai_error' => fn (Blueprint $table) => $table->text('ai_error')->nullable()->after('ai_confidence'),
            'ai_warnings' => fn (Blueprint $table) => $table->json('ai_warnings')->nullable()->after('ai_error'),
            'ai_completed_at' => fn (Blueprint $table) => $table->timestamp('ai_completed_at')->nullable()->after('ai_warnings'),
        ];
    }

    private function responseColumns(): array
    {
        return [
            'ai_transcript' => fn (Blueprint $table) => $table->text('ai_transcript')->nullable()->after('stt_confidence'),
            'ai_normalized_transcript' => fn (Blueprint $table) => $table->text('ai_normalized_transcript')->nullable()->after('ai_transcript'),
            'ai_similarity_label' => fn (Blueprint $table) => $table->string('ai_similarity_label')->nullable()->after('ai_normalized_transcript'),
            'ai_character_similarity' => fn (Blueprint $table) => $table->float('ai_character_similarity')->nullable()->after('ai_similarity_label'),
            'ai_token_similarity' => fn (Blueprint $table) => $table->float('ai_token_similarity')->nullable()->after('ai_character_similarity'),
            'ai_expected_phonemes' => fn (Blueprint $table) => $table->json('ai_expected_phonemes')->nullable()->after('ai_token_similarity'),
            'ai_actual_phonemes' => fn (Blueprint $table) => $table->json('ai_actual_phonemes')->nullable()->after('ai_expected_phonemes'),
            'ai_phoneme_similarity' => fn (Blueprint $table) => $table->float('ai_phoneme_similarity')->nullable()->after('ai_actual_phonemes'),
            'ai_error_type' => fn (Blueprint $table) => $table->string('ai_error_type')->nullable()->after('ai_phoneme_similarity'),
            'ai_error_position' => fn (Blueprint $table) => $table->string('ai_error_position')->nullable()->after('ai_error_type'),
            'ai_feedback_hint' => fn (Blueprint $table) => $table->string('ai_feedback_hint')->nullable()->after('ai_error_position'),
            'ai_coach_hint_key' => fn (Blueprint $table) => $table->string('ai_coach_hint_key')->nullable()->after('ai_feedback_hint'),
            'ai_skill_signal' => fn (Blueprint $table) => $table->string('ai_skill_signal')->nullable()->after('ai_coach_hint_key'),
            'ai_target_phoneme' => fn (Blueprint $table) => $table->string('ai_target_phoneme')->nullable()->after('ai_skill_signal'),
            'ai_recommended_practice_focus' => fn (Blueprint $table) => $table->string('ai_recommended_practice_focus')->nullable()->after('ai_target_phoneme'),
            'ai_response' => fn (Blueprint $table) => $table->json('ai_response')->nullable()->after('ai_recommended_practice_focus'),
            'ai_analyzed_at' => fn (Blueprint $table) => $table->timestamp('ai_analyzed_at')->nullable()->after('ai_response'),
        ];
    }
};
