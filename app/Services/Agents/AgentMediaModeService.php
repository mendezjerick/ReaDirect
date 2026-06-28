<?php

namespace App\Services\Agents;

use App\Models\SystemSetting;
use Illuminate\Database\QueryException;

class AgentMediaModeService
{
    public const KEY = 'agent_media_mode';
    public const CHIBI = 'chibi';
    public const DYNAMIC = 'dynamic';

    public function current(): string
    {
        try {
            $value = SystemSetting::query()->where('key', self::KEY)->value('value');
        } catch (QueryException) {
            return self::CHIBI;
        }

        return $this->normalize($value);
    }

    public function set(string $mode, ?int $userId = null): string
    {
        $mode = $this->normalize($mode);

        SystemSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            [
                'value' => $mode,
                'type' => 'string',
                'updated_by' => $userId,
            ],
        );

        return $mode;
    }

    public function normalize(?string $mode): string
    {
        return $mode === self::DYNAMIC ? self::DYNAMIC : self::CHIBI;
    }
}
