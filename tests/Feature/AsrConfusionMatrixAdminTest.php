<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ASR\AsrConfusionFixtureService;
use App\Services\ASR\AsrConfusionManualHistoryService;
use App\Services\ASR\AsrConfusionMatrixRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AsrConfusionMatrixAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_confusion_matrix_page_is_admin_only(): void
    {
        $student = $this->userWithRole('student');

        $this->actingAs($student)
            ->get(route('admin.confusion-matrix.index'))
            ->assertForbidden();

        $this->mock(AsrConfusionFixtureService::class, function ($mock): void {
            $mock->shouldReceive('loadManifest')->andReturn([
                'generated_at' => '2026-06-25T00:00:00Z',
                'summary' => ['total_fixtures' => 1],
            ]);
            $mock->shouldReceive('manifestPath')->andReturn(storage_path('app/asr_confusion_fixtures/manifest.json'));
            $mock->shouldReceive('latestResults')->andReturn(null);
            $mock->shouldReceive('fixtureOptions')->andReturn([]);
        });
        $this->mock(AsrConfusionManualHistoryService::class, function ($mock): void {
            $mock->shouldReceive('latest')->andReturn([]);
        });

        $this->actingAs($this->userWithRole('system_admin'))
            ->get(route('admin.confusion-matrix.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ConfusionMatrix')
                ->where('manifest.summary.total_fixtures', 1)
                ->where('manualHistory', [])
                ->where('routes.manualHistory', route('admin.confusion-matrix.manual-history'))
                ->where('routes.runFixture', route('admin.confusion-matrix.run-fixture')));
    }

    public function test_manual_fixture_endpoint_returns_complete_test_result(): void
    {
        $fixture = [
            'category' => 'diagnostic',
            'task' => 'task_1a',
            'item_key' => 'T1-L001',
            'fixture_type' => 'wrong_word',
        ];
        $result = [
            'category' => 'diagnostic',
            'task' => 'task_1a',
            'item_key' => 'T1-L001',
            'expected_answer' => 'A',
            'fixture_type' => 'wrong_word',
            'spoken_text' => 'justice',
            'recording_accepted' => true,
            'asr_raw_output' => 'justice',
            'normalized_output' => 'justice',
            'final_correctness_result' => false,
            'expected_correctness' => false,
            'confusion_matrix_result' => 'TN',
            'failure_reason' => null,
            'wrong_audible_rejected_as_invalid' => false,
            'scoring_debug' => [
                'true_gop_score' => null,
                'beam_search' => true,
                'expected_centric_score' => 0.0,
            ],
        ];

        $this->mock(AsrConfusionFixtureService::class, function ($mock) use ($fixture): void {
            $mock->shouldReceive('findFixture')
                ->with('diagnostic', 'task_1a', 'T1-L001', 'wrong_word')
                ->andReturn($fixture);
        });
        $this->mock(AsrConfusionMatrixRunner::class, function ($mock) use ($fixture, $result): void {
            $mock->shouldReceive('runFixture')->with($fixture)->andReturn($result);
        });
        $this->mock(AsrConfusionManualHistoryService::class, function ($mock) use ($result): void {
            $mock->shouldReceive('fromManualFixture')->with($result, \Mockery::type(User::class))->andReturn([
                'id' => 'history-1',
                'source_mode' => 'manual_fixture',
                'confusion_matrix_result' => 'True Negative',
            ]);
        });

        $this->actingAs($this->userWithRole('system_admin'))
            ->postJson(route('admin.confusion-matrix.run-fixture'), [
                'category' => 'diagnostic',
                'task' => 'task_1a',
                'item_key' => 'T1-L001',
                'fixture_type' => 'wrong_word',
            ])
            ->assertOk()
            ->assertJsonPath('result.recording_accepted', true)
            ->assertJsonPath('result.final_correctness_result', false)
            ->assertJsonPath('result.confusion_matrix_result', 'TN')
            ->assertJsonPath('result.wrong_audible_rejected_as_invalid', false)
            ->assertJsonPath('result.scoring_debug.beam_search', true)
            ->assertJsonPath('historyRow.source_mode', 'manual_fixture');
    }

    public function test_manual_history_endpoint_returns_true_sandbox_rows(): void
    {
        $this->mock(AsrConfusionManualHistoryService::class, function ($mock): void {
            $mock->shouldReceive('latest')->with('true_sandbox')->andReturn([
                [
                    'id' => 'history-true-sandbox',
                    'source_mode' => 'true_sandbox',
                    'source_label' => 'True Sandbox',
                    'confusion_matrix_result' => 'True Negative',
                ],
            ]);
        });

        $this->actingAs($this->userWithRole('system_admin'))
            ->getJson(route('admin.confusion-matrix.manual-history', ['source' => 'true_sandbox']))
            ->assertOk()
            ->assertJsonPath('manualHistory.0.source_label', 'True Sandbox')
            ->assertJsonPath('manualHistory.0.confusion_matrix_result', 'True Negative');
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
}
