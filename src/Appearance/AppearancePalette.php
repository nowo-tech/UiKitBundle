<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * The seven colours of one theme mode (light or dark).
 *
 * Values are CSS colours as stored by the host (normally `#rrggbb`); the emitter sanitises them.
 */
final readonly class AppearancePalette
{
    /** Keys of {@see self::toArray()} / {@see self::fromArray()}. */
    public const KEYS = ['accent', 'accent_deep', 'danger', 'warn', 'paper', 'ink', 'surface'];

    public function __construct(
        public string $accent,
        public string $accentDeep,
        public string $danger,
        public string $warn,
        public string $paper,
        public string $ink,
        public string $surface,
    ) {
    }

    /**
     * Build from snake_case (`accent_deep`) or camelCase (`accentDeep`) keys; missing keys come from `$fallback`.
     *
     * @param array<mixed> $data
     *
     * @return self Fluent interface
     */
    public static function fromArray(array $data, ?self $fallback = null): self
    {
        $fallback ??= BuiltInAppearancePresets::get('slate')->light;
        $read = static function (string $key, string $default) use ($data): string {
            $camel = lcfirst(str_replace('_', '', ucwords($key, '_')));
            $value = $data[$key] ?? $data[$camel] ?? null;

            return \is_string($value) && '' !== trim($value) ? trim($value) : $default;
        };

        return new self(
            $read('accent', $fallback->accent),
            $read('accent_deep', $fallback->accentDeep),
            $read('danger', $fallback->danger),
            $read('warn', $fallback->warn),
            $read('paper', $fallback->paper),
            $read('ink', $fallback->ink),
            $read('surface', $fallback->surface),
        );
    }

    /**
     * @return array{accent: string, accent_deep: string, danger: string, warn: string, paper: string, ink: string, surface: string}
     */
    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
            'accent_deep' => $this->accentDeep,
            'danger' => $this->danger,
            'warn' => $this->warn,
            'paper' => $this->paper,
            'ink' => $this->ink,
            'surface' => $this->surface,
        ];
    }

    /**
     * Same colours (case-insensitive), as used to recognise a preset.
     */
    public function equals(self $other): bool
    {
        return array_map(strtolower(...), $this->toArray()) === array_map(strtolower(...), $other->toArray());
    }
}
