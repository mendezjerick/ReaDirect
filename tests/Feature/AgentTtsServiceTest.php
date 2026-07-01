<?php

namespace Tests\Feature;

use App\Models\GeneratedVoiceLine;
use App\Models\Learner;
use App\Models\Module;
use App\Models\School;
use App\Models\SystemSetting;
use App\Services\TTS\AgentTtsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentTtsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_tts_service_maps_selected_kokoro_voices(): void
    {
        $service = app(AgentTtsService::class);

        $this->assertSame('af_bella', $service->voiceProfile('assessment')['voice']);
        $this->assertSame('af_heart', $service->voiceProfile('coach_feedback')['voice']);
        $this->assertSame('bf_isabella', $service->voiceProfile('evaluator')['voice']);
        $this->assertSame(0.97, $service->voiceProfile('assessment')['speed']);
        $this->assertSame(0.94, $service->voiceProfile('coach_feedback')['speed']);
        $this->assertSame(0.93, $service->voiceProfile('evaluator')['speed']);
    }

    public function test_agent_tts_service_clamps_ciel_speed_and_keeps_heart_voice(): void
    {
        config()->set('readirect.tts.voices.miss_ciel', 'af_bella');
        config()->set('readirect.tts.speeds.miss_ciel', 1.2);

        $profile = app(AgentTtsService::class)->voiceProfile('coach_feedback');

        $this->assertSame('af_heart', $profile['voice']);
        $this->assertSame(0.96, $profile['speed']);
    }

    public function test_tts_disabled_returns_silent_fallback_without_local_paths(): void
    {
        config()->set('readirect.tts.enabled', false);

        $payload = app(AgentTtsService::class)->speechPayload('coach_feedback', 'Good try!');

        $this->assertFalse($payload['voice_enabled']);
        $this->assertNull($payload['audio_url']);
        $this->assertArrayNotHasKey('browser_speech_allowed', $payload);
        $this->assertFalse($payload['text_fallback_allowed']);
        $this->assertArrayNotHasKey('debug', $payload);
        $this->assertStringNotContainsString('storage', json_encode($payload));
    }

    public function test_tts_service_unavailable_returns_safe_fallback(): void
    {
        config()->set('readirect.voice_database.enabled', false);
        config()->set('readirect.tts.enabled', true);
        config()->set('readirect.tts.base_url', 'http://tts.test');
        Http::fake(['http://tts.test/synthesize' => Http::response(['detail' => 'down'], 500)]);

        $payload = app(AgentTtsService::class)->speechPayload('miss_ciel', 'Keep going!');

        $this->assertFalse($payload['voice_enabled']);
        $this->assertNull($payload['audio_url']);
        $this->assertTrue($payload['fallback']);
        $this->assertArrayNotHasKey('debug', $payload);
    }

    public function test_voice_database_missing_selected_stage_returns_silence_without_other_stage_or_runtime_tts(): void
    {
        Storage::fake('public');
        config()->set('readirect.voice_database.enabled', true);
        config()->set('readirect.tts.enabled', true);
        config()->set('readirect.tts.base_url', 'http://tts.test');
        Http::fake();

        Storage::disk('public')->put('tts/generated_voice_lines/ciel/stage2.wav', 'RIFF-stage-two');

        $line = GeneratedVoiceLine::create([
            'line_key' => 'ciel.test.stage_missing',
            'agent' => 'ciel',
            'intent' => 'focused_instruction',
            'text' => 'Use the selected stage only.',
            'kokoro_identity_audio_path' => 'tts/generated_voice_lines/ciel/stage2.wav',
            'kokoro_identity_engine' => 'kokoro',
            'kokoro_identity_duration_seconds' => 7.0,
            'kokoro_identity_status' => 'completed',
        ]);

        $payload = app(AgentTtsService::class)->speechPayload('coach_feedback', 'Use the selected stage only.', true, [
            'line_key' => 'ciel.test.stage_missing',
        ]);

        $this->assertFalse($payload['voice_enabled']);
        $this->assertNull($payload['audio_url']);
        $this->assertFalse($payload['text_fallback_allowed']);
        $this->assertSame('database_voice_line_missing_silent', $payload['debug']['fallback_reason']);
        Http::assertSentCount(0);

        $this->get(route('agent-voice.generated', [
            'line' => $line,
            'stage' => 'kokoro_identity',
        ]))->assertNotFound();

        SystemSetting::create([
            'key' => 'voice_playback_stage',
            'value' => 'kokoro_identity',
            'type' => 'string',
        ]);

        $kokoroPayload = app(AgentTtsService::class)->speechPayload('coach_feedback', 'Use the selected stage only.', false, [
            'line_key' => 'ciel.test.stage_missing',
        ]);

        $this->assertTrue($kokoroPayload['voice_enabled']);
        $this->assertSame('kokoro_identity', $kokoroPayload['active_audio_type']);
        $this->assertStringContainsString('/agent-voice/generated/', $kokoroPayload['audio_url']);
        $this->get($kokoroPayload['audio_url'])
            ->assertOk()
            ->assertHeader('X-ReaDirect-Voice-Stage', 'kokoro_identity');
        Http::assertSentCount(0);
    }

    public function test_successful_tts_response_is_cached_and_served_through_laravel_route(): void
    {
        Storage::fake('local');
        config()->set('readirect.voice_database.enabled', false);
        config()->set('readirect.tts.enabled', true);
        config()->set('readirect.tts.base_url', 'http://tts.test');
        Http::fake(['http://tts.test/synthesize' => Http::response('RIFF-fake-wave', 200, ['Content-Type' => 'audio/wav'])]);

        $payload = app(AgentTtsService::class)->speechPayload('miss_vivian', 'Say the letter out loud.');

        $this->assertTrue($payload['voice_enabled']);
        $this->assertSame('kokoro', $payload['tts_provider']);
        $this->assertMatchesRegularExpression('#^/agent-voice/[a-f0-9]{64}$#', $payload['audio_url']);
        $cacheKey = basename($payload['audio_url']);
        Storage::disk('local')->assertExists('tts_cache/'.$cacheKey.'.wav');
        Http::assertSent(fn ($request) => $request['agent'] === 'miss_vivian'
            && $request['context'] === 'agent_narration'
            && $request['humanize'] === true
            && $request['delivery_control'] === true
            && $request['audio_humanizer'] === true
            && $request['pause_control'] === true);

        $this->get($payload['audio_url'])
            ->assertOk()
            ->assertHeader('content-type', 'audio/wav');
    }

    public function test_audio_cache_route_rejects_invalid_or_missing_cache_keys(): void
    {
        $this->get('/agent-voice/not-a-key')->assertNotFound();
        $this->get('/agent-voice/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/agent-voice/../.env')->assertNotFound();
    }

    public function test_tts_failure_does_not_change_official_learner_state(): void
    {
        config()->set('readirect.voice_database.enabled', false);
        config()->set('readirect.tts.enabled', true);
        config()->set('readirect.tts.base_url', 'http://tts.test');
        Http::fake(['http://tts.test/synthesize' => Http::response(['detail' => 'down'], 500)]);

        $school = School::create(['name' => 'TTS Safety School']);
        $module = Module::create([
            'sequence' => 1,
            'key' => 'module_1',
            'title' => 'Letter Sounds',
            'description' => 'Practice letter sounds.',
            'is_active' => true,
        ]);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('TTS-', false),
            'first_name' => 'Safe',
            'grade_level' => 'Grade 1',
            'current_stage' => 'module_practice_in_progress',
            'current_module_id' => $module->id,
        ]);

        app(AgentTtsService::class)->speechPayload('coach_feedback', 'Good try!');

        $learner->refresh();
        $this->assertSame('module_practice_in_progress', $learner->current_stage);
        $this->assertSame($module->id, $learner->current_module_id);
    }

    public function test_developer_debug_metadata_is_only_returned_when_requested(): void
    {
        Storage::fake('local');
        config()->set('readirect.voice_database.enabled', false);
        config()->set('readirect.tts.enabled', true);
        config()->set('readirect.tts.base_url', 'http://tts.test');
        Http::fake(['http://tts.test/synthesize' => Http::response('RIFF-fake-wave', 200)]);

        $normal = app(AgentTtsService::class)->speechPayload('coach_feedback', 'Good try!', false);
        $debug = app(AgentTtsService::class)->speechPayload('coach_feedback', 'Good try!', true);

        $this->assertArrayNotHasKey('debug', $normal);
        $this->assertArrayHasKey('debug', $debug);
        $this->assertSame('af_heart', $debug['debug']['voice']);
    }
}
