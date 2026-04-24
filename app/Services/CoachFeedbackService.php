<?php

namespace App\Services;

class CoachFeedbackService
{
    public function encouragement(string $state): string
    {
        return match ($state) {
            'retry' => 'Great effort. Listen once more and try again.',
            'completed' => 'Nice reading practice. Let us keep going.',
            'processing' => 'I am checking your reading now.',
            default => 'Take your time and read clearly.',
        };
    }
}
