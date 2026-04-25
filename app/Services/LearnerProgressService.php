<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use App\Models\Recommendation;

class LearnerProgressService
{
    public function detailFor(Learner $learner): array
    {
        $latestDiagnostic = AssessmentAttempt::with('assignedModule')
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->latest()
            ->first();
        $moduleAttempts = ModuleAttempt::with(['module', 'responses'])
            ->where('learner_id', $learner->id)
            ->latest()
            ->get();
        $latestRecommendation = Recommendation::with('recommendedModule')
            ->where('learner_id', $learner->id)
            ->latest()
            ->first();

        return [
            'learner' => $this->learnerArray($learner),
            'latestDiagnosticAttempt' => $latestDiagnostic?->only([
                'public_id', 'status', 'task_1_score', 'task_2a_score', 'task_2b_score',
                'crla_total_score', 'crla_classification', 'reading_accuracy',
                'incorrect_words', 'comprehension_correct_count', 'comprehension_percentage',
                'final_reading_score', 'reading_classification', 'placement_decision',
                'rule_applied', 'decision_reason', 'completed_at',
            ]),
            'diagnosticSummary' => $latestDiagnostic ? $this->diagnosticSummary($latestDiagnostic) : null,
            'readingSummary' => $latestDiagnostic ? $this->readingSummary($latestDiagnostic) : null,
            'moduleProgress' => $this->moduleProgress($moduleAttempts),
            'latestRecommendation' => $latestRecommendation ? [
                'decision' => $latestRecommendation->decision,
                'reason' => $latestRecommendation->decision_reason,
                'rule_applied' => $latestRecommendation->rule_applied,
                'module' => $latestRecommendation->recommendedModule?->title,
            ] : null,
            'recentActivity' => $this->timeline($latestDiagnostic, $moduleAttempts),
        ];
    }

    public function assessmentReview(AssessmentAttempt $attempt): array
    {
        $responses = $attempt->responses()->with('audioFile')->orderBy('item_number')->get();

        return [
            'assessmentAttempt' => $attempt->load('assignedModule')->only([
                'public_id', 'attempt_type', 'status', 'started_at', 'completed_at',
                'rule_applied', 'decision_reason', 'placement_decision',
            ]),
            'task1Responses' => $this->responseRows($responses->where('task_type', 'task_1_letter')),
            'task2aResponses' => $this->responseRows($responses->where('task_type', 'task_2a_rhyme')),
            'task2bResponses' => $this->responseRows($responses->where('task_type', 'task_2b_word_sentence')),
            'passageResult' => [
                'incorrect_words' => $attempt->incorrect_words,
                'reading_accuracy' => $attempt->reading_accuracy,
            ],
            'comprehensionResponses' => $this->responseRows($responses->where('task_type', 'comprehension_question')),
            'scoringSummary' => $this->diagnosticSummary($attempt) + $this->readingSummary($attempt),
            'placementDecision' => [
                'decision' => $attempt->placement_decision,
                'module' => $attempt->assignedModule?->title,
                'rule_applied' => $attempt->rule_applied,
                'reason' => $attempt->decision_reason,
            ],
        ];
    }

    public function moduleReview(Learner $learner): array
    {
        $attempts = ModuleAttempt::with(['module', 'responses.moduleAttemptItem', 'responses.audioFile'])
            ->where('learner_id', $learner->id)
            ->latest()
            ->get();

        return [
            'learner' => $this->learnerArray($learner),
            'moduleAttempts' => $attempts->map(fn (ModuleAttempt $attempt) => [
                'public_id' => $attempt->public_id,
                'module' => $attempt->module?->title,
                'status' => $attempt->status,
                'score' => $attempt->score,
                'mastery_decision' => $attempt->mastery_decision,
                'rule_applied' => $attempt->rule_applied,
                'decision_reason' => $attempt->decision_reason,
                'started_at' => $attempt->started_at?->toDateTimeString(),
                'completed_at' => $attempt->completed_at?->toDateTimeString(),
                'activity_types' => $attempt->responses->pluck('moduleAttemptItem.activity_type')->filter()->unique()->values()->all(),
                'correct_count' => $attempt->responses->where('is_correct', true)->count(),
                'incorrect_count' => $attempt->responses->where('is_correct', false)->count(),
                'responses' => $attempt->responses->map(fn ($response) => [
                    'activity_type' => $response->moduleAttemptItem?->activity_type,
                    'prompt' => $response->moduleAttemptItem?->prompt_snapshot['prompt'] ?? null,
                    'answer' => $response->learner_answer ?? $response->response_text,
                    'learner_transcript' => $response->learner_transcript ?? $response->learner_answer ?? $response->response_text,
                    'expected_answer' => $response->expected_answer,
                    'transcript_source' => $response->transcript_source ?? 'manual',
                    'stt_confidence' => $response->stt_confidence,
                    'is_correct' => $response->is_correct,
                    'score' => $response->score,
                    'feedback_text' => $response->feedback_text,
                    'retry_count' => $response->retry_count,
                    'is_mastery_item' => $response->is_mastery_item,
                    'audio_status' => $response->audioFile ? 'Audio saved' : 'No audio',
                    'audio' => $this->audioArray($response->audioFile),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function learnerArray(Learner $learner): array
    {
        return [
            'public_id' => $learner->public_id,
            'learner_code' => $learner->learner_code,
            'name' => trim($learner->first_name.' '.$learner->last_name),
            'first_name' => $learner->first_name,
            'last_name' => $learner->last_name,
            'class' => $learner->schoolClass?->name,
            'grade_level' => $learner->grade_level,
            'current_stage' => $learner->current_stage,
            'current_module' => $learner->currentModule?->title,
        ];
    }

    private function diagnosticSummary(AssessmentAttempt $attempt): array
    {
        return [
            'task_1_score' => $attempt->task_1_score,
            'task_2a_score' => $attempt->task_2a_score,
            'task_2b_score' => $attempt->task_2b_score,
            'crla_total_score' => $attempt->crla_total_score,
            'crla_classification' => $attempt->crla_classification,
        ];
    }

    private function readingSummary(AssessmentAttempt $attempt): array
    {
        return [
            'incorrect_words' => $attempt->incorrect_words,
            'reading_accuracy' => $attempt->reading_accuracy,
            'comprehension_correct_count' => $attempt->comprehension_correct_count,
            'comprehension_percentage' => $attempt->comprehension_percentage,
            'final_reading_score' => $attempt->final_reading_score,
            'reading_classification' => $attempt->reading_classification,
            'classification_note' => 'Reading classification is based only on final_reading_score.',
        ];
    }

    private function moduleProgress($moduleAttempts): array
    {
        return $moduleAttempts->map(fn (ModuleAttempt $attempt) => [
            'module' => $attempt->module?->title,
            'status' => $attempt->status,
            'score' => $attempt->score,
            'mastery_decision' => $attempt->mastery_decision,
            'rule_applied' => $attempt->rule_applied,
            'completed_at' => $attempt->completed_at?->toDateTimeString(),
        ])->values()->all();
    }

    private function timeline(?AssessmentAttempt $diagnostic, $moduleAttempts): array
    {
        $items = collect();

        if ($diagnostic) {
            $items->push([
                'date' => $diagnostic->updated_at?->toDateTimeString(),
                'label' => 'Diagnostic '.$diagnostic->status,
                'detail' => $diagnostic->crla_classification,
            ]);
        }

        foreach ($moduleAttempts as $attempt) {
            $items->push([
                'date' => $attempt->updated_at?->toDateTimeString(),
                'label' => ($attempt->module?->title ?? 'Module').' '.$attempt->status,
                'detail' => $attempt->mastery_decision,
            ]);
        }

        return $items->sortByDesc('date')->values()->all();
    }

    private function responseRows($responses): array
    {
        return $responses->map(fn ($response) => [
            'item' => $response->item_number,
            'prompt' => $response->prompt,
            'expected_answer' => $response->expected_answer,
            'answer' => $response->learner_transcript ?? $response->selected_answer ?? $response->response_text,
            'transcript_source' => $response->transcript_source ?? 'manual',
            'stt_confidence' => $response->stt_confidence,
            'is_correct' => $response->is_correct,
            'score' => $response->score,
            'error_type' => $response->error_type,
            'rule_applied' => $response->rule_applied,
            'audio_status' => $response->audioFile ? 'Audio saved' : 'No audio',
            'audio' => $this->audioArray($response->audioFile),
        ])->values()->all();
    }

    private function audioArray($audioFile): ?array
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
            'transcript' => $audioFile->transcript,
            'stt_confidence' => $audioFile->stt_confidence,
            'stt_completed_at' => $audioFile->stt_completed_at?->toDateTimeString(),
            'stt_error' => $audioFile->stt_error,
            'play_url' => route('teacher.audio.play', $audioFile),
            'transcript_update_url' => route('teacher.audio.transcript.update', $audioFile),
        ];
    }
}
