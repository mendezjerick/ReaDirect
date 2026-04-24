<?php

namespace App\Providers;

use App\Services\SpeechToText\MockSpeechToTextService;
use App\Services\SpeechToText\SpeechToTextServiceInterface;
use Illuminate\Support\ServiceProvider;

class SpeechToTextServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpeechToTextServiceInterface::class, fn () => new MockSpeechToTextService());
    }
}
