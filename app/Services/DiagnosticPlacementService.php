<?php

namespace App\Services;

use App\Models\AgentProfile;
use App\Models\AssessmentAttempt;
use App\Models\Module;
use App\Models\Recommendation;
use App\Support\LearnerStage;
use Illuminate\Support\Facades\DB;

class DiagnosticPlacementService
{
    public function __construct(private readonly ModulePlacementService $placement)
    {
    }

    public function completePlacement(AssessmentAttempt $attempt): array
    {
        return DB::transaction(function () use ($attempt): array {
            $decision = $this->placement->place(
                (string) $attempt->crla_classification,
                (string) $attempt->reading_classification
            );
            $module = $decision['module_key'] ? Module::where('key', $decision['module_key'])->firstOrFail() : null;

            $attempt->update([
                'assigned_module_id' => $module?->id,
                'placement_decision' => $decision['decision'],
                'status' => 'module_placement_completed',
                'completed_at' => now(),
            ]);

            Recommendation::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'recommendation_type' => 'module_placement'],
                [
                    'learner_id' => $attempt->learner_id,
                    'module_id' => $module?->id,
                    'recommended_module_id' => $module?->id,
                    'source_type' => 'diagnostic_assessment',
                    'source_id' => $attempt->id,
                    'decision' => $decision['decision'],
                    'rule_applied' => $decision['rule_applied'],
                    'generated_by' => AgentProfile::EVALUATOR_RECOMMENDATION,
                    'decision_reason' => $decision['decision_reason'],
                    'input_scores' => [
                        'crla_classification' => $attempt->crla_classification,
                        'reading_classification' => $attempt->reading_classification,
                        'final_reading_score' => $attempt->final_reading_score,
                    ],
                ]
            );

            $attempt->learner->update([
                'current_module_id' => $module?->id,
                'current_stage' => $module ? LearnerStage::MODULE_ASSIGNED : LearnerStage::GRADE_READY,
            ]);

            return [
                'decision' => $decision,
                'module' => $module,
                'attempt' => $attempt->fresh(['assignedModule', 'learner']),
            ];
        });
    }
}
