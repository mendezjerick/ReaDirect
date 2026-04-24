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

    private function moduleOne(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('move_to_module_2', 'module_2', 'MODULE_1_MASTERY_V1', $score, 'Great work! You are ready for Module 2.'),
            $score >= 60 => $this->result('repeat_module_1', 'module_1', 'MODULE_1_MASTERY_V1', $score, 'Great effort! Let us practice Module 1 once more.'),
            default => $this->result('extra_phoneme_drills', 'module_1', 'MODULE_1_MASTERY_V1', $score, 'Great effort! Extra sound practice will help before trying again.'),
        };
    }

    private function moduleTwo(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('move_to_module_3', 'module_3', 'MODULE_2_MASTERY_V1', $score, 'Great work! You are ready for Module 3.'),
            $score >= 60 => $this->result('repeat_module_2', 'module_2', 'MODULE_2_MASTERY_V1', $score, 'Great effort! Let us practice Module 2 once more.'),
            default => $this->result('return_to_module_1', 'module_1', 'MODULE_2_MASTERY_V1', $score, 'Great effort! More letter and sound practice will help.'),
        };
    }

    private function moduleThree(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('proceed_to_reassessment', null, 'MODULE_3_MASTERY_V1', $score, 'Great work! You are ready for the final reassessment placeholder.'),
            $score >= 70 => $this->result('repeat_module_3', 'module_3', 'MODULE_3_MASTERY_V1', $score, 'Great effort! Let us practice fluency once more.'),
            default => $this->result('return_to_module_2', 'module_2', 'MODULE_3_MASTERY_V1', $score, 'Great effort! More word reading practice will help.'),
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
