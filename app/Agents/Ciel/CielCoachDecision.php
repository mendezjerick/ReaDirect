<?php

namespace App\Agents\Ciel;

final readonly class CielCoachDecision
{
    public function __construct(
        public string $action,
        public string $dialogueKey,
        public string $message,
        public array $reasonCodes,
        public string $context,
        public string $sourceType,
    ) {}

    public function toArray(): array
    {
        return [
            'agent' => 'ciel',
            'action' => $this->action,
            'dialogue_key' => $this->dialogueKey,
            'message' => $this->message,
            'reason_codes' => array_values($this->reasonCodes),
            'context' => $this->context,
            'source_type' => $this->sourceType,
            'tts_voice' => 'miss_ciel',
            'should_request_tts' => true,
            'official_progression_changed' => false,
        ];
    }
}
