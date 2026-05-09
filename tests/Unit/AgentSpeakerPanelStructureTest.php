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

    public function test_agent_speaker_tts_component_uses_kokoro_audio_then_browser_fallback(): void
    {
        $root = dirname(__DIR__, 2);
        $componentPath = $root.'/resources/js/Components/Agents/AgentSpeakerTTS.vue';

        $this->assertFileExists($componentPath);

        $component = file_get_contents($componentPath);

        $this->assertStringContainsString('new Audio', $component);
        $this->assertStringContainsString('audioUrl', $component);
        $this->assertStringContainsString('SpeechSynthesisUtterance', $component);
        $this->assertStringContainsString('speechSynthesis.speak', $component);
        $this->assertStringContainsString('Browser voice is unavailable right now.', $component);
        $this->assertStringContainsString('speakingStart', $component);
        $this->assertStringContainsString('speakingEnd', $component);
        $this->assertStringNotContainsString('voiceschanged', $component);
        $this->assertStringNotContainsString('Web Speech API not supported.', $component);
    }

    public function test_stop_agent_audio_utility_cleans_up_legacy_browser_and_kokoro_audio(): void
    {
        $utility = file_get_contents(dirname(__DIR__, 2).'/resources/js/utils/stopAgentAudio.js');

        $this->assertStringContainsString('speechSynthesis.cancel', $utility);
        $this->assertStringContainsString('readirect:stop-agent-audio', $utility);
        $this->assertStringContainsString('readirect:stop-agent-speech', $utility);
    }

    public function test_module_overview_supports_miss_ciel_hover_explanations(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Pages/Learner/Modules/ModuleOverview.vue');

        $this->assertStringContainsString('lessonBoxes', $component);
        $this->assertStringContainsString('explainLesson', $component);
        $this->assertStringContainsString('transitionMessages', $component);
        $this->assertStringContainsString('Let us slow down and choose one lesson at a time.', $component);
        $this->assertStringContainsString('Are you ready to choose one lesson without rushing?', $component);
        $this->assertStringContainsString('transitionDelayFor', $component);
        $this->assertStringContainsString('@mouseenter="explainLesson(lesson)"', $component);
        $this->assertStringContainsString('@focus="explainLesson(lesson)"', $component);
        $this->assertStringContainsString('@click="explainLesson(lesson)"', $component);
        $this->assertStringNotContainsString('Try', $component);
        $this->assertStringNotContainsString('mastery_check', $component);
        $this->assertStringContainsString('Back to Learner Dashboard', $component);
    }

    public function test_module_activity_pages_include_safe_dashboard_return(): void
    {
        $root = dirname(__DIR__, 2);
        $activity = file_get_contents($root.'/resources/js/Pages/Learner/Modules/ModuleActivity.vue');
        $mastery = file_get_contents($root.'/resources/js/Pages/Learner/Modules/ModuleMasteryCheck.vue');

        foreach ([$activity, $mastery] as $component) {
            $this->assertStringContainsString('returnToDashboard', $component);
            $this->assertStringContainsString('See you next time!', $component);
            $this->assertStringContainsString('readirect:stop-agent-speech', $component);
            $this->assertStringContainsString("window.location.href = '/learner/dashboard'", $component);
        }
    }
}
