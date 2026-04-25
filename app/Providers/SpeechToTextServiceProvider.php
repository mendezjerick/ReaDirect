<?php

namespace App\Providers;

use App\Services\SpeechToText\ConfiguredSpeechToTextService;
use App\Services\SpeechToText\SpeechToTextServiceInterface as LegacySpeechToTextServiceInterface;
use App\Services\STT\MockSTTService;
use App\Services\STT\SpeechToTextServiceInterface;
use App\Services\STT\WhisperCppSTTService;
use Illuminate\Support\ServiceProvider;

class SpeechToTextServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpeechToTextServiceInterface::class, function ($app) {
            return match (config('stt.provider')) {
                'whisper_cpp' => $app->make(WhisperCppSTTService::class),
                default => $app->make(MockSTTService::class),
            };
        });

        $this->app->bind(LegacySpeechToTextServiceInterface::class, ConfiguredSpeechToTextService::class);
    }
}
