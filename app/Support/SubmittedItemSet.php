<?php

namespace App\Support;

use Illuminate\Support\Collection;

class SubmittedItemSet
{
    public static function idsMatch(Collection $items, array $responses, string $submittedKey): bool
    {
        $expected = self::normalizedIds($items->pluck('id')->all());
        $submitted = self::normalizedIds(collect($responses)->pluck($submittedKey)->all());

        return $expected === $submitted;
    }

    public static function normalizedIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
