<?php

namespace Tests\Unit;

use App\Agents\Ciel\CielCoachDecisionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CielCoachDecisionServiceTest extends TestCase
{
    public function test_module_decision_rules_are_deterministic_and_do_not_change_progression(): void
    {
        Http::fake();
        $service = app(CielCoachDecisionService::class);

        $cases = [
            [
                ['instruction_mode' => 'checking'],
                'thinking',
                'ciel.module.processing',
                'PROCESSING',
            ],
            [
                ['retry_required' => true],
                'confused',
                'ciel.module.audio_unclear',
                'AUDIO_UNCLEAR',
            ],
            [
                ['is_correct' => false, 'remaining_attempts' => 1, 'error_type' => 'vowel_confusion'],
                'advise',
                'ciel.module.close_retry.middle_sound',
                'VOWEL_CONFUSION',
            ],
            [
                ['is_correct' => false, 'remaining_attempts' => 1, 'error_type' => 'initial_sound_error'],
                'advise',
                'ciel.module.close_retry.initial_sound',
                'INITIAL_SOUND_ERROR',
            ],
            [
                ['is_correct' => false, 'remaining_attempts' => 1, 'error_type' => 'middle_sound_error'],
                'advise',
                'ciel.module.close_retry.middle_sound',
                'MIDDLE_SOUND_ERROR',
            ],
            [
                ['is_correct' => false, 'remaining_attempts' => 1, 'error_type' => 'final_sound_error'],
                'advise',
                'ciel.module.close_retry.final_sound',
                'FINAL_SOUND_ERROR',
            ],
            [
                ['is_correct' => false, 'remaining_attempts' => 1, 'error_type' => 'omission'],
                'advise',
                'ciel.module.close_retry.omission',
                'OMISSION',
            ],
            [
                ['is_correct' => false, 'remaining_attempts' => 1, 'error_type' => 'insertion'],
                'advise',
                'ciel.module.close_retry.insertion',
                'INSERTION',
            ],
            [
                ['is_correct' => false, 'remaining_attempts' => 1, 'error_type' => 'word_boundary_error'],
                'advise',
                'ciel.module.close_retry.word_boundary',
                'WORD_BOUNDARY_ERROR',
            ],
            [
                ['is_correct' => true],
                'happy',
                'ciel.module.success',
                'CORRECT_RESPONSE',
            ],
            [
                ['is_correct' => true, 'correct_streak' => 3],
                'clap',
                'ciel.module.strong_success',
                'STRONG_PROGRESS',
            ],
            [
                ['is_correct' => true, 'section_completed' => true],
                'clap',
                'ciel.module.section_complete',
                'SECTION_COMPLETE',
            ],
        ];

        foreach ($cases as [$event, $action, $dialogueKey, $reason]) {
            $decision = $service->decide($event + [
                'source_type' => 'module',
                'context' => 'module_practice',
                'learner_id' => 10,
                'attempt_number' => 1,
            ]);

            $this->assertSame($action, $decision['action']);
            $this->assertSame($dialogueKey, $decision['dialogue_key']);
            $this->assertContains($reason, $decision['reason_codes']);
            $this->assertFalse($decision['official_progression_changed']);
            $this->assertSame('miss_ciel', $decision['tts_voice']);
            $this->assertTrue($decision['should_request_tts']);
        }

        Http::assertNothingSent();
    }

    public function test_assessment_contexts_never_return_ciel_tutoring(): void
    {
        $service = app(CielCoachDecisionService::class);

        foreach (['assessment', 'diagnostic_assessment', 'final_assessment'] as $context) {
            $this->assertNull($service->decide([
                'source_type' => 'module',
                'context' => $context,
                'is_correct' => false,
                'remaining_attempts' => 2,
            ]));
        }
    }

    public function test_listening_game_contract_needs_no_asr_recording_or_scoring_fields(): void
    {
        Http::fake();
        $service = app(CielCoachDecisionService::class);
        $cases = [
            ['model_pronunciation', 'talk', 'ciel.game.model_pronunciation', 'PRONUNCIATION_MODEL'],
            ['repeat_after_me', 'talk', 'ciel.game.repeat_after_me', 'LISTEN_AND_REPEAT'],
            ['listen_and_choose', 'advise', 'ciel.game.listen_and_choose', 'LISTENING_CHOICE'],
            ['sound_focus', 'advise', 'ciel.game.sound_focus', 'SOUND_FOCUS'],
            [null, 'talk', 'ciel.game.generic_listen', 'LISTENING_GUIDE'],
        ];

        foreach ($cases as [$mode, $action, $dialogueKey, $reason]) {
            $decision = $service->decide([
                'source_type' => 'readirect_game',
                'context' => 'listening_game_practice',
                'activity_type' => 'listen_word',
                'target_text' => 'cat',
                'target_sound' => 'k',
                'instruction_mode' => $mode,
            ]);

            $this->assertSame($action, $decision['action']);
            $this->assertSame($dialogueKey, $decision['dialogue_key']);
            $this->assertContains($reason, $decision['reason_codes']);
            $this->assertFalse($decision['official_progression_changed']);
            $this->assertNotEmpty($decision['message']);
        }

        Http::assertNothingSent();
    }

    public function test_listening_game_pronunciation_message_uses_only_whitelisted_target_text(): void
    {
        $decision = app(CielCoachDecisionService::class)->decide([
            'source_type' => 'readirect_game',
            'context' => 'listening_game_practice',
            'activity_type' => 'listen_word',
            'target_text' => '<b>cat</b> {model}',
            'instruction_mode' => 'model_pronunciation',
        ]);

        $this->assertStringContainsString('cat', $decision['message']);
        $this->assertStringNotContainsString('<b>', $decision['message']);
        $this->assertStringNotContainsString('{model}', $decision['message']);
    }
}
