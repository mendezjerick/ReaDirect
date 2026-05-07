<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;

class DeveloperReinforcementModeService
{
    public const KEY = 'developer_reinforcement_mode';

    public function enabled(): bool
    {
        $setting = SystemSetting::query()->where('key', self::KEY)->first();

        return filter_var($setting?->value ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled, ?User $user = null): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            [
                'value' => $enabled ? 'true' : 'false',
                'type' => 'boolean',
                'updated_by' => $user?->id,
            ]
        );
    }

    public function canUse(?User $user): bool
    {
        return (bool) $user?->hasAnyRole(['system_admin', 'school_admin']);
    }

    public function payloadFor(?User $user): array
    {
        $enabled = $this->enabled() && $this->canUse($user);

        return [
            'developer_reinforcement_enabled' => $enabled,
            'developer_user_role' => $enabled ? $this->developerRole($user) : null,
            'developer_user_id' => $enabled ? ($user?->email ?: $user?->id) : null,
        ];
    }

    public function statusFor(?User $user): array
    {
        return [
            'enabled' => $this->enabled(),
            'visible' => $this->canUse($user),
            'warning' => 'Developer reinforcement mode writes incorrect ASR outputs into correction memory. Turn this off before normal learner testing.',
        ];
    }

    private function developerRole(?User $user): string
    {
        if ($user?->hasRole('system_admin')) {
            return 'admin';
        }

        if ($user?->hasRole('school_admin')) {
            return 'admin';
        }

        return 'learner';
    }
}
