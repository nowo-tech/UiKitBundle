<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * A named palette pair. `mode` tags which half the preset is meant for: applying a light preset only
 * replaces the light colours, a dark preset only the dark ones, so each mode keeps its own selection.
 */
final readonly class AppearancePreset
{
    public const MODE_LIGHT = 'light';

    public const MODE_DARK = 'dark';

    public function __construct(
        public string $id,
        public string $mode,
        public AppearancePalette $light,
        public AppearancePalette $dark,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid appearance preset id "%s" (lowercase letters, digits, "-" and "_").', $id));
        }
        if (self::MODE_LIGHT !== $mode && self::MODE_DARK !== $mode) {
            throw new \InvalidArgumentException(\sprintf('Invalid appearance preset mode "%s" for "%s" (light|dark).', $mode, $id));
        }
    }

    /**
     * Build from the `nowo_ui_kit.appearance.presets.<id>` config shape: `{mode, light: {...}, dark: {...}}`.
     *
     * @param array<string, mixed> $data
     *
     * @return self Fluent interface
     */
    public static function fromArray(string $id, array $data): self
    {
        $light = \is_array($data['light'] ?? null) ? $data['light'] : [];
        $dark = \is_array($data['dark'] ?? null) ? $data['dark'] : [];
        $mode = \is_string($data['mode'] ?? null) ? $data['mode'] : self::MODE_LIGHT;

        return new self(
            $id,
            $mode,
            AppearancePalette::fromArray($light, BuiltInAppearancePresets::get('slate')->light),
            AppearancePalette::fromArray($dark, BuiltInAppearancePresets::get('slate')->dark),
        );
    }

    public function palette(string $mode): AppearancePalette
    {
        return self::MODE_DARK === $mode ? $this->dark : $this->light;
    }
}
