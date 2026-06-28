<?php

namespace App\Services;

class CoachFeedbackService
{
    public function encouragement(string $state): string
    {
        return match ($state) {
            'retry' => 'Great effort. Listen once more, then try again slowly with your clear reading voice.',
            'completed' => 'Nice reading practice. Let us keep going, one careful step at a time.',
            'processing' => 'I am checking your reading now. Please wait a moment while I listen carefully.',
            default => 'Take your time, then read this one out loud. I will stay with you while you practice.',
        };
    }
}
