<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AutomaticListeningChunkGuard
{
    private const TTL_SECONDS = 7200;

    public function claim(int|string $learnerId, string $sessionId, string $chunkId): bool
    {
        return Cache::add($this->key($learnerId, $sessionId, $chunkId), true, self::TTL_SECONDS);
    }

    private function key(int|string $learnerId, string $sessionId, string $chunkId): string
    {
        return 'automatic-ciel-chunk:'.sha1($learnerId.'|'.$sessionId.'|'.$chunkId);
    }
}
