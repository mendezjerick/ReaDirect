<?php

namespace Tests\Unit;

use App\Services\LearnerListeningModeService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LearnerListeningModeServiceTest extends TestCase
{
    public function test_defaults_to_manual_for_missing_or_unknown_mode(): void
    {
        $service = new LearnerListeningModeService();

        $this->assertSame(LearnerListeningModeService::MANUAL, $service->forLearner(null));
        $this->assertSame(LearnerListeningModeService::MANUAL, $service->normalize(null));
        $this->assertSame(LearnerListeningModeService::MANUAL, $service->normalize('unsupported'));
    }

    public function test_allows_known_listening_modes_only(): void
    {
        $service = new LearnerListeningModeService();

        $this->assertSame(LearnerListeningModeService::MANUAL, $service->validate('manual'));
        $this->assertSame(LearnerListeningModeService::AUTOMATIC_CIEL, $service->validate('automatic_ciel'));

        $this->expectException(InvalidArgumentException::class);
        $service->validate('always_on');
    }
}
