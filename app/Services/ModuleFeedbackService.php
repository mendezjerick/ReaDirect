<?php

namespace App\Services;

use App\Models\LearningContent;

class ModuleFeedbackService
{
    public function feedbackForCorrect(string $moduleKey, string $activityType): array
    {
        return $this->template($moduleKey, $activityType, 'correct');
    }

    public function feedbackForIncorrect(string $moduleKey, string $activityType, string $errorType = 'incorrect_general'): array
    {
        return $this->template($moduleKey, $activityType, $errorType)
            ?: $this->template($moduleKey, $activityType, 'incorrect_general');
    }

    public function feedbackForModuleComplete(string $moduleKey): array
    {
        return $this->template($moduleKey, 'all', 'module_complete');
    }

    private function template(string $moduleKey, string $activityType, string $errorType): array
    {
        $templates = LearningContent::where('content_type', 'module_feedback_template')
            ->where('is_active', true)
            ->get()
            ->filter(fn (LearningContent $content) => ($content->payload['error_type'] ?? null) === $errorType)
            ->sortBy(function (LearningContent $content) use ($moduleKey, $activityType): int {
                $payload = $content->payload ?? [];
                $score = 0;
                $score += ($payload['module_key'] ?? null) === $moduleKey ? 4 : 0;
                $score += ($payload['module_key'] ?? null) === 'all' ? 1 : 0;
                $score += ($payload['activity_type'] ?? null) === $activityType ? 4 : 0;
                $score += ($payload['activity_type'] ?? null) === 'all' ? 1 : 0;

                return -$score;
            })
            ->first();

        if (! $templates) {
            return [
                'feedback_text' => 'Great effort! Try this one again.',
                'retry_instruction' => 'Try again when you are ready.',
                'success_text' => 'Nice reading!',
            ];
        }

        return [
            'feedback_text' => $templates->prompt,
            'retry_instruction' => $templates->payload['retry_instruction'] ?? 'Try again when you are ready.',
            'success_text' => $templates->payload['success_text'] ?? 'Nice reading!',
        ];
    }
}
