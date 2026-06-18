<?php

namespace Tests\Unit;

use App\Services\AutomaticListeningChunkGuard;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutomaticListeningChunkGuardTest extends TestCase
{
    public function test_claim_rejects_duplicate_chunks_for_same_learner_session_and_chunk(): void
    {
        Cache::flush();

        $guard = app(AutomaticListeningChunkGuard::class);

        $this->assertTrue($guard->claim(10, 'session-1', 'chunk-1'));
        $this->assertFalse($guard->claim(10, 'session-1', 'chunk-1'));
        $this->assertTrue($guard->claim(10, 'session-1', 'chunk-2'));
        $this->assertTrue($guard->claim(10, 'session-2', 'chunk-1'));
        $this->assertTrue($guard->claim(11, 'session-1', 'chunk-1'));
    }
}
