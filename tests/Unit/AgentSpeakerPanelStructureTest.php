<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AgentSpeakerPanelStructureTest extends TestCase
{
    public function test_agent_assets_were_copied_to_public_paths(): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertFileExists($root.'/public/assets/agents/assessment/idle.png');
        $this->assertFileExists($root.'/public/assets/agents/assessment/idle.webm');
        $this->assertFileExists($root.'/public/assets/agents/coach_feedback/idle.png');
        $this->assertFileExists($root.'/public/assets/agents/evaluator/idle.png');
    }

    public function test_agent_speaker_panel_supports_required_agents_and_fallbacks(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/Learner/AgentSpeakerPanel.vue');

        $this->assertStringContainsString('Miss Vivian', $component);
        $this->assertStringContainsString('Miss Ciel', $component);
        $this->assertStringContainsString('Miss Estelle', $component);
        $this->assertStringContainsString('handleImageError', $component);
        $this->assertStringContainsString('idle.webm', $component);
        $this->assertStringContainsString('type="video/webm"', $component);
        $this->assertStringContainsString("displayMode.value = 'idle'", $component);
        $this->assertStringContainsString("displayMode.value = 'placeholder'", $component);
        $this->assertStringContainsString('object-contain', $component);
    }

    public function test_agent_speaker_panel_integrates_tts_controls(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/Learner/AgentSpeakerPanel.vue');

        $this->assertStringContainsString('AgentSpeakerTTS', $component);
        $this->assertStringContainsString('isSpeaking', $component);
        $this->assertStringContainsString('readirect-agent-tts-muted', $component);
        $this->assertStringContainsString('/agent-voice/synthesize', $component);
        $this->assertStringContainsString('naturalAudioUrl', $component);
        $this->assertStringContainsString('Unmute agent voice', $component);
        $this->assertStringContainsString('Replay agent message', $component);
    }

    public function test_agent_speaker_tts_component_uses_web_speech_api_and_agent_voice_mapping(): void
    {
        $root = dirname(__DIR__, 2);
        $componentPath = $root.'/resources/js/Components/Agents/AgentSpeakerTTS.vue';

        $this->assertFileExists($componentPath);

        $component = file_get_contents($componentPath);

        $this->assertStringContainsString('speechSynthesis', $component);
        $this->assertStringContainsString('new Audio', $component);
        $this->assertStringContainsString('audioUrl', $component);
        $this->assertStringContainsString('SpeechSynthesisUtterance', $component);
        $this->assertStringContainsString('voiceschanged', $component);
        $this->assertStringContainsString('assessment', $component);
        $this->assertStringContainsString('coach_feedback', $component);
        $this->assertStringContainsString('evaluator', $component);
        $this->assertStringContainsString('speakingStart', $component);
        $this->assertStringContainsString('speakingEnd', $component);
        $this->assertStringContainsString('Web Speech API not supported.', $component);
    }

    public function test_stop_agent_audio_utility_stops_browser_and_natural_audio(): void
    {
        $utility = file_get_contents(dirname(__DIR__, 2).'/resources/js/utils/stopAgentAudio.js');

        $this->assertStringContainsString('speechSynthesis.cancel', $utility);
        $this->assertStringContainsString('readirect:stop-agent-audio', $utility);
        $this->assertStringContainsString('readirect:stop-agent-speech', $utility);
    }
}
