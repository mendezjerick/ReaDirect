<?php

namespace App\Services;

use App\Models\LearnerReward;
use App\Models\Module;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;

class CielFocusModeService
{
    public const MODES = [
        'teaching',
        'reward',
        'compliment',
        'congratulations',
        'reminder',
        'confidence_boost',
    ];

    public function eventForModuleCheck(
        ModuleAttempt $attempt,
        ModuleAttemptItem $item,
        Module $module,
        string $activityType,
        ?string $targetText,
        bool $isCorrect,
        int $attemptNumber,
        int $correctStreak,
    ): ?array {
        if (! $isCorrect && $attemptNumber === 2) {
            return $this->teachingEvent($module, $activityType, $targetText);
        }

        if ($isCorrect && $correctStreak >= 3 && $correctStreak % 3 === 0) {
            return $this->rewardEvent($attempt, $item, $correctStreak);
        }

        return null;
    }

    public function starTotal(int $learnerId): int
    {
        return (int) LearnerReward::query()
            ->where('learner_id', $learnerId)
            ->where('reward_type', 'star')
            ->sum('amount');
    }

    private function teachingEvent(Module $module, string $activityType, ?string $targetText): array
    {
        $targetType = $this->targetType($module, $activityType);
        $target = trim((string) $targetText);
        $spokenTarget = $this->spokenTarget($target);

        return [
            'enabled' => true,
            'mode' => 'teaching',
            'target_type' => $targetType,
            'target_text' => $target,
            'reason' => 'two_wrong_attempts',
            'reward' => null,
            'dialogue_steps' => [
                [
                    'text' => "Let's practice reading this {$targetType} together. Look closely, listen carefully, and take your time.",
                    'action' => 'talk',
                ],
                [
                    'text' => "I will say the {$targetType} first, and you can listen before you try it again.",
                    'action' => 'talk',
                ],
                [
                    'text' => $spokenTarget,
                    'action' => 'talk',
                ],
                [
                    'text' => "Got it? I'll say it again one more time, then you can try it with your clear voice.",
                    'action' => 'talk',
                ],
                [
                    'text' => $spokenTarget,
                    'action' => 'talk',
                ],
            ],
        ];
    }

    private function rewardEvent(ModuleAttempt $attempt, ModuleAttemptItem $item, int $correctStreak): ?array
    {
        $sourceType = 'module_attempt_correct_streak_'.$correctStreak;

        $reward = LearnerReward::query()->firstOrCreate(
            [
                'learner_id' => $attempt->learner_id,
                'reward_type' => 'star',
                'reason' => 'three_correct_streak',
                'source_type' => $sourceType,
                'source_id' => $attempt->id,
            ],
            [
                'amount' => 1,
                'metadata' => [
                    'module_attempt_item_id' => $item->id,
                    'correct_streak' => $correctStreak,
                ],
            ],
        );

        if (! $reward->wasRecentlyCreated) {
            return null;
        }

        return [
            'enabled' => true,
            'mode' => 'reward',
            'target_type' => null,
            'target_text' => null,
            'reason' => 'three_correct_streak',
            'reward' => [
                'type' => 'star',
                'amount' => 1,
            ],
            'dialogue_steps' => [
                [
                    'text' => 'Great job! You answered three in a row correctly, and your clear voice is getting stronger.',
                    'action' => 'happy',
                ],
                [
                    'text' => 'You earned a star! Keep going slowly, and keep trying your best on each reading step.',
                    'action' => 'clap',
                ],
            ],
        ];
    }

    private function targetType(Module $module, string $activityType): string
    {
        $activity = strtolower($activityType);

        if ($module->key === 'module_1' || str_contains($activity, 'letter') || str_contains($activity, 'sound')) {
            return 'letter';
        }

        return 'word';
    }

    private function spokenTarget(string $target): string
    {
        if ($target === '') {
            return 'This one.';
        }

        return ucfirst($target).'.';
    }
}
