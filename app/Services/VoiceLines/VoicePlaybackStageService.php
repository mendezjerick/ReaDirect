<?php

namespace App\Services\VoiceLines;

use App\Models\SystemSetting;
use Illuminate\Database\QueryException;

class VoicePlaybackStageService
{
    public const KEY = 'voice_playback_stage';
    public const EXPRESSIVE = 'reference_style';
    public const KOKORO = 'kokoro_identity';

    public function current(): string
    {
        try {
            $value = SystemSetting::query()->where('key', self::KEY)->value('value');
        } catch (QueryException) {
            return $this->defaultStage();
        }

        return $this->normalize($value ?: $this->defaultStage());
    }

    public function set(string $stage, ?int $userId = null): string
    {
        $stage = $this->normalize($stage);

        SystemSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            [
                'value' => $stage,
                'type' => 'string',
                'updated_by' => $userId,
            ],
        );

        return $stage;
    }

    public function normalize(?string $stage): string
    {
        return $stage === self::KOKORO ? self::KOKORO : self::EXPRESSIVE;
    }

    private function defaultStage(): string
    {
        return self::EXPRESSIVE;
    }
}
