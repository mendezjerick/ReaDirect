<?php

namespace Tests\Unit;

use App\Services\Ciel\CielTutorAgentClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CielTutorAgentClientTest extends TestCase
{
    public function test_it_uses_readirect_ia_decision_without_changing_official_progression(): void
    {
        config()->set('readirect.ciel.service_enabled', true);
        config()->set('readirect.ciel.base_url', 'http://127.0.0.1:8003');

        Http::fake([
            'http://127.0.0.1:8003/ia/ciel/decide' => Http::response([
                'ciel_agent' => [
                    'agent' => 'ciel',
                    'mode' => 'focus_teach',
                    'animation' => 'c-advise',
                    'emotion' => 'gentle_correction',
                    'message' => 'B and D sound close. Let\'s listen carefully: B.',
                    'display_target' => 'B',
                    'next_action' => 'listen_then_retry',
                    'lock_interaction' => true,
                    'repeat_after_agent' => true,
                    'teaching_focus' => 'letter_confusion',
                    'focus_mode' => [
                        'enabled' => true,
                        'layout' => 'blank_screen',
                        'target_position' => 'center',
                        'agent_position' => 'bottom',
                        'target_size' => 'large',
                    ],
                    'memory_update' => [
                        'error_key' => 'B_D_CONFUSION',
                        'count_increment' => 1,
                        'current_count' => 2,
                        'learner_id' => '1',
                        'session_id' => 'session-1',
                    ],
                    'official_progression_changed' => false,
                ],
            ]),
        ]);

        $decision = app(CielTutorAgentClient::class)->decide([
            'learner_id' => 1,
            'session_id' => 'session-1',
            'module_type' => 'letter_reading',
            'expected' => 'B',
            'transcript' => 'D',
            'is_correct' => false,
            'attempt' => 2,
            'error_type' => 'letter_confusion',
        ]);

        $this->assertSame('readirect_ia', $decision['decision_source']);
        $this->assertSame('focus_teach', $decision['mode']);
        $this->assertSame('c-advise', $decision['animation']);
        $this->assertTrue($decision['focus_mode']['enabled']);
        $this->assertFalse($decision['official_progression_changed']);
    }

    public function test_invalid_remote_animation_uses_safe_deterministic_fallback(): void
    {
        config()->set('readirect.ciel.service_enabled', true);
        Http::fake([
            '*' => Http::response([
                'ciel_agent' => [
                    'agent' => 'ciel',
                    'mode' => 'soft_retry',
                    'animation' => 'thinking',
                ],
            ]),
        ]);

        $decision = app(CielTutorAgentClient::class)->decide([
            'learner_id' => 1,
            'session_id' => 'session-1',
            'expected' => 'B',
            'transcript' => 'D',
            'is_correct' => false,
            'attempt' => 1,
        ]);

        $this->assertSame('laravel_deterministic_fallback', $decision['decision_source']);
        $this->assertSame('c-confused', $decision['animation']);
        $this->assertNotSame('c-congrats', $decision['animation']);
    }

    public function test_normal_correct_fallback_never_uses_congrats(): void
    {
        config()->set('readirect.ciel.service_enabled', false);

        $decision = app(CielTutorAgentClient::class)->decide([
            'learner_id' => 1,
            'session_id' => 'session-1',
            'expected' => 'B',
            'transcript' => 'B',
            'is_correct' => true,
            'attempt' => 1,
        ]);

        $this->assertSame('correct_praise', $decision['mode']);
        $this->assertSame('c-clap', $decision['animation']);
        $this->assertNotSame('c-congrats', $decision['animation']);
    }

    public function test_it_forwards_optional_automatic_listening_context_to_ia(): void
    {
        config()->set('readirect.ciel.service_enabled', true);
        config()->set('readirect.ciel.base_url', 'http://127.0.0.1:8003');

        Http::fake([
            'http://127.0.0.1:8003/ia/ciel/decide' => Http::response([
                'ciel_agent' => [
                    'agent' => 'ciel',
                    'mode' => 'soft_retry',
                    'animation' => 'c-confused',
                    'emotion' => 'encouraging_retry',
                    'message' => 'Try again.',
                    'display_target' => 'CAT',
                    'next_action' => 'retry',
                    'focus_mode' => ['enabled' => false],
                ],
            ]),
        ]);

        app(CielTutorAgentClient::class)->decide([
            'learner_id' => 1,
            'session_id' => 'session-1',
            'expected' => 'cat',
            'transcript' => 'cap',
            'is_correct' => false,
            'attempt' => 1,
            'listening_mode' => 'automatic_ciel',
            'session_mode' => 'automatic_ciel',
            'automatic_session_id' => 'auto-session-1',
            'current_agent_state' => 'processing',
            'silence_timeout' => false,
            'chunk_id' => 'chunk-1',
        ]);

        Http::assertSent(fn ($request): bool => $request['listening_mode'] === 'automatic_ciel'
            && $request['session_mode'] === 'automatic_ciel'
            && $request['automatic_session_id'] === 'auto-session-1'
            && $request['current_agent_state'] === 'processing'
            && $request['silence_timeout'] === false
            && $request['chunk_id'] === 'chunk-1');
    }
}
