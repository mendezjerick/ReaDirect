<?php

namespace App\Services;

class RecommendationService
{
    public function __construct(private readonly ModulePlacementService $placementService)
    {
    }

    public function recommendFromAssessment(string $crlaClassification, string $readingClassification): array
    {
        return $this->placementService->place($crlaClassification, $readingClassification);
    }
}
