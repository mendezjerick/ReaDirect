<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\LearnerPreference;
use InvalidArgumentException;

class LearnerListeningModeService
{
    public const MANUAL = 'manual';

    public const AUTOMATIC_CIEL = 'automatic_ciel';

    public const ALLOWED = [
        self::MANUAL,
        self::AUTOMATIC_CIEL,
    ];

    public function forLearner(?Learner $learner): string
    {
        if (! $learner) {
            return self::MANUAL;
        }

        $mode = $learner->relationLoaded('preference')
            ? $learner->preference?->listening_mode
            : $learner->preference()->value('listening_mode');

        return $this->normalize($mode);
    }

    public function props(?Learner $learner): array
    {
        return [
            'current' => $this->forLearner($learner),
            'default' => self::MANUAL,
            'allowed' => self::ALLOWED,
            'automatic_mode_available' => true,
        ];
    }

    public function normalize(?string $mode): string
    {
        return in_array($mode, self::ALLOWED, true) ? $mode : self::MANUAL;
    }

    public function validate(string $mode): string
    {
        if (! in_array($mode, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Unsupported learner listening mode [{$mode}].");
        }

        return $mode;
    }

    public function setForLearner(Learner $learner, string $mode): LearnerPreference
    {
        return LearnerPreference::query()->updateOrCreate(
            ['learner_id' => $learner->id],
            ['listening_mode' => $this->validate($mode)]
        );
    }
}
