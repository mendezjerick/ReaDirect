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
            'decision_reason' => $this->decisionReason($moduleKey, $crlaClassification, $readingClassification),
            'crla_meaning' => $this->crlaMeaning($crlaClassification),
            'reading_meaning' => $this->readingMeaning($readingClassification),
            'placement_explanation' => $this->placementExplanation($moduleKey, $crlaClassification, $readingClassification),
            'matched_condition' => $this->matchedCondition($moduleKey, $crlaClassification, $readingClassification),
        ];
    }

    public function crlaSummary(string $crlaClassification): array
    {
        $moduleKey = in_array($crlaClassification, [
            CrlaScoringService::FULL_REFRESHER,
            CrlaScoringService::MODERATE_REFRESHER,
            CrlaScoringService::LIGHT_REFRESHER,
        ], true) ? 'module_1' : null;

        return [
            'module_key' => $moduleKey,
            'decision' => $moduleKey ? 'assign_module_1_preview' : 'reading_check_required',
            'rule_applied' => 'CRLA_MODULE_PREVIEW_V1',
            'crla_meaning' => $this->crlaMeaning($crlaClassification),
            'decision_reason' => $moduleKey
                ? 'Your CRLA score places you in Module 1. The passage reading check still runs next so we can record your reading level before your dashboard opens.'
                : 'Your CRLA score is Grade Ready, so the passage and comprehension check will decide whether you start in Module 2, Module 3, or no module.',
        ];
    }

    private function crlaMeaning(string $classification): string
    {
        return match ($classification) {
            CrlaScoringService::FULL_REFRESHER => 'Needs the most support with early letter, rhyme, and word reading skills.',
            CrlaScoringService::MODERATE_REFRESHER => 'Shows some early reading skills, but still needs focused practice before moving into longer reading.',
            CrlaScoringService::LIGHT_REFRESHER => 'Has many early reading skills in place and needs a lighter review before moving ahead.',
            CrlaScoringService::GRADE_READY => 'Early reading skills look ready, so the passage check becomes the main placement signal.',
            default => 'Classification meaning is not available for this score.',
        };
    }

    private function readingMeaning(string $classification): string
    {
        return match ($classification) {
            ReadingComprehensionScoringService::LOW_EMERGING => 'Low Emerging means the passage score shows early reading is still developing and needs substantial support.',
            ReadingComprehensionScoringService::HIGH_EMERGING => 'High Emerging means the learner is building passage reading skills but still needs guided word and sentence practice.',
            ReadingComprehensionScoringService::DEVELOPING => 'Developing means the learner can read some connected text and needs sentence and passage practice to improve accuracy and comprehension.',
            ReadingComprehensionScoringService::TRANSITIONING => 'Transitioning means the learner is close to grade-level reading and needs targeted practice with longer connected text.',
            ReadingComprehensionScoringService::GRADE_LEVEL => 'Reading at Grade Level means the learner met the diagnostic reading target and does not need a module right now.',
            default => 'Reading level meaning is not available for this score.',
        };
    }

    private function decisionReason(?string $moduleKey, string $crlaClassification, string $readingClassification): string
    {
        if ($moduleKey === 'module_1') {
            return "CRLA is {$crlaClassification}, so Module 1 is assigned for foundational reading practice.";
        }

        if ($moduleKey === 'module_2') {
            return "CRLA is Grade Ready, but reading is {$readingClassification}, so Module 2 is assigned for word and sentence support.";
        }

        if ($moduleKey === 'module_3') {
            return "CRLA is Grade Ready and reading is {$readingClassification}, so Module 3 is assigned for connected text practice.";
        }

        return 'CRLA and passage reading are both grade ready, so no module is needed right now.';
    }

    private function placementExplanation(?string $moduleKey, string $crlaClassification, string $readingClassification): string
    {
        return match ($moduleKey) {
            'module_1' => "The CRLA result ({$crlaClassification}) takes priority because it shows foundational reading skills need review before module 2 or 3 work.",
            'module_2' => "The CRLA result is Grade Ready, but the reading level ({$readingClassification}) shows the learner still needs supported practice before longer passages.",
            'module_3' => "The CRLA result is Grade Ready and the reading level ({$readingClassification}) points to practice with sentence and passage reading.",
            default => 'The learner met both the early reading and passage reading targets, so the path goes back to the dashboard without assigning a module.',
        };
    }

    private function matchedCondition(?string $moduleKey, string $crlaClassification, string $readingClassification): string
    {
        return match ($moduleKey) {
            'module_1' => 'CRLA classification is Full Refresher, Moderate Refresher, or Light Refresher.',
            'module_2' => 'CRLA classification is Grade Ready and reading classification is Low Emerging Reader or High Emerging Reader.',
            'module_3' => 'CRLA classification is Grade Ready and reading classification is Developing Reader or Transitioning Reader.',
            default => 'CRLA classification is Grade Ready and reading classification is Reading at Grade Level.',
        };
    }
}
