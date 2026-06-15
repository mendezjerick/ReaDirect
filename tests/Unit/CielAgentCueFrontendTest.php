<?php

namespace Tests\Unit;

use Tests\TestCase;

class CielAgentCueFrontendTest extends TestCase
{
    public function test_module_pages_prefer_intelligent_ciel_payload_with_legacy_fallback(): void
    {
        foreach ([
            resource_path('js/Pages/Learner/Modules/ModuleActivity.vue'),
            resource_path('js/Pages/Learner/Modules/ModuleMasteryCheck.vue'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString("result.ciel_agent?.agent === 'ciel'", $source);
            $this->assertStringContainsString('cielAgent?.animation', $source);
            $this->assertStringContainsString('cielAgent?.message', $source);
            $this->assertStringContainsString('cielAgent?.focus_mode?.enabled', $source);
            $this->assertStringContainsString("result.agent_cue?.agent === 'ciel'", $source);
            $this->assertStringContainsString('agentCue?.action', $source);
            $this->assertStringContainsString('agentCue?.message', $source);
        }
    }

    public function test_exact_ciel_thinking_labels_map_to_exact_media_variants(): void
    {
        $interaction = file_get_contents(resource_path('js/utils/agentInteraction.js'));
        $media = file_get_contents(resource_path('js/utils/agentMedia.js'));

        foreach ([1, 2, 3] as $variant) {
            $this->assertStringContainsString("c_thinking_{$variant}: 'thinking_{$variant}'", $interaction);
            $this->assertStringContainsString("thinking_{$variant}: Object.freeze([video('videos/Ciel/c-thinking-{$variant}.mp4')])", $media);
        }
    }
}
