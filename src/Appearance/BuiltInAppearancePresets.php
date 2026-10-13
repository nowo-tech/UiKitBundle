<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * Generic presets shipped with the kit. Hosts add their own (config `presets` or a tagged
 * {@see AppearancePresetProviderInterface}) and may restrict these with `builtin_presets`.
 */
final class BuiltInAppearancePresets
{
    /** Ids in display order. */
    public const IDS = ['ocean', 'slate', 'sandstone', 'midnight', 'obsidian', 'aurora', 'ember'];

    private const DATA = [
        'ocean' => ['light', ['#0e7490', '#155e75', '#b42318', '#b54708', '#f0f9fb', '#0c3a45', '#ffffff'], ['#22d3ee', '#67e8f9', '#f97066', '#fdb022', '#071318', '#e0f4f8', '#0f1c22']],
        'slate' => ['light', ['#475569', '#334155', '#b42318', '#b54708', '#f1f5f9', '#0f172a', '#ffffff'], ['#94a3b8', '#cbd5e1', '#f97066', '#fdb022', '#0b1220', '#e2e8f0', '#111827']],
        'sandstone' => ['light', ['#9a6700', '#7a5200', '#b42318', '#b54708', '#f7f4ef', '#2a2118', '#fffcf7'], ['#f5c84c', '#fde68a', '#f97066', '#fdb022', '#14110c', '#f3ebe0', '#1c1812']],
        'midnight' => ['dark', ['#1d4ed8', '#1e3a8a', '#b42318', '#b54708', '#eef2ff', '#1e1b4b', '#ffffff'], ['#60a5fa', '#93c5fd', '#f97066', '#fdb022', '#070b16', '#e0e7ff', '#0f1524']],
        'obsidian' => ['dark', ['#059669', '#047857', '#b42318', '#b54708', '#f3f4f6', '#111827', '#ffffff'], ['#34d399', '#6ee7b7', '#f97066', '#fdb022', '#050505', '#f3f4f6', '#0a0a0a']],
        'aurora' => ['dark', ['#0f766e', '#115e59', '#b42318', '#b54708', '#ecfdf5', '#064e3b', '#ffffff'], ['#2dd4bf', '#5eead4', '#f97066', '#fdb022', '#021411', '#d1fae5', '#0a1f1a']],
        'ember' => ['dark', ['#c2410c', '#9a3412', '#b42318', '#b54708', '#fff7ed', '#431407', '#ffffff'], ['#fb923c', '#fdba74', '#f97066', '#fdb022', '#120a06', '#ffedd5', '#1a100c']],
    ];

    /**
     * @return list<AppearancePreset>
     */
    public static function all(): array
    {
        return array_map(self::get(...), self::IDS);
    }

    /**
     * @throws \InvalidArgumentException for an id outside {@see self::IDS}
     */
    public static function get(string $id): AppearancePreset
    {
        if (!isset(self::DATA[$id])) {
            throw new \InvalidArgumentException(\sprintf('Unknown built-in appearance preset "%s".', $id));
        }
        [$mode, $light, $dark] = self::DATA[$id];

        return new AppearancePreset($id, $mode, new AppearancePalette(...$light), new AppearancePalette(...$dark));
    }
}
