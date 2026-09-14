<?php

namespace App\Services;

use App\Models\Setting;

final class SidebarAppearance
{
    public const KEY = 'sidebar_appearance';

    public function current(): array
    {
        // Read the single small record directly: cross-device updates must not
        // depend on a process-local memo or a stale shared settings cache.
        $stored = json_decode((string) Setting::query()->where('key', self::KEY)->value('value'), true);
        if (is_array($stored)
            && in_array($stored['theme'] ?? null, ['light', 'dark'], true)
            && array_key_exists($stored['color'] ?? '', config('sidebar.palettes'))) {
            return ['theme' => $stored['theme'], 'color' => $stored['color']];
        }

        $legacy = Setting::query()->where('key', 'sidebar_theme')->value('value') ?: 'white';
        return self::fromLegacy($legacy);
    }

    public static function fromLegacy(string $legacy): array
    {
        return ['theme' => $legacy === 'white' ? 'light' : 'dark', 'color' => match ($legacy) {
            'white' => 'classic', 'navy' => 'navy', 'espresso' => 'bronze',
            'steel' => 'petrol', 'midnight' => 'plum', default => 'slate',
        }];
    }

    public function save(array $appearance): array
    {
        // One update writes the complete pair atomically; never split theme and
        // color across records that another panel could observe half-written.
        Setting::put(self::KEY, json_encode($appearance, JSON_THROW_ON_ERROR), 'appearance');
        return $appearance;
    }
}
