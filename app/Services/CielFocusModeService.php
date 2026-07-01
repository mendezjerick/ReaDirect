<?php

namespace App\Services;

use App\Models\LearnerReward;
use App\Models\Module;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\VoiceLines\ModuleEchoLineFactory;

class CielFocusModeService
{
    public function __construct(private readonly ModuleEchoLineFactory $moduleEchoLines) {}

    public const SPECIAL_STAR_REWARD_TYPE = 'advanced_star';

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
            return $this->teachingEvent($item, $module, $activityType, $targetText);
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

    public function specialStarTotal(int $learnerId): int
    {
        return (int) LearnerReward::query()
            ->where('learner_id', $learnerId)
            ->where('reward_type', self::SPECIAL_STAR_REWARD_TYPE)
            ->sum('amount');
    }

    public function awardAdvancedModuleStar(int $learnerId, int $moduleId, int $moduleAttemptId): LearnerReward
    {
        return LearnerReward::query()->firstOrCreate(
            [
                'learner_id' => $learnerId,
                'reward_type' => self::SPECIAL_STAR_REWARD_TYPE,
                'reason' => 'advanced_module_complete',
                'source_type' => 'advanced_module',
                'source_id' => $moduleId,
            ],
            [
                'amount' => 1,
                'metadata' => [
                    'module_attempt_id' => $moduleAttemptId,
                ],
            ],
        );
    }

    private function teachingEvent(ModuleAttemptItem $item, Module $module, string $activityType, ?string $targetText): array
    {
        $echo = $this->moduleEchoLines->forAttemptItem($item);
        $targetType = $echo['target_type'] ?? $this->targetType($module, $activityType);
        $target = trim((string) ($echo['target_text'] ?? $targetText ?? ''));
        $correctEcho = $echo['correct'] ?? [
            'text' => $this->spokenTarget($target),
            'line_key' => null,
            'intent' => ModuleEchoLineFactory::CORRECT_INTENT,
        ];

        return [
            'enabled' => true,
            'mode' => 'teaching',
            'target_type' => $targetType,
            'target_text' => $target,
            'reason' => 'two_wrong_attempts',
            'reward' => null,
            'dialogue_steps' => [
                [
                    'text' => "I'll say it first, listen carefully.",
                    'action' => 'talk',
                    'line_key' => 'ciel.focus.echo_intro',
                    'intent' => ModuleEchoLineFactory::FOCUS_SUPPORT_INTENT,
                ],
                [
                    'text' => $correctEcho['text'],
                    'action' => 'talk',
                    'line_key' => $correctEcho['line_key'],
                    'intent' => $correctEcho['intent'] ?? ModuleEchoLineFactory::CORRECT_INTENT,
                ],
                [
                    'text' => "I'll repeat it one more time, listen closely.",
                    'action' => 'talk',
                    'line_key' => 'ciel.focus.echo_repeat',
                    'intent' => ModuleEchoLineFactory::FOCUS_SUPPORT_INTENT,
                ],
                [
                    'text' => $correctEcho['text'],
                    'action' => 'talk',
                    'line_key' => $correctEcho['line_key'],
                    'intent' => $correctEcho['intent'] ?? ModuleEchoLineFactory::CORRECT_INTENT,
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

        if ($module->key === 'module_3' || $module->key === 'advanced_module') {
            return 'sentence';
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
