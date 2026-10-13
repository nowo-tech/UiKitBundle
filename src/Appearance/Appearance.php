<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * Site look & feel as stored by the host (entity, settings table, config…): brand, light and dark
 * palettes, the preset ids last applied per mode, corner style, border strength, fixed footer.
 *
 * Immutable: `with*()` return a copy. {@see self::toArray()} / {@see self::fromArray()} bridge to
 * forms and storage.
 */
final readonly class Appearance
{
    public const CUSTOM = 'custom';

    public function __construct(
        public string $brandName,
        public string $brandEyebrow,
        public AppearancePalette $light,
        public AppearancePalette $dark,
        public string $themeId = self::CUSTOM,
        public string $themeIdDark = self::CUSTOM,
        public string $cornerStyle = 'soft',
        public string $borderStrength = 'medium',
        public bool $footerFixed = false,
    ) {
    }

    public function corner(): CornerStyle
    {
        return CornerStyle::fromLoose($this->cornerStyle);
    }

    public function border(): BorderStrength
    {
        return BorderStrength::fromLoose($this->borderStrength);
    }

    public function palette(string $mode): AppearancePalette
    {
        return AppearancePreset::MODE_DARK === $mode ? $this->dark : $this->light;
    }

    public function withLight(AppearancePalette $light, ?string $themeId = null): self
    {
        return $this->with(['light' => $light, 'themeId' => $themeId ?? $this->themeId]);
    }

    public function withDark(AppearancePalette $dark, ?string $themeIdDark = null): self
    {
        return $this->with(['dark' => $dark, 'themeIdDark' => $themeIdDark ?? $this->themeIdDark]);
    }

    /**
     * Copy with some constructor arguments replaced (keys are the constructor parameter names).
     *
     * @param array<string, mixed> $changes
     *
     * @return self Fluent interface
     */
    public function with(array $changes): self
    {
        $value = static fn (string $key, mixed $current): mixed => \array_key_exists($key, $changes) ? $changes[$key] : $current;
        $light = $value('light', $this->light);
        $dark = $value('dark', $this->dark);

        return new self(
            (string) $this->scalar($value('brandName', $this->brandName)),
            (string) $this->scalar($value('brandEyebrow', $this->brandEyebrow)),
            $light instanceof AppearancePalette ? $light : $this->light,
            $dark instanceof AppearancePalette ? $dark : $this->dark,
            (string) $this->scalar($value('themeId', $this->themeId)),
            (string) $this->scalar($value('themeIdDark', $this->themeIdDark)),
            (string) $this->scalar($value('cornerStyle', $this->cornerStyle)),
            (string) $this->scalar($value('borderStrength', $this->borderStrength)),
            (bool) $value('footerFixed', $this->footerFixed),
        );
    }

    /**
     * @return array{
     *     brand_name: string,
     *     brand_eyebrow: string,
     *     light: array{accent: string, accent_deep: string, danger: string, warn: string, paper: string, ink: string, surface: string},
     *     dark: array{accent: string, accent_deep: string, danger: string, warn: string, paper: string, ink: string, surface: string},
     *     theme_id: string,
     *     theme_id_dark: string,
     *     corner_style: string,
     *     border_strength: string,
     *     footer_fixed: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'brand_name' => $this->brandName,
            'brand_eyebrow' => $this->brandEyebrow,
            'light' => $this->light->toArray(),
            'dark' => $this->dark->toArray(),
            'theme_id' => $this->themeId,
            'theme_id_dark' => $this->themeIdDark,
            'corner_style' => $this->cornerStyle,
            'border_strength' => $this->borderStrength,
            'footer_fixed' => $this->footerFixed,
        ];
    }

    /**
     * Inverse of {@see self::toArray()}; missing keys keep the values of `$defaults`.
     *
     * @param array<mixed> $data
     *
     * @return self Fluent interface
     */
    public static function fromArray(array $data, self $defaults): self
    {
        $string = static fn (string $key, string $default): string => \is_string($data[$key] ?? null) ? $data[$key] : $default;

        return new self(
            $string('brand_name', $defaults->brandName),
            $string('brand_eyebrow', $defaults->brandEyebrow),
            \is_array($data['light'] ?? null) ? AppearancePalette::fromArray($data['light'], $defaults->light) : $defaults->light,
            \is_array($data['dark'] ?? null) ? AppearancePalette::fromArray($data['dark'], $defaults->dark) : $defaults->dark,
            $string('theme_id', $defaults->themeId),
            $string('theme_id_dark', $defaults->themeIdDark),
            $string('corner_style', $defaults->cornerStyle),
            $string('border_strength', $defaults->borderStrength),
            \array_key_exists('footer_fixed', $data) ? (bool) $data['footer_fixed'] : $defaults->footerFixed,
        );
    }

    private function scalar(mixed $value): string|int|float|bool|null
    {
        return \is_scalar($value) || null === $value ? $value : null;
    }
}
