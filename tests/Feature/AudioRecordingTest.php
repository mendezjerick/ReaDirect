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
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AssessmentItemSelectionService;
use App\Services\ModuleActivitySelectionService;
use App\Services\SpeechToText\SpeechToTextServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id])
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

        $this->withSession(['learner_id' => $learner->id, 'module_attempt_id' => $attempt->id])
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

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id])
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
