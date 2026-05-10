<?php

namespace App\Providers;

use App\Models\AudioFile;
use App\Policies\AudioFilePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AudioFile::class, AudioFilePolicy::class);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);

        RateLimiter::for('learner-access', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        RateLimiter::for('assessment-submit', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('audio-upload', fn (Request $request) => [
            Limit::perMinute(30)->by($request->ip()),
        ]);

        RateLimiter::for('agent-voice', fn (Request $request) => [
            Limit::perMinute(60)->by($request->ip()),
        ]);

        RateLimiter::for('assessment-progress', fn (Request $request) => [
            Limit::perMinute(60)->by($request->ip()),
        ]);
    }
}
