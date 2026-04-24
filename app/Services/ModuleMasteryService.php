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
            $score >= 90 => $this->result('move_to_module_2', 'module_2', 'MODULE_1_MASTERY_V1'),
            $score >= 60 => $this->result('repeat_module_1', 'module_1', 'MODULE_1_MASTERY_V1'),
            default => $this->result('extra_phoneme_drills', 'module_1', 'MODULE_1_MASTERY_V1'),
        };
    }

    private function moduleTwo(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('move_to_module_3', 'module_3', 'MODULE_2_MASTERY_V1'),
            $score >= 60 => $this->result('repeat_module_2', 'module_2', 'MODULE_2_MASTERY_V1'),
            default => $this->result('return_to_module_1', 'module_1', 'MODULE_2_MASTERY_V1'),
        };
    }

    private function moduleThree(float $score): array
    {
        return match (true) {
            $score >= 90 => $this->result('proceed_to_reassessment', null, 'MODULE_3_MASTERY_V1'),
            $score >= 70 => $this->result('repeat_module_3', 'module_3', 'MODULE_3_MASTERY_V1'),
            default => $this->result('return_to_module_2', 'module_2', 'MODULE_3_MASTERY_V1'),
        };
    }

    private function result(string $decision, ?string $nextModuleKey, string $rule): array
    {
        return [
            'decision' => $decision,
            'next_module_key' => $nextModuleKey,
            'rule_applied' => $rule,
        ];
    }
}
