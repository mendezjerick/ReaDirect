<?php

namespace App\Services;

class ModulePlacementService
{
    public function place(string $crlaClassification, string $readingClassification): array
    {
        $moduleKey = match (true) {
            in_array($crlaClassification, [
                CrlaScoringService::FULL_REFRESHER,
                CrlaScoringService::MODERATE_REFRESHER,
                CrlaScoringService::LIGHT_REFRESHER,
            ], true) => 'module_1',
            $crlaClassification === CrlaScoringService::GRADE_READY
                && in_array($readingClassification, [
                    ReadingComprehensionScoringService::LOW_EMERGING,
                    ReadingComprehensionScoringService::HIGH_EMERGING,
                ], true) => 'module_2',
            $crlaClassification === CrlaScoringService::GRADE_READY
                && in_array($readingClassification, [
                    ReadingComprehensionScoringService::DEVELOPING,
                    ReadingComprehensionScoringService::TRANSITIONING,
                ], true) => 'module_3',
            $crlaClassification === CrlaScoringService::GRADE_READY
                && $readingClassification === ReadingComprehensionScoringService::GRADE_LEVEL => null,
            default => throw new \InvalidArgumentException('Unknown classification combination.'),
        };

        return [
            'module_key' => $moduleKey,
            'decision' => $moduleKey ? 'assign_'.$moduleKey : 'no_module_needed',
            'rule_applied' => 'MODULE_PLACEMENT_V1',
            'decision_reason' => "CRLA: {$crlaClassification}; Reading: {$readingClassification}.",
        ];
    }
}
