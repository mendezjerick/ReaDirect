<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AgentSpeakerPanelStructureTest extends TestCase
{
    public function test_agent_media_registry_uses_the_static_asset_base_url_and_exact_filenames(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = file_get_contents($root.'/resources/js/utils/agentMedia.js');

        $this->assertStringContainsString('VITE_REA_AGENT_ASSET_BASE_URL', $registry);
        $this->assertStringContainsString("'/ia-assets'", $registry);
        $this->assertStringContainsString('videos/Ciel/c-idle.mp4', $registry);
        $this->assertStringContainsString('videos/Ciel/c-thinking-3.mp4', $registry);
        $this->assertStringContainsString('videos/Ciel/c-happy.mp4', $registry);
        $this->assertStringContainsString('videos/Ciel/c-confused.mp4', $registry);
        $this->assertStringContainsString('videos/Ciel/c-advise.mp4', $registry);
        $this->assertStringContainsString('videos/Ciel/c-clap.mp4', $registry);
        $this->assertStringContainsString('videos/Ciel/c-congrats.mp4', $registry);
        $this->assertStringContainsString('videos/Ciel/c-talk.mp4', $registry);
        $this->assertStringContainsString('videos/Vivian/v-idle.mp4', $registry);
        $this->assertStringContainsString('videos/Vivian/v-talk.mp4', $registry);
        $this->assertStringContainsString('videos/Vivian/v-think.mp4', $registry);
        $this->assertStringContainsString('videos/Vivian/v-congrats.mp4', $registry);
        $this->assertStringContainsString('videos/Estelle/e-idle.mp4', $registry);
        $this->assertStringContainsString('videos/Estelle/e-talk.mp4', $registry);
        $this->assertStringContainsString('videos/Estelle/e-results-2.mp4', $registry);
        $this->assertStringContainsString('videos/Estelle/e-congrats.mp4', $registry);
        $this->assertStringNotContainsString('videos/Vivian/v-thinking-2.mp4', $registry);
    }

    public function test_agent_video_player_is_non_interrupting_and_has_no_queue(): void
    {
        $root = dirname(__DIR__, 2);
        $player = file_get_contents($root.'/resources/js/Components/Agents/AgentVideoPlayer.vue');
        $component = file_get_contents($root.'/resources/js/Components/Learner/AgentSpeakerPanel.vue');

        $this->assertStringContainsString('if (isBusy.value)', $player);
        $this->assertStringContainsString('return false', $player);
        $this->assertStringContainsString('interactionReady', $player);
        $this->assertStringContainsString('agent-media-interaction--ready', $player);
        $this->assertStringContainsString('@ended="handleVideoEnded"', $player);
        $this->assertStringContainsString('resetInteraction()', $player);
        $this->assertStringContainsString('getAgentFallbackMedia', $player);
        $this->assertStringNotContainsString('pending', strtolower($player));
        $this->assertStringNotContainsString('queue', strtolower($player));
        $this->assertStringContainsString('AgentVideoPlayer', $component);
    }

    public function test_congrats_media_is_enabled_only_in_final_completion_views(): void
    {
        $root = dirname(__DIR__, 2);
        $summary = file_get_contents($root.'/resources/js/Pages/Learner/FinalAssessment/Summary.vue');
        $completion = file_get_contents($root.'/resources/js/Pages/Learner/Completion.vue');
        $activity = file_get_contents($root.'/resources/js/Pages/Learner/Modules/ModuleActivity.vue');

        $this->assertStringContainsString("ref('results')", $summary);
        $this->assertStringContainsString("agentAction.value = 'congrats'", $summary);
        $this->assertStringContainsString('allow-congrats', $summary);
        $this->assertStringContainsString('allow-congrats', $completion);
        $this->assertStringNotContainsString('allow-congrats', $activity);
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

    public function test_agent_speaker_tts_component_uses_kokoro_audio_or_text_only_fallback(): void
    {
        $root = dirname(__DIR__, 2);
        $componentPath = $root.'/resources/js/Components/Agents/AgentSpeakerTTS.vue';

        $this->assertFileExists($componentPath);

        $component = file_get_contents($componentPath);

        $this->assertStringContainsString('new Audio', $component);
        $this->assertStringContainsString('audioUrl', $component);
        $this->assertStringContainsString('Kokoro voice is unavailable right now.', $component);
        $this->assertStringContainsString('speakingStart', $component);
        $this->assertStringContainsString('speakingEnd', $component);
        $this->assertStringNotContainsString('SpeechSynthesisUtterance', $component);
        $this->assertStringNotContainsString('speechSynthesis.speak', $component);
        $this->assertStringNotContainsString('voiceschanged', $component);
        $this->assertStringNotContainsString('Web Speech API not supported.', $component);
    }

    public function test_stop_agent_audio_utility_dispatches_kokoro_stop_events(): void
    {
        $utility = file_get_contents(dirname(__DIR__, 2).'/resources/js/utils/stopAgentAudio.js');

        $this->assertStringContainsString('export function stopAllAgentAudio', $utility);
        $this->assertStringContainsString('readirect:stop-agent-audio', $utility);
        $this->assertStringContainsString('readirect:stop-agent-speech', $utility);
        $this->assertStringNotContainsString('speechSynthesis', $utility);
    }

    public function test_learner_audio_playback_stops_agent_voice(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/Learner/AudioRecorder.vue');

        $this->assertStringContainsString('stopAllAgentAudio', $component);
        $this->assertStringContainsString('stopAgentAudioForPlayback', $component);
        $this->assertStringContainsString('@play="stopAgentAudioForPlayback"', $component);
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

    public function test_automatic_ciel_listening_is_scoped_to_supported_module_pages(): void
    {
        $root = dirname(__DIR__, 2);
        $dashboard = file_get_contents($root.'/resources/js/Pages/Learner/Dashboard.vue');
        $activity = file_get_contents($root.'/resources/js/Pages/Learner/Modules/ModuleActivity.vue');
        $mastery = file_get_contents($root.'/resources/js/Pages/Learner/Modules/ModuleMasteryCheck.vue');
        $composable = file_get_contents($root.'/resources/js/Composables/useAutomaticCielListeningSession.js');

        $this->assertStringContainsString('/learner/listening-mode', $dashboard);
        $this->assertStringContainsString('Automatic Ciel Listening Mode', $dashboard);
        $this->assertStringContainsString('AutomaticCielListeningPanel', $activity);
        $this->assertStringContainsString('v-if="isAutomaticListeningMode"', $activity);
        $this->assertStringContainsString('AutomaticCielListeningPanel', $mastery);
        $this->assertStringContainsString('v-if="isAutomaticListeningMode"', $mastery);
        $this->assertStringContainsString('getUserMedia', $composable);
        $this->assertStringContainsString('MediaRecorder', $composable);
        $this->assertStringContainsString('silenceDurationBeforeSubmitMs', $composable);
    }
}
