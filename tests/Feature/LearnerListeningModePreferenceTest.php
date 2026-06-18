<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\School;
use App\Services\LearnerListeningModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearnerListeningModePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_defaults_to_manual_and_can_save_automatic_mode(): void
    {
        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Dashboard')
                ->where('listeningMode.current', LearnerListeningModeService::MANUAL)
                ->where('listeningMode.default', LearnerListeningModeService::MANUAL)
                ->where('listeningMode.automatic_mode_available', true)
            );

        $this->withSession(['learner_id' => $learner->id])
            ->patch(route('learner.listening-mode.update'), [
                'listening_mode' => LearnerListeningModeService::AUTOMATIC_CIEL,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('learner_preferences', [
            'learner_id' => $learner->id,
            'listening_mode' => LearnerListeningModeService::AUTOMATIC_CIEL,
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('listeningMode.current', LearnerListeningModeService::AUTOMATIC_CIEL)
            );
    }

    public function test_invalid_listening_mode_is_rejected(): void
    {
        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->from(route('learner.dashboard'))
            ->patch(route('learner.listening-mode.update'), [
                'listening_mode' => 'always_on',
            ])
            ->assertRedirect(route('learner.dashboard'))
            ->assertSessionHasErrors('listening_mode');

        $this->assertDatabaseMissing('learner_preferences', [
            'learner_id' => $learner->id,
            'listening_mode' => 'always_on',
        ]);
    }

    private function learner(): Learner
    {
        $school = School::create(['name' => 'Listening Mode School']);

        return Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('LM-', false),
            'first_name' => 'Reader',
            'grade_level' => 'Grade 1',
        ]);
    }
}
