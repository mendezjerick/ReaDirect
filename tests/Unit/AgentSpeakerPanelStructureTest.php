<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AgentSpeakerPanelStructureTest extends TestCase
{
    public function test_agent_assets_were_copied_to_public_paths(): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertFileExists($root.'/public/assets/agents/assessment/idle.png');
        $this->assertFileExists($root.'/public/assets/agents/coach_feedback/idle.png');
        $this->assertFileExists($root.'/public/assets/agents/evaluator/idle.png');
    }

    public function test_agent_speaker_panel_supports_required_agents_and_fallbacks(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Components/Learner/AgentSpeakerPanel.vue');

        $this->assertStringContainsString('Assessment Agent', $component);
        $this->assertStringContainsString('Coach + Feedback Agent', $component);
        $this->assertStringContainsString('Evaluator / Recommendation Agent', $component);
        $this->assertStringContainsString('handleImageError', $component);
        $this->assertStringContainsString("displayMode.value = 'idle'", $component);
        $this->assertStringContainsString("displayMode.value = 'placeholder'", $component);
        $this->assertStringContainsString('object-contain', $component);
    }
}
