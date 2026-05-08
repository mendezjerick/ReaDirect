<?php

namespace App\Support;

final class LearnerStage
{
    public const NEW = 'new';

    public const DIAGNOSTIC_IN_PROGRESS = 'diagnostic_in_progress';

    public const MODULE_ASSIGNED = 'module_assigned';

    public const MODULE_PRACTICE_IN_PROGRESS = 'module_practice_in_progress';

    public const MODULE_MASTERY_IN_PROGRESS = 'module_mastery_in_progress';

    public const EXTRA_PHONEME_DRILLS = 'extra_phoneme_drills';

    public const FINAL_REASSESSMENT_PENDING = 'final_reassessment_pending';

    public const FINAL_REASSESSMENT_IN_PROGRESS = 'final_reassessment_in_progress';

    public const FINAL_REASSESSMENT_COMPLETED = 'final_reassessment_completed';

    public const GRADE_READY = 'grade_ready';

    public const COMPLETED = 'completed';

    public static function normalize(?string $stage): string
    {
        return match ($stage) {
            'module_practice' => self::MODULE_PRACTICE_IN_PROGRESS,
            'module_mastery' => self::MODULE_MASTERY_IN_PROGRESS,
            null, '' => self::NEW,
            default => $stage,
        };
    }
}
