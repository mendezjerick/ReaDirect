<?php

namespace App\Services\AI;

use App\Models\ModuleAttemptItem;
use Illuminate\Support\Collection;

class CandidateItemPayloadBuilder
{
    public function fromModuleAttemptItems(Collection $items): array
    {
        return $items->map(function (ModuleAttemptItem $item): array {
            $snapshot = $item->prompt_snapshot ?? [];
            $payload = $snapshot['payload'] ?? [];
            $enrichment = $payload['enrichment'] ?? [];

            return [
                'prompt_id' => $item->source_csv_id,
                'module_key' => $payload['module_key'] ?? null,
                'activity_type' => $item->activity_type,
                'prompt_text' => $snapshot['prompt'] ?? '',
                'expected_text' => $payload['expected_answer'] ?? $payload['target_word'] ?? null,
                'accepted_answers' => $snapshot['accepted_answers'] ?? [],
                'error_focus' => $enrichment['error_focus'] ?? null,
                'skill_tag' => $enrichment['skill_tag'] ?? null,
                'target_phoneme' => $enrichment['target_phoneme'] ?? null,
                'difficulty_level' => $enrichment['difficulty_level'] ?? $snapshot['difficulty'] ?? null,
                'is_active' => true,
                'needs_manual_review' => filter_var($enrichment['needs_manual_review'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        })->values()->all();
    }
}
