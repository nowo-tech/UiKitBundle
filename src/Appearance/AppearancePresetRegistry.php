<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * All presets available to the host, in display order: host presets first (config `presets`, then tagged
 * providers), then the enabled built-ins. A host preset with a built-in id replaces it.
 */
final readonly class AppearancePresetRegistry
{
    /** @var array<string, AppearancePreset> */
    private array $presets;

    /**
     * @param iterable<AppearancePreset>                  $configPresets
     * @param iterable<AppearancePresetProviderInterface> $providers
     * @param list<string>|null                           $builtIn       built-in ids to keep (null = all)
     */
    public function __construct(iterable $configPresets = [], iterable $providers = [], ?array $builtIn = null)
    {
        $presets = [];
        foreach ($configPresets as $preset) {
            $presets[$preset->id] = $preset;
        }
        foreach ($providers as $provider) {
            foreach ($provider->getPresets() as $preset) {
                $presets[$preset->id] = $preset;
            }
        }
        foreach (BuiltInAppearancePresets::all() as $preset) {
            if (!isset($presets[$preset->id]) && (null === $builtIn || \in_array($preset->id, $builtIn, true))) {
                $presets[$preset->id] = $preset;
            }
        }
        $this->presets = $presets;
    }

    /**
     * @return list<AppearancePreset>
     */
    public function all(): array
    {
        return array_values($this->presets);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->presets);
    }

    public function has(string $id): bool
    {
        return isset($this->presets[$id]);
    }

    public function get(string $id): ?AppearancePreset
    {
        return $this->presets[$id] ?? null;
    }

    /**
     * Presets tagged for one mode (`light` / `dark`).
     *
     * @return list<AppearancePreset>
     */
    public function byMode(string $mode): array
    {
        return array_values(array_filter($this->presets, static fn (AppearancePreset $p): bool => $p->mode === $mode));
    }

    /**
     * Copy of `$appearance` with the matching half of preset `$id` applied (light preset → light colours +
     * `themeId`; dark preset → dark colours + `themeIdDark`). Unknown id → unchanged.
     */
    public function apply(Appearance $appearance, string $id): Appearance
    {
        $preset = $this->get($id);
        if (!$preset instanceof AppearancePreset) {
            return $appearance;
        }

        return AppearancePreset::MODE_DARK === $preset->mode
            ? $appearance->withDark($preset->dark, $preset->id)
            : $appearance->withLight($preset->light, $preset->id);
    }

    /**
     * Id of the light-tagged preset whose light colours equal the appearance's, or `custom`.
     */
    public function matchLightId(Appearance $appearance): string
    {
        return $this->match($appearance, AppearancePreset::MODE_LIGHT);
    }

    /**
     * Id of the dark-tagged preset whose dark colours equal the appearance's, or `custom`.
     */
    public function matchDarkId(Appearance $appearance): string
    {
        return $this->match($appearance, AppearancePreset::MODE_DARK);
    }

    private function match(Appearance $appearance, string $mode): string
    {
        foreach ($this->byMode($mode) as $preset) {
            if ($preset->palette($mode)->equals($appearance->palette($mode))) {
                return $preset->id;
            }
        }

        return Appearance::CUSTOM;
    }
}
