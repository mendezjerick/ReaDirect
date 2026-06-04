<?php

namespace App\Services;

class ModuleMasteryService
{
    public function decide(string $moduleKey, float|int $score): array
    {
        if ($score < 0 || $score > 100) {
            throw new \InvalidArgumentException('Module score must be between 0 and 100.');
        }

        return match ($moduleKey) {
            'module_1' => $this->moduleOne((float) $score),
            'module_2' => $this->moduleTwo((float) $score),
            'module_3' => $this->moduleThree((float) $score),
            default => throw new \InvalidArgumentException('Unknown module key.'),
        };
    }

    public function ruleTable(): array
    {
        return [
            [
                'module' => 'Module 1',
                'score_range' => '90-100',
                'decision' => 'move_to_module_2',
                'next' => 'Module 2',
                'rule_applied' => 'MODULE_1_MASTERY_V1',
            ],
            [
                'module' => 'Module 1',
                'score_range' => '60-89',
                'decision' => 'repeat_module_1',
                'next' => 'Module 1',
                'rule_applied' => 'MODULE_1_MASTERY_V1',
            ],
            [
                'module' => 'Module 1',
                'score_range' => '0-59',
                'decision' => 'extra_phoneme_drills',
                'next' => 'Module 1',
                'rule_applied' => 'MODULE_1_MASTERY_V1',
            ],
            [
                'module' => 'Module 2',
                'score_range' => '90-100',
                'decision' => 'move_to_module_3',
                'next' => 'Module 3',
                'rule_applied' => 'MODULE_2_MASTERY_V1',
            ],
            [
                'module' => 'Module 2',
                'score_range' => '60-89',
                'decision' => 'repeat_module_2',
                'next' => 'Module 2',
                'rule_applied' => 'MODULE_2_MASTERY_V1',
            ],
            [
                'module' => 'Module 2',
                'score_range' => '0-59',
                'decision' => 'return_to_module_1',
                'next' => 'Module 1',
                'rule_applied' => 'MODULE_2_MASTERY_V1',
            ],
            [
                'module' => 'Module 3',
                'score_range' => '90-100',
                'decision' => 'proceed_to_reassessment',
                'next' => 'Final reassessment',
                'rule_applied' => 'MODULE_3_MASTERY_V1',
            ],
            [
                'module' => 'Module 3',
                'score_range' => '70-89',
                'decision' => 'repeat_module_3',
                'next' => 'Module 3',
                'rule_applied' => 'MODULE_3_MASTERY_V1',
            ],
            [
                'module' => 'Module 3',
                'score_range' => '0-69',
                'decision' => 'return_to_module_2',
                'next' => 'Module 2',
                'rule_applied' => 'MODULE_3_MASTERY_V1',
            ],
        ];
    }

    private function moduleOne(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('move_to_module_2', 'module_2', 'MODULE_1_MASTERY_V1', $score, 'Great work! You are ready for Module 2.'),
            $score >= 60 => $this->result('repeat_module_1', 'module_1', 'MODULE_1_MASTERY_V1', $score, 'You are doing better. Let us practice Module 1 again to make your sounds stronger.'),
            default => $this->result('extra_phoneme_drills', 'module_1', 'MODULE_1_MASTERY_V1', $score, 'Let us practice some sounds first. These drills will help you before trying again.'),
        };
    }

    private function moduleTwo(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('move_to_module_3', 'module_3', 'MODULE_2_MASTERY_V1', $score, 'Great work! You are ready for Module 3.'),
            $score >= 60 => $this->result('repeat_module_2', 'module_2', 'MODULE_2_MASTERY_V1', $score, 'Let us practice these words again so you can feel more confident.'),
            default => $this->result('return_to_module_1', 'module_1', 'MODULE_2_MASTERY_V1', $score, 'We will go back to letter sounds for more practice. This will help your word reading.'),
        };
    }

    private function moduleThree(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('proceed_to_reassessment', null, 'MODULE_3_MASTERY_V1', $score, 'You worked hard in your modules. Do your best on your final reading check!'),
            $score >= 70 => $this->result('repeat_module_3', 'module_3', 'MODULE_3_MASTERY_V1', $score, 'Let us practice sentence reading again so you can read more smoothly.'),
            default => $this->result('return_to_module_2', 'module_2', 'MODULE_3_MASTERY_V1', $score, 'We will practice words again to help your sentence reading become stronger.'),
        };
    }

    private function result(string $decision, ?string $nextModuleKey, string $rule, float $score, string $message): array
    {
        return [
            'decision' => $decision,
            'decision_key' => $decision,
            'next_module_key' => $nextModuleKey,
            'next_module_id' => null,
            'user_friendly_message' => $message,
            'rule_applied' => $rule,
            'score' => $score,
        ];
    }
}
