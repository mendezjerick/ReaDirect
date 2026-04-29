<?php

namespace Tests\Feature;

use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\School;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AI\ReadirectAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadirectAIIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_readirect_ai_service_sends_configured_analyze_text_request(): void
    {
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.api_token' => 'secret-token',
            'readirect_ai.endpoints.analyze_text' => '/analyze-text',
        ]);

        Http::fake([
            'http://ai.test/analyze-text' => Http::response([
                'ok' => true,
                'transcript' => 'cap',
                'error_type' => 'final_sound_error',
            ]),
        ]);

        $response = app(ReadirectAIService::class)->analyzeText([
            'expected_text' => 'cat',
            'actual_text' => 'cap',
        ]);

        $this->assertTrue($response['ok']);
        $this->assertSame('final_sound_error', $response['error_type']);

        Http::assertSent(fn ($request) => $request->hasHeader('X-ReaDirect-AI-Token', 'secret-token')
            && $request['expected_text'] === 'cat'
            && $request['actual_text'] === 'cap');
    }

    public function test_ai_analysis_resolver_prefers_ai_audio_response_and_stores_audio_fields(): void
    {
        Storage::fake('local');
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
        ]);
        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'request_id' => 'req-123',
                'provider' => 'hf_whisper_local',
                'model_size' => 'readirect-whisper-base-en-v1-hf',
                'transcript' => 'cap',
                'normalized_transcript' => 'cap',
                'confidence' => null,
                'similarity_label' => 'very_close',
                'error_type' => 'final_sound_error',
                'warnings' => [],
            ]),
        ]);

        $audioFile = $this->audioFile();

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'cat',
            'accepted_answers' => ['cat'],
            'prompt_id' => 'M2-001',
        ]);

        $audioFile->refresh();

        $this->assertSame('cap', $resolved['transcript']);
        $this->assertSame('ai_asr', $resolved['source']);
        $this->assertSame('hf_whisper_local', $audioFile->ai_provider);
        $this->assertSame('req-123', $audioFile->ai_request_id);
        $this->assertNotNull($audioFile->ai_completed_at);
    }

    public function test_ai_analysis_resolver_falls_back_to_existing_stt_when_ai_fails(): void
    {
        Storage::fake('local');
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
            'readirect_ai.fallback.use_existing_stt_if_ai_offline' => true,
            'stt.mock.transcript' => 'cat',
        ]);
        Http::fake(['http://ai.test/analyze-audio' => Http::response(['ok' => false, 'error' => 'offline'], 500)]);

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $this->audioFile(), [
            'expected_text' => 'cat',
            'accepted_answers' => ['cat'],
        ]);

        $this->assertSame('cat', $resolved['transcript']);
        $this->assertSame('stt_auto', $resolved['source']);
        $this->assertSame('offline', $resolved['ai_response']['error']);
    }

    public function test_dashboard_status_reports_connected_ai_service(): void
    {
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.health' => '/health',
            'readirect_ai.endpoints.version' => '/version',
        ]);

        Http::fake([
            'http://ai.test/health' => Http::response([
                'status' => 'ok',
                'service' => 'ReaDirect AI/ASR Service',
                'version' => '0.1.0',
                'asr_provider' => 'hf_whisper_local',
                'content_index_loaded' => true,
                'cmudict_loaded' => true,
            ]),
            'http://ai.test/version' => Http::response([
                'service' => 'ReaDirect AI/ASR Service',
                'version' => '0.1.0',
                'config' => ['asr' => ['provider' => 'hf_whisper_local', 'model_size' => 'readirect-whisper-base-en-v1-hf']],
            ]),
        ]);

        $status = app(ReadirectAIService::class)->dashboardStatus();

        $this->assertTrue($status['connected']);
        $this->assertSame('connected', $status['status']);
        $this->assertSame('hf_whisper_local', $status['asr_provider']);
        $this->assertSame('readirect-whisper-base-en-v1-hf', $status['model_size']);
    }

    public function test_response_fields_map_ai_response_to_storable_columns(): void
    {
        $fields = app(AIAnalysisResolver::class)->responseFields([
            'transcript' => 'cap',
            'normalized_transcript' => 'cap',
            'similarity_label' => 'very_close',
            'character_similarity' => 0.67,
            'expected_phonemes' => ['K', 'AE', 'T'],
            'actual_phonemes' => ['K', 'AE', 'P'],
            'error_type' => 'final_sound_error',
        ]);

        $this->assertSame('cap', $fields['ai_transcript']);
        $this->assertSame('very_close', $fields['ai_similarity_label']);
        $this->assertSame(['K', 'AE', 'T'], $fields['ai_expected_phonemes']);
        $this->assertSame('final_sound_error', $fields['ai_error_type']);
        $this->assertArrayHasKey('ai_analyzed_at', $fields);
    }

    private function audioFile(): AudioFile
    {
        $learner = $this->learner();
        $path = 'audio/learners/'.$learner->public_id.'/sample.webm';
        Storage::disk('local')->put($path, 'fake-audio');

        return AudioFile::create([
            'learner_id' => $learner->id,
            'disk' => 'local',
            'path' => $path,
            'file_path' => $path,
            'mime_type' => 'audio/webm',
            'size_bytes' => 10,
            'file_size' => 10,
            'file_hash' => hash('sha256', 'fake-audio'),
            'recording_context' => 'assessment_task',
            'sync_status' => 'synced',
        ]);
    }

    private function learner(): Learner
    {
        $school = School::create(['name' => 'AI Test School']);

        return Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('AI-', false),
            'first_name' => 'AI',
            'grade_level' => 'Grade 1',
        ]);
    }
}
