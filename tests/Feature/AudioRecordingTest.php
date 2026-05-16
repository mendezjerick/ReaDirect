<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AssessmentItemSelectionService;
use App\Services\ModuleActivitySelectionService;
use App\Services\SpeechToText\SpeechToTextServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AudioRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_can_upload_valid_audio_to_private_storage(): void
    {
        Storage::fake('local');
        $learner = $this->learner();
        $file = UploadedFile::fake()->create('letter.webm', 120, 'audio/webm');

        $response = $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => $file,
                'context_type' => 'assessment_task',
                'duration_seconds' => 4,
            ]);

        $response->assertOk()->assertJsonStructure(['audio_file_id', 'audio_file_public_id']);
        $audioFile = AudioFile::firstOrFail();

        $this->assertSame($learner->id, $audioFile->learner_id);
        $this->assertSame('assessment_task', $audioFile->recording_context);
        $this->assertSame('synced', $audioFile->sync_status);
        Storage::disk('local')->assertExists($audioFile->file_path);
    }

    public function test_audio_upload_rejects_invalid_type_and_oversized_files(): void
    {
        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
                'context_type' => 'assessment_task',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');

        $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('large.webm', 11000, 'audio/webm'),
                'context_type' => 'assessment_task',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');

        $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('video-container.mp4', 10, 'audio/mp4'),
                'context_type' => 'assessment_task',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
    }

    public function test_audio_upload_rejects_accidental_taps_shorter_than_half_second(): void
    {
        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('short.webm', 10, 'audio/webm'),
                'context_type' => 'assessment_task',
                'duration_seconds' => 0.4,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_seconds');
    }

    public function test_audio_upload_accepts_recorder_metadata_boolean_values(): void
    {
        Storage::fake('local');
        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('passage.wav', 120, 'audio/wav'),
                'context_type' => 'passage_reading',
                'duration_seconds' => 4,
                'audio_metadata' => [
                    'total_duration_seconds' => 4.0,
                    'speech_duration_seconds' => 3.2,
                    'leading_silence_seconds' => 0.2,
                    'trailing_silence_seconds' => 0.4,
                    'silence_ratio' => 0.2,
                    'speech_ratio' => 0.8,
                    'was_trimmed' => '1',
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['audio_file_id']);

        $this->assertSame('1', AudioFile::firstOrFail()->metadata['audio_metadata']['was_trimmed']);
    }

    public function test_audio_playback_is_authorized_for_assigned_teacher_only(): void
    {
        Storage::fake('local');
        [$teacher, $learner] = $this->teacherWithLearner();
        $outsideTeacher = $this->userWithRole('teacher');
        $path = 'audio/learners/'.$learner->public_id.'/sample.webm';
        Storage::disk('local')->put($path, 'fake-audio');
        $audioFile = AudioFile::create([
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

        $this->get(route('teacher.audio.play', $audioFile))->assertRedirect(route('login'));
        $this->actingAs($teacher)->get(route('teacher.audio.play', $audioFile))->assertOk();
        $this->actingAs($outsideTeacher)->get(route('teacher.audio.play', $audioFile))->assertForbidden();
    }

    public function test_assessment_response_stores_audio_file_and_transcript_source(): void
    {
        Storage::fake('local');
        $attempt = $this->attemptWithTaskOneItems();
        $responses = $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item, $index) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => 'A',
                'transcript_source' => 'manual',
                'audio' => $index === 0 ? UploadedFile::fake()->create('letter.webm', 100, 'audio/webm') : null,
                'duration_seconds' => $index === 0 ? 3 : null,
            ])
            ->all();

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-1.store'), ['responses' => $responses])
            ->assertRedirect(route('learner.diagnostic.task-routing'));

        $response = AssessmentTaskResponse::whereNotNull('audio_file_id')->firstOrFail();

        $this->assertSame('manual', $response->transcript_source);
        $this->assertNotNull($response->audioFile);
        Storage::disk('local')->assertExists($response->audioFile->file_path);
    }

    public function test_module_activity_response_stores_audio_file_and_manual_transcript_scores(): void
    {
        Storage::fake('local');
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice']);
        $this->seedModuleActivities($module, 'read_word', 5);
        $selection = app(ModuleActivitySelectionService::class);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $items = $selection->selectPracticeItemsForAttempt($attempt, 'read_word', 5);
        $responses = $items->map(fn ($item, $index) => [
            'module_attempt_item_id' => $item->id,
            'answer' => 'cat',
            'transcript_source' => 'manual',
            'audio' => $index === 0 ? UploadedFile::fake()->create('word.webm', 100, 'audio/webm') : null,
            'duration_seconds' => $index === 0 ? 2 : null,
        ])->all();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']), ['responses' => $responses])
            ->assertRedirect();

        $response = ModuleActivityResponse::whereNotNull('audio_file_id')->firstOrFail();

        $this->assertTrue($response->is_correct);
        $this->assertSame('manual', $response->transcript_source);
        $this->assertNotNull($response->audioFile);
    }

    public function test_blank_transcript_with_audio_is_rejected_before_scoring(): void
    {
        Storage::fake('local');
        $attempt = $this->attemptWithTaskOneItems();
        $responses = $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => 'A',
                'audio' => null,
            ])
            ->all();
        $responses[0]['answer'] = '';
        $responses[0]['audio'] = UploadedFile::fake()->create('blank.webm', 100, 'audio/webm');

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-1.store'), ['responses' => $responses])
            ->assertSessionHasErrors('responses.0.answer');

        $this->assertSame(0, AssessmentTaskResponse::count());
    }

    public function test_mock_speech_to_text_service_resolves_without_real_asr_dependency(): void
    {
        $learner = $this->learner();
        $audioFile = AudioFile::create([
            'learner_id' => $learner->id,
            'disk' => 'local',
            'path' => 'audio/example.webm',
            'file_path' => 'audio/example.webm',
            'mime_type' => 'audio/webm',
            'size_bytes' => 10,
            'file_size' => 10,
            'file_hash' => hash('sha256', 'example'),
            'recording_context' => 'assessment_task',
            'sync_status' => 'synced',
        ]);
        $service = app(SpeechToTextServiceInterface::class);
        $result = $service->transcribe($audioFile);

        $this->assertFalse($result->hasTranscript());
        $this->assertSame('stt_placeholder', $result->source);
        $this->assertFalse($result->metadata['real_asr']);
    }

    public function test_audio_upload_runs_configured_stt_and_stores_transcript_metadata(): void
    {
        Storage::fake('local');
        config(['stt.mock.transcript' => 'cat']);
        $learner = $this->learner();

        $response = $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('word.webm', 100, 'audio/webm'),
                'context_type' => 'module_activity',
            ]);

        $response->assertOk()
            ->assertJsonPath('transcript', 'cat')
            ->assertJsonPath('transcript_source', 'stt_auto');

        $audioFile = AudioFile::firstOrFail();

        $this->assertSame('cat', $audioFile->transcript);
        $this->assertSame(0.5, $audioFile->stt_confidence);
        $this->assertNotNull($audioFile->stt_completed_at);
    }

    public function test_task_one_letter_audio_upload_uses_ai_when_enabled(): void
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
                'request_id' => 'letter-req-1',
                'asr_route' => 'wav2vec2_only',
                'model_family' => 'wav2vec2',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'transcript' => 'l',
                'raw_transcript' => 'l',
                'corrected_transcript' => 'L',
                'displayed_transcript' => 'L',
                'accepted' => true,
                'prompt_type' => 'letter',
                'confidence' => null,
                'similarity_label' => 'exact',
                'warnings' => [],
            ]),
        ]);

        $attempt = $this->attemptWithTaskOneItems();
        $item = $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->firstOrFail();

        $response = $this->withSession(['learner_id' => $attempt->learner_id, 'admin_testing_mode' => true])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('letter.wav', 100, 'audio/wav'),
                'context_type' => 'assessment_task',
                'assessment_attempt_id' => $attempt->id,
                'item_id' => $item->id,
                'task_type' => AssessmentItemSelectionService::TASK_1_LETTER,
                'duration_seconds' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('transcript', 'L')
            ->assertJsonPath('displayed_transcript', 'L')
            ->assertJsonPath('raw_transcript', 'l')
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('transcript_source', 'ai_asr')
            ->assertJsonPath('ai_error', null);

        $audioFile = AudioFile::firstOrFail();

        $this->assertSame('l', $audioFile->ai_transcript);
        $this->assertSame('L', $audioFile->ai_normalized_transcript);
        $this->assertSame('wav2vec2', $audioFile->ai_provider);
        $this->assertSame('models/wav2vec2-readirect-asr-letters-v2', $audioFile->ai_model);
        $this->assertSame('letter-req-1', $audioFile->ai_request_id);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body());

            return $request->url() === 'http://ai.test/analyze-audio'
                && $payload->expected_text === 'A'
                && $payload->prompt_type === 'letter'
                && $payload->activity_type === AssessmentItemSelectionService::TASK_1_LETTER
                && $payload->item_id !== null
                && $payload->learner_id !== null
                && $payload->attempt_id !== null;
        });
    }

    public function test_retry_required_audio_upload_does_not_mark_assessment_item_answered(): void
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
                'request_id' => 'retry-req-1',
                'transcript' => 'tree',
                'raw_transcript' => 'tree',
                'corrected_transcript' => 'tree',
                'displayed_transcript' => 'tree',
                'accepted' => false,
                'prompt_type' => 'word',
                'retry_required' => true,
                'learner_retry_message' => 'Please try again with clearer speech.',
                'warnings' => [],
            ]),
        ]);

        $attempt = $this->attemptWithTaskOneItems();
        $item = $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->firstOrFail();

        $response = $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('letter.wav', 100, 'audio/wav'),
                'context_type' => 'assessment_task',
                'assessment_attempt_id' => $attempt->id,
                'item_id' => $item->id,
                'task_type' => AssessmentItemSelectionService::TASK_1_LETTER,
                'duration_seconds' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_submit', false)
            ->assertJsonPath('retry_required', true)
            ->assertJsonPath('transcript_source', null);

        $this->assertNull($item->refresh()->answered_at);
        $this->assertSame(0, AssessmentTaskResponse::count());
    }

    public function test_passage_audio_upload_persists_long_prompt_and_expected_answer(): void
    {
        Storage::fake('local');
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
        ]);

        $passage = trim(str_repeat(
            'Arthur carried his brother shield through the bright churchyard while many knights watched in quiet wonder. ',
            8
        ));
        $transcript = 'arthur carried his brother shield through the bright churchyard while many knights watched in quiet wonder';

        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'request_id' => 'passage-req-1',
                'asr_route' => 'wav2vec2_only',
                'model_family' => 'wav2vec2',
                'model_used' => 'models/wav2vec2-readirect-asr-letters-v2',
                'transcript' => $transcript,
                'raw_transcript' => $transcript,
                'corrected_transcript' => $transcript,
                'displayed_transcript' => $transcript,
                'accepted' => false,
                'prompt_type' => 'passage',
                'retry_required' => false,
                'uncertain' => false,
                'audio_quality' => ['passed' => true],
                'raw_wer' => 0.2,
                'warnings' => [],
            ]),
        ]);

        $learner = $this->learner();
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'reading_passage',
            'started_at' => now(),
        ]);
        $content = LearningContent::create([
            'content_type' => 'reading_passage',
            'title' => 'Long Passage',
            'prompt' => $passage,
            'payload' => ['source_csv_id' => 'PASS-LONG'],
            'difficulty' => 'easy',
            'is_active' => true,
        ]);
        $item = $attempt->selectedItems()->create([
            'learning_content_id' => $content->id,
            'source_csv_id' => 'PASS-LONG',
            'task_type' => AssessmentItemSelectionService::READING_PASSAGE,
            'sequence' => 1,
            'prompt_snapshot' => [
                'prompt' => $passage,
                'payload' => $content->payload,
                'accepted_answers' => null,
            ],
            'selected_at' => now(),
        ]);

        $this->assertGreaterThan(255, strlen($passage));

        $response = $this->withSession(['learner_id' => $learner->id, 'assessment_attempt_id' => $attempt->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('passage.wav', 120, 'audio/wav'),
                'context_type' => 'passage_reading',
                'assessment_attempt_id' => $attempt->id,
                'duration_seconds' => 22,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_submit', true)
            ->assertJsonPath('transcript', $transcript);

        $stored = AssessmentTaskResponse::where('assessment_attempt_item_id', $item->id)->firstOrFail();

        $this->assertSame($passage, $stored->prompt);
        $this->assertSame($passage, $stored->expected_answer);
        $this->assertSame($transcript, $stored->learner_transcript);
    }

    public function test_assessment_submission_uses_stt_transcript_when_manual_answer_is_blank(): void
    {
        Storage::fake('local');
        config(['stt.mock.transcript' => 'A']);
        $attempt = $this->attemptWithTaskOneItems();
        $responses = $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item, $index) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => $index === 0 ? '' : 'A',
                'transcript_source' => $index === 0 ? 'stt_auto' : 'manual',
                'audio' => $index === 0 ? UploadedFile::fake()->create('letter.webm', 100, 'audio/webm') : null,
                'duration_seconds' => $index === 0 ? 2 : null,
            ])
            ->all();

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-1.store'), ['responses' => $responses])
            ->assertRedirect(route('learner.diagnostic.task-routing'));

        $response = AssessmentTaskResponse::whereNotNull('audio_file_id')->firstOrFail();

        $this->assertSame('A', $response->learner_transcript);
        $this->assertSame('stt_auto', $response->transcript_source);
        $this->assertSame(0.5, $response->stt_confidence);
        $this->assertTrue($response->is_correct);
    }

    public function test_manual_transcript_overrides_stt_for_scoring(): void
    {
        Storage::fake('local');
        config(['stt.mock.transcript' => 'dog']);
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice']);
        $this->seedModuleActivities($module, 'read_word', 5);
        $selection = app(ModuleActivitySelectionService::class);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $items = $selection->selectPracticeItemsForAttempt($attempt, 'read_word', 5);
        $responses = $items->map(fn ($item, $index) => [
            'module_attempt_item_id' => $item->id,
            'answer' => 'cat',
            'transcript_source' => 'manual',
            'audio' => $index === 0 ? UploadedFile::fake()->create('word.webm', 100, 'audio/webm') : null,
            'duration_seconds' => $index === 0 ? 2 : null,
        ])->all();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']), ['responses' => $responses])
            ->assertRedirect();

        $response = ModuleActivityResponse::whereNotNull('audio_file_id')->firstOrFail();

        $this->assertSame('cat', $response->learner_transcript);
        $this->assertSame('manual', $response->transcript_source);
        $this->assertNull($response->stt_confidence);
        $this->assertTrue($response->is_correct);
    }

    public function test_normal_module_audio_submission_ignores_client_manual_transcript(): void
    {
        Storage::fake('local');
        config(['stt.mock.transcript' => 'dog']);
        [$learner, $module] = $this->moduleContext();
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice']);
        $this->seedModuleActivities($module, 'read_word', 5);
        $selection = app(ModuleActivitySelectionService::class);
        $attempt = $selection->startOrResumeModuleAttempt($learner, $module);
        $items = $selection->selectPracticeItemsForAttempt($attempt, 'read_word', 5);
        $responses = $items->map(fn ($item, $index) => [
            'module_attempt_item_id' => $item->id,
            'answer' => 'cat',
            'transcript_source' => 'manual',
            'audio' => UploadedFile::fake()->create('word-'.$index.'.webm', 100, 'audio/webm'),
            'duration_seconds' => 2,
        ])->all();

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
            ->post(route('learner.modules.activity.store', [$module, 'read_word']), ['responses' => $responses])
            ->assertRedirect();

        $response = ModuleActivityResponse::whereNotNull('audio_file_id')->firstOrFail();

        $this->assertSame('dog', $response->learner_transcript);
        $this->assertSame('stt_auto', $response->transcript_source);
        $this->assertFalse($response->is_correct);
    }

    public function test_teacher_can_save_reviewed_transcript_without_rescoring(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $audioFile = AudioFile::create([
            'learner_id' => $learner->id,
            'disk' => 'local',
            'path' => 'audio/review.webm',
            'file_path' => 'audio/review.webm',
            'mime_type' => 'audio/webm',
            'size_bytes' => 10,
            'file_size' => 10,
            'file_hash' => hash('sha256', 'review'),
            'recording_context' => 'module_activity',
            'sync_status' => 'synced',
            'transcript' => 'cot',
            'stt_confidence' => 0.42,
        ]);
        $module = Module::create([
            'sequence' => 1,
            'key' => 'module_1',
            'title' => 'Letter Sounds',
            'description' => 'Practice sounds',
            'is_active' => true,
        ]);
        $attempt = ModuleAttempt::create([
            'learner_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'practice_started',
            'started_at' => now(),
        ]);
        $activity = ModuleActivity::create([
            'module_id' => $module->id,
            'sequence' => 1,
            'activity_type' => 'read_word',
            'title' => 'Read cat',
            'configuration' => ['expected_answer' => 'cat'],
        ]);
        $response = ModuleActivityResponse::create([
            'module_attempt_id' => $attempt->id,
            'module_activity_id' => $activity->id,
            'audio_file_id' => $audioFile->id,
            'response_text' => 'cot',
            'learner_answer' => 'cot',
            'learner_transcript' => 'cot',
            'transcript_source' => 'stt_auto',
            'expected_answer' => 'cat',
            'is_correct' => false,
            'score' => 0,
        ]);
        $audioFile->update(['module_activity_response_id' => $response->id]);

        $this->actingAs($teacher)
            ->put(route('teacher.audio.transcript.update', $audioFile), ['transcript' => 'cat'])
            ->assertRedirect();

        $response->refresh();

        $this->assertSame('cat', $response->learner_transcript);
        $this->assertSame('teacher_review', $response->transcript_source);
        $this->assertFalse($response->is_correct);
        $this->assertSame(0.0, (float) $response->score);
    }

    private function learner(): Learner
    {
        $school = School::create(['name' => 'Audio Test School']);

        return Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('AUD-', false),
            'first_name' => 'Audio',
            'grade_level' => 'Grade 1',
        ]);
    }

    private function teacherWithLearner(): array
    {
        $teacher = $this->userWithRole('teacher');
        $school = School::create(['name' => 'Audio Teacher School']);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'name' => 'Grade 1 Blue',
            'grade_level' => 'Grade 1',
        ]);
        $learner = Learner::create([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'learner_code' => uniqid('AUD-', false),
            'first_name' => 'Audio',
            'last_name' => 'Reader',
            'grade_level' => 'Grade 1',
        ]);

        return [$teacher, $learner];
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => uniqid($role, false).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function attemptWithTaskOneItems(): AssessmentAttempt
    {
        $learner = $this->learner();
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'started_at' => now(),
        ]);

        foreach (range(1, 10) as $index) {
            $content = LearningContent::create([
                'content_type' => 'letter',
                'title' => 'Letter A '.$index,
                'prompt' => 'A',
                'payload' => ['source_csv_id' => 'T1-AUD-'.$index, 'expected_answer' => 'A'],
                'accepted_answers' => ['A', 'a'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
            $attempt->selectedItems()->create([
                'learning_content_id' => $content->id,
                'source_csv_id' => 'T1-AUD-'.$index,
                'task_type' => AssessmentItemSelectionService::TASK_1_LETTER,
                'sequence' => $index,
                'prompt_snapshot' => [
                    'prompt' => 'A',
                    'payload' => $content->payload,
                    'accepted_answers' => $content->accepted_answers,
                ],
                'selected_at' => now(),
            ]);
        }

        return $attempt;
    }

    private function moduleContext(): array
    {
        $learner = $this->learner();
        $module = Module::create([
            'sequence' => 2,
            'key' => 'module_2',
            'title' => 'Word Reading',
            'description' => 'Read words',
            'is_active' => true,
        ]);

        return [$learner, $module];
    }

    private function seedModuleActivities(Module $module, string $activityType, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $content = LearningContent::create([
                'content_type' => 'module_activity',
                'title' => $activityType.' '.$index,
                'prompt' => 'Read the word cat.',
                'payload' => [
                    'source_csv_id' => 'AUD-MOD-'.$index,
                    'module_key' => $module->key,
                    'activity_type' => $activityType,
                    'sequence' => $index,
                    'expected_answer' => 'cat',
                    'points' => 1,
                    'is_mastery_item' => false,
                ],
                'accepted_answers' => ['cat'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            ModuleActivity::create([
                'module_id' => $module->id,
                'learning_content_id' => $content->id,
                'sequence' => $index,
                'activity_type' => $activityType,
                'title' => $content->prompt,
                'configuration' => $content->payload,
            ]);
        }
    }
}
