<?php

namespace App\Services\Admin;

use App\Models\AssessmentAttempt;
use App\Models\AudioFile;
use App\Models\LlmInteraction;
use App\Models\ModuleAttempt;
use App\Services\AnswerMatchingService;
use App\Services\Scoring\AnswerSimilarityService;
use App\Services\STT\TranscriptSanitizer;

class AdminDebugDataService
{
    public function assessment(AssessmentAttempt $attempt): array
    {
        $matching = app(AnswerMatchingService::class);
        $similarity = app(AnswerSimilarityService::class);
        $sanitizer = app(TranscriptSanitizer::class);

        return [
            'attempt' => $attempt->only(['public_id', 'attempt_type', 'status', 'is_sandbox', 'task_1_score', 'task_2a_score', 'task_2b_score', 'crla_total_score', 'reading_accuracy', 'final_reading_score', 'rule_applied']),
            'learner' => $attempt->learner?->only(['public_id', 'learner_code', 'first_name', 'last_name']),
            'items' => $attempt->selectedItems()->orderBy('sequence')->get()->map(function ($item) use ($attempt, $matching, $similarity, $sanitizer) {
                $response = $attempt->responses()->where('assessment_attempt_item_id', $item->id)->with('audioFile')->first();
                $expected = $response?->expected_answer ?? ($item->prompt_snapshot['payload']['expected_answer'] ?? $item->prompt_snapshot['prompt'] ?? '');
                $answer = $response?->learner_transcript ?? $response?->response_text ?? '';

                return [
                    'route_state' => request()->path(),
                    'task_type' => $item->task_type,
                    'sequence' => $item->sequence,
                    'item_bank_id' => $item->source_csv_id,
                    'selected_item_id' => $item->id,
                    'prompt_snapshot' => $item->prompt_snapshot,
                    'expected_answer' => $expected,
                    'accepted_answers' => $item->prompt_snapshot['accepted_answers'] ?? [],
                    'learner_answer' => $answer,
                    'raw_stt_transcript' => $response?->audioFile?->transcript,
                    'normalized_transcript' => $sanitizer->sanitize($answer),
                    'transcript_source' => $response?->transcript_source,
                    'stt_confidence' => $response?->stt_confidence ?? $response?->audioFile?->stt_confidence,
                    'stt_provider' => $response?->audioFile?->metadata['stt_provider'] ?? config('stt.provider'),
                    'stt_error' => $response?->audioFile?->stt_error,
                    'ai_provider' => $response?->audioFile?->ai_provider,
                    'ai_model' => $response?->audioFile?->ai_model,
                    'ai_asr_route' => data_get($response?->ai_response, 'asr_route'),
                    'ai_model_family' => data_get($response?->ai_response, 'model_family'),
                    'ai_model_used' => data_get($response?->ai_response, 'model_used'),
                    'ai_prompt_type' => data_get($response?->ai_response, 'prompt_type'),
                    'ai_transcript' => $response?->ai_transcript ?? $response?->audioFile?->ai_transcript,
                    'ai_normalized_transcript' => $response?->ai_normalized_transcript ?? $response?->audioFile?->ai_normalized_transcript,
                    'ai_raw_transcript' => data_get($response?->ai_response, 'raw_transcript', $response?->ai_transcript ?? $response?->audioFile?->ai_transcript),
                    'ai_wav2vec2_transcript' => data_get($response?->ai_response, 'wav2vec2_transcript'),
                    'ai_corrected_transcript' => data_get($response?->ai_response, 'corrected_transcript', $response?->ai_normalized_transcript ?? $response?->audioFile?->ai_normalized_transcript),
                    'ai_displayed_transcript' => data_get($response?->ai_response, 'displayed_transcript', $response?->response_text),
                    'ai_raw_wer' => data_get($response?->ai_response, 'raw_wer'),
                    'ai_corrected_wer' => data_get($response?->ai_response, 'corrected_wer'),
                    'ai_raw_cer' => data_get($response?->ai_response, 'raw_cer'),
                    'ai_corrected_cer' => data_get($response?->ai_response, 'corrected_cer'),
                    'ai_accepted' => data_get($response?->ai_response, 'accepted'),
                    'ai_expected_phonemes' => data_get($response?->ai_response, 'expected_phonemes'),
                    'ai_observed_phonemes' => data_get($response?->ai_response, 'observed_phonemes'),
                    'ai_phonetic_similarity_score' => data_get($response?->ai_response, 'phonetic_similarity_score'),
                    'ai_composite_score' => data_get($response?->ai_response, 'composite_score'),
                    'ai_normalization_applied' => data_get($response?->ai_response, 'normalization_applied'),
                    'ai_normalization_reason' => data_get($response?->ai_response, 'normalization_reason'),
                    'ai_correction_strategy_used' => data_get($response?->ai_response, 'correction_strategy_used'),
                    'ai_accepted_by_exact_match' => data_get($response?->ai_response, 'accepted_by_exact_match'),
                    'ai_accepted_by_letter_alias' => data_get($response?->ai_response, 'accepted_by_letter_alias', data_get($response?->ai_response, 'accepted_by_letter_normalization')),
                    'ai_accepted_by_letter_lattice' => data_get($response?->ai_response, 'accepted_by_letter_lattice'),
                    'ai_accepted_by_vowel_tail' => data_get($response?->ai_response, 'accepted_by_vowel_tail'),
                    'ai_accepted_by_known_confusion' => data_get($response?->ai_response, 'accepted_by_known_confusion'),
                    'ai_accepted_by_phonetic_threshold' => data_get($response?->ai_response, 'accepted_by_phonetic_threshold'),
                    'ai_accepted_by_phoneme_evidence' => data_get($response?->ai_response, 'accepted_by_phoneme_evidence'),
                    'ai_critical_phoneme' => data_get($response?->ai_response, 'critical_phoneme'),
                    'ai_critical_phoneme_detected' => data_get($response?->ai_response, 'critical_phoneme_detected'),
                    'ai_threshold_used' => data_get($response?->ai_response, 'threshold_used'),
                    'ai_similarity_label' => $response?->ai_similarity_label,
                    'ai_character_similarity' => $response?->ai_character_similarity,
                    'ai_phoneme_similarity' => $response?->ai_phoneme_similarity,
                    'ai_error_type' => $response?->ai_error_type,
                    'ai_feedback_hint' => $response?->ai_feedback_hint,
                    'ai_skill_signal' => $response?->ai_skill_signal,
                    'ai_target_phoneme' => $response?->ai_target_phoneme,
                    'ai_warnings' => $response?->audioFile?->ai_warnings,
                    'ai_error' => $response?->audioFile?->ai_error,
                    'ai_response' => config('readirect_ai.debug.show_admin_debug') ? $response?->ai_response : null,
                    'answer_matching_result' => $answer !== '' ? $matching->isAcceptedAnswer($answer, $item->prompt_snapshot['accepted_answers'] ?? []) : null,
                    'similarity_percentage' => $answer !== '' ? $similarity->similarityPercentage((string) $expected, (string) $answer) : null,
                    'similarity_label' => $answer !== '' ? $similarity->classifySimilarity((string) $expected, (string) $answer) : 'blank',
                    'error_type' => $response?->error_type,
                    'score' => $response?->score,
                    'max_score' => 1,
                    'scoring_service_used' => 'AnswerMatchingService',
                    'rule_applied' => $response?->rule_applied,
                    'audio' => $this->audio($response?->audioFile),
                    'agent_commentary_text' => $response?->agent_commentary_text,
                    'commentary_source' => $response?->agent_commentary_source,
                    'agent_message_text' => $response?->agent_commentary_text,
                ];
            })->values()->all(),
        ];
    }

    public function module(ModuleAttempt $attempt): array
    {
        return [
            'attempt' => $attempt->only(['public_id', 'status', 'score', 'mastery_decision', 'rule_applied', 'is_sandbox']),
            'learner' => $attempt->learner?->only(['public_id', 'learner_code', 'first_name', 'last_name']),
            'module' => $attempt->module?->only(['key', 'title']),
            'responses' => $attempt->responses()->with(['moduleAttemptItem', 'audioFile'])->get()->map(fn ($response) => [
                'module_attempt_id' => $attempt->id,
                'activity_type' => $response->moduleAttemptItem?->activity_type,
                'module_attempt_item_id' => $response->module_attempt_item_id,
                'prompt_snapshot' => $response->moduleAttemptItem?->prompt_snapshot,
                'expected_answer' => $response->expected_answer,
                'accepted_answers' => $response->moduleAttemptItem?->prompt_snapshot['accepted_answers'] ?? [],
                'learner_answer' => $response->learner_answer ?? $response->response_text,
                'raw_stt_transcript' => $response->audioFile?->transcript,
                'transcript_source' => $response->transcript_source,
                'stt_confidence' => $response->stt_confidence ?? $response->audioFile?->stt_confidence,
                'ai_provider' => $response->audioFile?->ai_provider,
                'ai_model' => $response->audioFile?->ai_model,
                'ai_asr_route' => data_get($response->ai_response, 'asr_route'),
                'ai_model_family' => data_get($response->ai_response, 'model_family'),
                'ai_model_used' => data_get($response->ai_response, 'model_used'),
                'ai_prompt_type' => data_get($response->ai_response, 'prompt_type'),
                'ai_transcript' => $response->ai_transcript ?? $response->audioFile?->ai_transcript,
                'ai_normalized_transcript' => $response->ai_normalized_transcript ?? $response->audioFile?->ai_normalized_transcript,
                'ai_raw_transcript' => data_get($response->ai_response, 'raw_transcript', $response->ai_transcript ?? $response->audioFile?->ai_transcript),
                'ai_wav2vec2_transcript' => data_get($response->ai_response, 'wav2vec2_transcript'),
                'ai_corrected_transcript' => data_get($response->ai_response, 'corrected_transcript', $response->ai_normalized_transcript ?? $response->audioFile?->ai_normalized_transcript),
                'ai_displayed_transcript' => data_get($response->ai_response, 'displayed_transcript', $response->response_text),
                'ai_raw_wer' => data_get($response->ai_response, 'raw_wer'),
                'ai_corrected_wer' => data_get($response->ai_response, 'corrected_wer'),
                'ai_raw_cer' => data_get($response->ai_response, 'raw_cer'),
                'ai_corrected_cer' => data_get($response->ai_response, 'corrected_cer'),
                'ai_accepted' => data_get($response->ai_response, 'accepted'),
                'ai_expected_phonemes' => data_get($response->ai_response, 'expected_phonemes'),
                'ai_observed_phonemes' => data_get($response->ai_response, 'observed_phonemes'),
                'ai_phonetic_similarity_score' => data_get($response->ai_response, 'phonetic_similarity_score'),
                'ai_composite_score' => data_get($response->ai_response, 'composite_score'),
                'ai_normalization_applied' => data_get($response->ai_response, 'normalization_applied'),
                'ai_normalization_reason' => data_get($response->ai_response, 'normalization_reason'),
                'ai_correction_strategy_used' => data_get($response->ai_response, 'correction_strategy_used'),
                'ai_accepted_by_exact_match' => data_get($response->ai_response, 'accepted_by_exact_match'),
                'ai_accepted_by_letter_alias' => data_get($response->ai_response, 'accepted_by_letter_alias', data_get($response->ai_response, 'accepted_by_letter_normalization')),
                'ai_accepted_by_letter_lattice' => data_get($response->ai_response, 'accepted_by_letter_lattice'),
                'ai_accepted_by_vowel_tail' => data_get($response->ai_response, 'accepted_by_vowel_tail'),
                'ai_accepted_by_known_confusion' => data_get($response->ai_response, 'accepted_by_known_confusion'),
                'ai_accepted_by_phonetic_threshold' => data_get($response->ai_response, 'accepted_by_phonetic_threshold'),
                'ai_accepted_by_phoneme_evidence' => data_get($response->ai_response, 'accepted_by_phoneme_evidence'),
                'ai_critical_phoneme' => data_get($response->ai_response, 'critical_phoneme'),
                'ai_critical_phoneme_detected' => data_get($response->ai_response, 'critical_phoneme_detected'),
                'ai_threshold_used' => data_get($response->ai_response, 'threshold_used'),
                'ai_similarity_label' => $response->ai_similarity_label,
                'ai_character_similarity' => $response->ai_character_similarity,
                'ai_phoneme_similarity' => $response->ai_phoneme_similarity,
                'ai_error_type' => $response->ai_error_type,
                'ai_feedback_hint' => $response->ai_feedback_hint,
                'ai_skill_signal' => $response->ai_skill_signal,
                'ai_target_phoneme' => $response->ai_target_phoneme,
                'ai_response' => config('readirect_ai.debug.show_admin_debug') ? $response->ai_response : null,
                'score' => $response->score,
                'feedback_text' => $response->feedback_text,
                'agent_commentary' => $response->agent_commentary_text,
                'audio' => $this->audio($response->audioFile),
            ])->values()->all(),
        ];
    }

    public function stt(AudioFile $audioFile): array
    {
        $response = $audioFile->assessmentTaskResponse ?? $audioFile->moduleActivityResponse;

        return [
            'audio' => $this->audio($audioFile),
            'raw_transcript' => $audioFile->transcript,
            'normalized_transcript' => app(TranscriptSanitizer::class)->sanitize($audioFile->transcript),
            'confidence' => $audioFile->stt_confidence,
            'provider' => $audioFile->metadata['stt_provider'] ?? config('stt.provider'),
            'error' => $audioFile->stt_error,
            'ai_provider' => $audioFile->ai_provider,
            'ai_model' => $audioFile->ai_model,
            'ai_asr_route' => data_get($response?->ai_response, 'asr_route'),
            'ai_model_family' => data_get($response?->ai_response, 'model_family'),
            'ai_model_used' => data_get($response?->ai_response, 'model_used'),
            'ai_prompt_type' => data_get($response?->ai_response, 'prompt_type'),
            'ai_transcript' => $audioFile->ai_transcript,
            'ai_normalized_transcript' => $audioFile->ai_normalized_transcript,
            'ai_raw_transcript' => data_get($response?->ai_response, 'raw_transcript', $audioFile->ai_transcript),
            'ai_wav2vec2_transcript' => data_get($response?->ai_response, 'wav2vec2_transcript'),
            'ai_corrected_transcript' => data_get($response?->ai_response, 'corrected_transcript', $audioFile->ai_normalized_transcript),
            'ai_displayed_transcript' => data_get($response?->ai_response, 'displayed_transcript', $response?->response_text),
            'ai_raw_wer' => data_get($response?->ai_response, 'raw_wer'),
            'ai_corrected_wer' => data_get($response?->ai_response, 'corrected_wer'),
            'ai_raw_cer' => data_get($response?->ai_response, 'raw_cer'),
            'ai_corrected_cer' => data_get($response?->ai_response, 'corrected_cer'),
            'ai_accepted' => data_get($response?->ai_response, 'accepted'),
            'ai_expected_phonemes' => data_get($response?->ai_response, 'expected_phonemes'),
            'ai_observed_phonemes' => data_get($response?->ai_response, 'observed_phonemes'),
            'ai_phonetic_similarity_score' => data_get($response?->ai_response, 'phonetic_similarity_score'),
            'ai_composite_score' => data_get($response?->ai_response, 'composite_score'),
            'ai_normalization_applied' => data_get($response?->ai_response, 'normalization_applied'),
            'ai_normalization_reason' => data_get($response?->ai_response, 'normalization_reason'),
            'ai_correction_strategy_used' => data_get($response?->ai_response, 'correction_strategy_used'),
            'ai_accepted_by_exact_match' => data_get($response?->ai_response, 'accepted_by_exact_match'),
            'ai_accepted_by_letter_alias' => data_get($response?->ai_response, 'accepted_by_letter_alias', data_get($response?->ai_response, 'accepted_by_letter_normalization')),
            'ai_accepted_by_letter_lattice' => data_get($response?->ai_response, 'accepted_by_letter_lattice'),
            'ai_accepted_by_vowel_tail' => data_get($response?->ai_response, 'accepted_by_vowel_tail'),
            'ai_accepted_by_known_confusion' => data_get($response?->ai_response, 'accepted_by_known_confusion'),
            'ai_accepted_by_phonetic_threshold' => data_get($response?->ai_response, 'accepted_by_phonetic_threshold'),
            'ai_accepted_by_phoneme_evidence' => data_get($response?->ai_response, 'accepted_by_phoneme_evidence'),
            'ai_critical_phoneme' => data_get($response?->ai_response, 'critical_phoneme'),
            'ai_critical_phoneme_detected' => data_get($response?->ai_response, 'critical_phoneme_detected'),
            'ai_threshold_used' => data_get($response?->ai_response, 'threshold_used'),
            'ai_confidence' => $audioFile->ai_confidence,
            'ai_error' => $audioFile->ai_error,
            'ai_warnings' => $audioFile->ai_warnings,
            'transcript_source' => $response?->transcript_source,
            'expected_answer' => $response?->expected_answer,
            'accepted_answers' => $response?->metadata_json['prompt_snapshot']['accepted_answers'] ?? [],
            'score' => $response?->score,
        ];
    }

    public function llm(LlmInteraction $interaction): array
    {
        return [
            'public_id' => $interaction->public_id,
            'source_type' => $interaction->source_type,
            'source_id' => $interaction->source_id,
            'input_summary' => $interaction->input_summary,
            'model' => $interaction->model,
            'output_text' => $interaction->output_text,
            'fallback_used' => $interaction->fallback_used,
            'safety_status' => $interaction->safety_status,
            'error_message' => $interaction->error_message,
            'created_at' => $interaction->created_at?->toDateTimeString(),
        ];
    }

    private function audio(?AudioFile $audioFile): ?array
    {
        if (! $audioFile) {
            return null;
        }

        return [
            'public_id' => $audioFile->public_id,
            'mime_type' => $audioFile->mime_type,
            'file_size' => $audioFile->file_size ?? $audioFile->size_bytes,
            'duration_seconds' => $audioFile->duration_seconds,
            'recording_context' => $audioFile->recording_context,
            'safe_path' => basename((string) $audioFile->file_path),
            'play_url' => route('teacher.audio.play', $audioFile),
        ];
    }
}
