<?php

namespace Tests\Feature;

use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\School;
use App\Models\User;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AI\ReadirectAIService;
use App\Services\DeveloperReinforcementModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
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
                'asr_route' => 'wav2vec2_only',
                'model_family' => 'wav2vec2',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'transcript' => 'cap',
                'raw_transcript' => 'cap',
                'corrected_transcript' => 'cap',
                'displayed_transcript' => 'cap',
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
        $this->assertSame('wav2vec2', $audioFile->ai_provider);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $audioFile->ai_model);
        $this->assertSame('req-123', $audioFile->ai_request_id);
        $this->assertNotNull($audioFile->ai_completed_at);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body());

            return $request->url() === 'http://ai.test/analyze-audio'
                && $payload->expected_text === 'cat'
                && $payload->prompt_type === 'word'
                && $payload->content_metadata instanceof \stdClass;
        });
    }

    public function test_ai_analysis_resolver_prefers_corrected_transcript_for_scoring_and_preserves_raw(): void
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
                'request_id' => 'req-456',
                'model_family' => 'wav2vec2',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'transcript' => 'Read',
                'normalized_transcript' => 'red',
                'raw_transcript' => 'Read',
                'corrected_transcript' => 'Red',
                'displayed_transcript' => 'Red',
                'raw_wer' => 1.0,
                'corrected_wer' => 0.0,
                'phonetic_similarity_score' => 0.95,
                'normalization_applied' => true,
                'normalization_reason' => 'ASR transcript is a known homophone or near-homophone of expected text',
                'correction_strategy_used' => 'known_confusion_expected_prompt_alignment',
                'accepted_by_phonetic_threshold' => true,
                'threshold_used' => 0.82,
                'warnings' => [],
            ]),
        ]);

        $audioFile = $this->audioFile();

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'Red',
            'accepted_answers' => ['Red'],
        ]);

        $audioFile->refresh();

        $this->assertSame('Red', $resolved['transcript']);
        $this->assertSame('Red', $resolved['displayed_transcript']);
        $this->assertSame('Read', $audioFile->ai_transcript);
        $this->assertSame('Red', $audioFile->ai_normalized_transcript);
        $this->assertEquals(1.0, $resolved['ai_response']['raw_wer']);
        $this->assertEquals(0.0, $resolved['ai_response']['corrected_wer']);
    }

    public function test_developer_reinforcement_flags_are_sent_only_for_admin_when_enabled(): void
    {
        Storage::fake('local');
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
        ]);
        app(DeveloperReinforcementModeService::class)->setEnabled(true);
        $admin = $this->userWithRole('system_admin');
        $audioFile = $this->audioFile();

        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'transcript' => 'Layo',
                'raw_transcript' => 'Layo',
                'corrected_transcript' => 'Layo',
                'displayed_transcript' => 'Layo',
                'accepted' => false,
                'warnings' => [],
            ]),
        ]);

        $this->actingAs($admin);
        app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'Leo',
            'prompt_type' => 'word',
        ]);

        Http::assertSent(fn ($request) => $request['developer_reinforcement_enabled'] === true
            && $request['developer_user_role'] === 'admin'
            && $request['developer_user_id'] === $admin->email);
    }

    public function test_developer_reinforcement_flags_are_false_for_learner_requests(): void
    {
        Storage::fake('local');
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
        ]);
        app(DeveloperReinforcementModeService::class)->setEnabled(true);
        $audioFile = $this->audioFile();

        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'transcript' => 'Layo',
                'raw_transcript' => 'Layo',
                'corrected_transcript' => 'Layo',
                'displayed_transcript' => 'Layo',
                'accepted' => false,
                'warnings' => [],
            ]),
        ]);

        app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'Leo',
            'prompt_type' => 'word',
        ]);

        Http::assertSent(fn ($request) => $request['developer_reinforcement_enabled'] === false
            && $request['developer_user_role'] === null
            && $request['developer_user_id'] === null);
    }

    public function test_laravel_uses_corrected_and_displayed_transcripts_for_letter_alias_response(): void
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
                'expected_text' => 'L',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'model_family' => 'wav2vec2',
                'asr_route' => 'wav2vec2_only',
                'raw_transcript' => 'Elle',
                'corrected_transcript' => 'L',
                'displayed_transcript' => 'L',
                'accepted' => true,
                'prompt_type' => 'letter',
            ]),
        ]);

        $audioFile = $this->audioFile();
        $resolved = app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'L',
            'accepted_answers' => ['L'],
        ]);

        $audioFile->refresh();

        $this->assertSame('L', $resolved['transcript']);
        $this->assertSame('L', $resolved['displayed_transcript']);
        $this->assertSame('Elle', $audioFile->ai_transcript);
        $this->assertSame('L', $audioFile->ai_normalized_transcript);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $audioFile->ai_model);
        $this->assertTrue(app(AIAnalysisResolver::class)->acceptedForShortPrompt($resolved['ai_response']));
    }

    public function test_laravel_uses_corrected_and_displayed_transcripts_for_word_homophone_response(): void
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
                'expected_text' => 'tree',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'model_family' => 'wav2vec2',
                'asr_route' => 'wav2vec2_only',
                'raw_transcript' => 'three',
                'corrected_transcript' => 'tree',
                'displayed_transcript' => 'tree',
                'accepted' => true,
                'prompt_type' => 'word',
            ]),
        ]);

        $audioFile = $this->audioFile();
        $resolved = app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'tree',
            'accepted_answers' => ['tree'],
        ]);

        $audioFile->refresh();

        $this->assertSame('tree', $resolved['transcript']);
        $this->assertSame('tree', $resolved['displayed_transcript']);
        $this->assertSame('three', $audioFile->ai_transcript);
        $this->assertSame('tree', $audioFile->ai_normalized_transcript);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $audioFile->ai_model);
        $this->assertTrue(app(AIAnalysisResolver::class)->acceptedForShortPrompt($resolved['ai_response']));
    }

    public function test_laravel_preserves_reinforcement_correction_metadata(): void
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
                'expected_text' => 'Z',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'model_family' => 'wav2vec2',
                'asr_route' => 'wav2vec2_only',
                'raw_transcript' => 'They',
                'corrected_transcript' => 'Z',
                'displayed_transcript' => 'Z',
                'accepted' => true,
                'accepted_by_reinforcement_match' => true,
                'prompt_type' => 'letter',
            ]),
        ]);

        $audioFile = $this->audioFile();
        $resolved = app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'Z',
            'accepted_answers' => ['Z'],
        ]);

        $audioFile->refresh();

        $this->assertSame('Z', $resolved['transcript']);
        $this->assertSame('Z', $resolved['displayed_transcript']);
        $this->assertSame('They', $audioFile->ai_transcript);
        $this->assertSame('Z', $audioFile->ai_normalized_transcript);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $audioFile->ai_model);
        $this->assertTrue($resolved['ai_response']['accepted_by_reinforcement_match']);
        $this->assertTrue(app(AIAnalysisResolver::class)->acceptedForShortPrompt($resolved['ai_response']));
    }

    public function test_laravel_keeps_rejected_transcript_and_does_not_force_expected_text(): void
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
                'expected_text' => 'tree',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'model_family' => 'wav2vec2',
                'asr_route' => 'wav2vec2_only',
                'raw_transcript' => 'banana',
                'corrected_transcript' => 'banana',
                'displayed_transcript' => 'banana',
                'accepted' => false,
                'prompt_type' => 'word',
            ]),
        ]);

        $audioFile = $this->audioFile();
        $resolved = app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'tree',
            'accepted_answers' => ['tree'],
        ]);

        $audioFile->refresh();

        $this->assertSame('banana', $resolved['transcript']);
        $this->assertSame('banana', $resolved['displayed_transcript']);
        $this->assertSame('banana', $audioFile->ai_transcript);
        $this->assertSame('banana', $audioFile->ai_normalized_transcript);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $audioFile->ai_model);
        $this->assertFalse(app(AIAnalysisResolver::class)->acceptedForShortPrompt($resolved['ai_response']));
    }

    public function test_laravel_display_falls_back_to_corrected_transcript_when_displayed_missing(): void
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
                'raw_transcript' => 'then',
                'corrected_transcript' => 'ten',
                'accepted' => true,
                'prompt_type' => 'word',
                'model_family' => 'wav2vec2',
            ]),
        ]);

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $this->audioFile(), [
            'expected_text' => 'ten',
            'accepted_answers' => ['ten'],
        ]);

        $this->assertSame('ten', $resolved['transcript']);
        $this->assertSame('ten', $resolved['displayed_transcript']);
    }

    public function test_laravel_falls_back_to_transcript_then_raw_when_corrected_missing(): void
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
                'transcript' => 'cat',
                'raw_transcript' => 'cat',
                'accepted' => true,
                'prompt_type' => 'word',
                'model_family' => 'wav2vec2',
            ]),
        ]);

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $this->audioFile(), [
            'expected_text' => 'cat',
            'accepted_answers' => ['cat'],
        ]);

        $this->assertSame('cat', $resolved['transcript']);
        $this->assertSame('cat', $resolved['displayed_transcript']);
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
            'readirect.ollama.enabled' => true,
            'readirect.agent_feedback.miss_ciel_ollama_enabled' => true,
            'readirect.ollama.base_url' => 'http://ollama.test',
            'readirect.ollama.model' => 'qwen3:4b',
        ]);

        Http::fake([
            'http://ai.test/health' => Http::response([
                'status' => 'ok',
                'service' => 'ReaDirect AI/ASR Service',
                'version' => '0.1.0',
                'asr_architecture' => 'wav2vec2_only',
                'active_asr_model' => 'wav2vec2',
                'model_version' => 'letters-v2',
                'base_model' => 'models/wav2vec2-readirect-asr',
                'training_type' => 'continued_fine_tuning',
                'training_mix' => '50% ReaDirect letters, 30% SpeechOcean, 20% LibriSpeech',
                'wav2vec2_asr_available' => true,
                'wav2vec2_asr_model_name' => 'models/wav2vec2-readirect-asr-letters-v2',
                'wav2vec2_phoneme_available' => true,
                'wav2vec2_phoneme_model_name' => 'models/wav2vec2-phoneme',
                'whisper_removed' => true,
                'correction_layer_enabled' => true,
                'expected_centric_scoring_enabled' => true,
                'phoneme_evidence_enabled' => true,
                'reinforcement_corrections_enabled' => true,
                'audio_quality_validation_enabled' => true,
                'pause_detection_enabled' => true,
                'uncertainty_decision_enabled' => true,
                'content_index_loaded' => true,
                'cmudict_loaded' => true,
            ]),
            'http://ai.test/version' => Http::response([
                'service' => 'ReaDirect AI/ASR Service',
                'version' => '0.1.0',
                'config' => ['asr' => ['architecture' => 'wav2vec2_only', 'provider' => 'wav2vec2']],
            ]),
            'http://ollama.test/api/tags' => Http::response([
                'models' => [['name' => 'qwen3:4b']],
            ]),
        ]);

        $status = app(ReadirectAIService::class)->dashboardStatus();

        $this->assertTrue($status['connected']);
        $this->assertSame('connected', $status['status']);
        $this->assertSame('wav2vec2_only', $status['asr_architecture']);
        $this->assertSame('wav2vec2', $status['asr_provider']);
        $this->assertTrue($status['whisper_removed']);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $status['model_size']);
        $this->assertSame('letters-v2', $status['model_version']);
        $this->assertSame('models/wav2vec2-readirect-asr', $status['base_model']);
        $this->assertSame('continued_fine_tuning', $status['training_type']);
        $this->assertTrue($status['audio_quality_validation_enabled']);
        $this->assertTrue($status['pause_detection_enabled']);
        $this->assertTrue($status['uncertainty_decision_enabled']);
        $this->assertTrue($status['llm']['connected']);
        $this->assertSame('LLM Connected', $status['llm']['label']);
        $this->assertSame('qwen3:4b', $status['llm']['model']);
        $this->assertSame(
            'corrected_transcript -> transcript -> raw_transcript',
            $status['laravel_response_contract']['scoring_transcript']
        );
        $this->assertSame(
            'displayed_transcript -> corrected_transcript -> transcript -> raw_transcript',
            $status['laravel_response_contract']['learner_display_transcript']
        );
    }

    public function test_response_fields_map_ai_response_to_storable_columns(): void
    {
        $fields = app(AIAnalysisResolver::class)->responseFields([
            'transcript' => 'cap',
            'normalized_transcript' => 'cat',
            'raw_transcript' => 'cap',
            'corrected_transcript' => 'cat',
            'displayed_transcript' => 'cat',
            'raw_wer' => 1.0,
            'corrected_wer' => 0.0,
            'similarity_label' => 'very_close',
            'character_similarity' => 0.67,
            'expected_phonemes' => ['K', 'AE', 'T'],
            'observed_phonemes' => ['K', 'AE', 'P'],
            'phonetic_similarity_score' => 0.8,
            'error_type' => 'final_sound_error',
        ]);

        $this->assertSame('cap', $fields['ai_transcript']);
        $this->assertSame('cat', $fields['ai_normalized_transcript']);
        $this->assertSame('very_close', $fields['ai_similarity_label']);
        $this->assertSame(['K', 'AE', 'T'], $fields['ai_expected_phonemes']);
        $this->assertSame(['K', 'AE', 'P'], $fields['ai_actual_phonemes']);
        $this->assertSame(0.8, $fields['ai_phoneme_similarity']);
        $this->assertSame('final_sound_error', $fields['ai_error_type']);
        $this->assertArrayHasKey('ai_analyzed_at', $fields);
    }

    public function test_response_fields_preserve_v2_debug_contract_metadata(): void
    {
        $aiResponse = [
            'transcript' => 'L',
            'expected_text' => 'L',
            'prompt_type' => 'letter',
            'asr_route' => 'wav2vec2_only',
            'model_family' => 'wav2vec2',
            'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
            'raw_transcript' => 'Elle',
            'wav2vec2_transcript' => 'Elle',
            'corrected_transcript' => 'L',
            'displayed_transcript' => 'L',
            'accepted' => true,
            'raw_wer' => 1.0,
            'corrected_wer' => 0.0,
            'raw_cer' => 3.0,
            'corrected_cer' => 0.0,
            'phonetic_similarity_score' => 0.96,
            'composite_score' => 0.98,
            'threshold_used' => 0.85,
            'normalization_reason' => 'ASR transcript is a valid spoken form of the expected letter',
            'correction_strategy_used' => 'wav2vec2_expected_centric_acoustic_phonetic_scoring',
            'audio_quality' => ['quality_flags' => ['too_short' => false]],
            'pause_metrics' => ['pause_count' => 0],
            'retry_required' => false,
            'uncertain' => false,
            'uncertainty_reasons' => [],
            'debug_metadata' => ['actual_device' => 'cpu'],
        ];

        $fields = app(AIAnalysisResolver::class)->responseFields($aiResponse);

        $this->assertSame('Elle', $fields['ai_transcript']);
        $this->assertSame('L', $fields['ai_normalized_transcript']);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $fields['ai_response']['model_used']);
        $this->assertSame('Elle', $fields['ai_response']['raw_transcript']);
        $this->assertSame('L', $fields['ai_response']['corrected_transcript']);
        $this->assertSame('L', $fields['ai_response']['displayed_transcript']);
        $this->assertSame(['actual_device' => 'cpu'], $fields['ai_response']['debug_metadata']);
        $this->assertFalse($fields['ai_response']['retry_required']);
    }

    public function test_ai_analysis_resolver_uses_displayed_transcript_fallbacks(): void
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
                'transcript' => 'z',
                'raw_transcript' => 'zy',
                'corrected_transcript' => 'Z',
                'accepted' => true,
                'prompt_type' => 'letter',
                'model_family' => 'wav2vec2',
            ]),
        ]);

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $this->audioFile(), [
            'expected_text' => 'Z',
            'accepted_answers' => ['Z'],
        ]);

        $this->assertSame('Z', $resolved['transcript']);
        $this->assertSame('Z', $resolved['displayed_transcript']);
        $this->assertTrue(app(AIAnalysisResolver::class)->acceptedForShortPrompt($resolved['ai_response']));
    }

    public function test_ai_analysis_resolver_falls_back_to_raw_when_corrected_and_transcript_missing(): void
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
                'raw_transcript' => 'banana',
                'accepted' => false,
                'prompt_type' => 'word',
            ]),
        ]);

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $this->audioFile(), [
            'expected_text' => 'ten',
            'accepted_answers' => ['ten'],
        ]);

        $this->assertSame('banana', $resolved['transcript']);
        $this->assertSame('banana', $resolved['displayed_transcript']);
    }

    public function test_sentence_display_is_not_forced_to_expected_text(): void
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
                'prompt_type' => 'sentence',
                'raw_transcript' => 'I see a three',
                'corrected_transcript' => 'I see a three',
                'displayed_transcript' => 'I see a three',
                'accepted' => false,
            ]),
        ]);

        $resolved = app(AIAnalysisResolver::class)->resolve(null, $this->audioFile(), [
            'expected_text' => 'I see a tree',
            'prompt_type' => 'sentence',
        ]);

        $this->assertSame('I see a three', $resolved['displayed_transcript']);
        $this->assertFalse(app(AIAnalysisResolver::class)->acceptedForShortPrompt($resolved['ai_response']));
    }

    public function test_retry_required_blank_ai_response_is_preserved_without_fallback_scoring(): void
    {
        Storage::fake('local');
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
            'readirect_ai.fallback.use_existing_stt_if_ai_offline' => true,
            'stt.mock.transcript' => 'tree',
        ]);
        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'expected_text' => 'tree',
                'raw_transcript' => '',
                'corrected_transcript' => '',
                'displayed_transcript' => '',
                'accepted' => false,
                'retry_required' => true,
                'uncertain' => true,
                'uncertainty_reasons' => ['audio_too_short'],
                'learner_retry_message' => 'Please record again.',
                'audio_quality' => ['quality_flags' => ['too_short' => true]],
            ]),
        ]);

        $audioFile = $this->audioFile();
        $resolved = app(AIAnalysisResolver::class)->resolve(null, $audioFile, [
            'expected_text' => 'tree',
            'accepted_answers' => ['tree'],
        ]);
        $audioFile->refresh();

        $this->assertSame('', $resolved['transcript']);
        $this->assertSame('ai_asr', $resolved['source']);
        $this->assertTrue($resolved['ai_response']['retry_required']);
        $this->assertSame(['audio_too_short'], $resolved['ai_response']['uncertainty_reasons']);
        $this->assertSame('', $audioFile->ai_transcript);
        $this->assertSame('', $audioFile->ai_normalized_transcript);
    }

    public function test_audio_upload_surfaces_retry_required_guidance(): void
    {
        Storage::fake('local');
        $learner = $this->learner();
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
        ]);
        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'expected_text' => 'tree',
                'raw_transcript' => '',
                'corrected_transcript' => '',
                'displayed_transcript' => '',
                'accepted' => false,
                'retry_required' => true,
                'uncertain' => true,
                'uncertainty_reasons' => ['audio_too_short'],
                'learner_retry_message' => 'Please record again.',
                'audio_quality' => ['quality_flags' => ['too_short' => true]],
                'pause_metrics' => ['pause_count' => 0],
            ]),
        ]);

        $response = $this
            ->withSession(['learner_id' => $learner->id, 'admin_testing_mode' => true])
            ->postJson('/learner/audio/upload', [
                'audio' => UploadedFile::fake()->create('short.webm', 10, 'audio/webm'),
                'context_type' => 'comprehension_optional',
                'duration_seconds' => 1.0,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('transcription_status', 'failed')
            ->assertJsonPath('retry_required', true)
            ->assertJsonPath('uncertain', true)
            ->assertJsonPath('uncertainty_reasons.0', 'audio_too_short')
            ->assertJsonPath('learner_retry_message', 'Please record again.')
            ->assertJsonPath('transcription_message', 'Please record again.')
            ->assertJsonPath('audio_quality.quality_flags.too_short', true)
            ->assertJsonPath('pause_metrics.pause_count', 0);
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

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create([
            'email' => uniqid($role, false).'@example.com',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
