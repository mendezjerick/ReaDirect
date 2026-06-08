<?php

namespace App\Services\Agents;

use App\Support\AgentIdentity;

class MissCielFeedbackService
{
    public function __construct(private readonly MissCielScriptedFeedback $scripts) {}

    public function feedback(array $context): array
    {
        $category = $context['feedback_category'] ?? $this->scripts->categoryFor($context);

        return [
            'agent' => AgentIdentity::MISS_CIEL,
            'display_name' => AgentIdentity::displayName(AgentIdentity::MISS_CIEL),
            'message' => $this->scripts->forCategory($category),
            'source' => 'deterministic_script',
            'fallback_used' => false,
            'fallback_reason' => null,
            'latency_ms' => null,
        ];
    }
}
