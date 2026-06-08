<?php

namespace Tests\Unit;

use Tests\TestCase;

class CielAgentCueFrontendTest extends TestCase
{
    public function test_module_pages_prefer_resolved_ciel_agent_cues(): void
    {
        foreach ([
            resource_path('js/Pages/Learner/Modules/ModuleActivity.vue'),
            resource_path('js/Pages/Learner/Modules/ModuleMasteryCheck.vue'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString("result.agent_cue?.agent === 'ciel'", $source);
            $this->assertStringContainsString('agentCue?.action', $source);
            $this->assertStringContainsString('agentCue?.message', $source);
        }
    }
}
