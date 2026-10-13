<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * Turns an {@see Appearance} into a `:root` + light + dark block of `--nowo-ui-*` custom properties,
 * plus optional host aliases (`token_aliases`: kit token → extra property names that receive the same
 * value, e.g. a Tailwind `--color-*` palette).
 *
 * Colours are sanitised (hex, CSS colour functions, named colours); anything else falls back to the
 * matching colour of the fallback palettes, so stored values can never break out of the `<style>`.
 */
final readonly class AppearanceCssEmitter
{
    /** Tokens emitted once (outside the theme blocks). */
    public const ROOT_TOKENS = ['--nowo-ui-radius', '--nowo-ui-card-radius', '--nowo-ui-border-width'];

    /** Tokens emitted in the light and in the dark block. */
    public const MODE_TOKENS = [
        '--nowo-ui-primary',
        '--nowo-ui-primary-deep',
        '--nowo-ui-primary-hover',
        '--nowo-ui-primary-contrast',
        '--nowo-ui-danger',
        '--nowo-ui-warning',
        '--nowo-ui-paper',
        '--nowo-ui-text',
        '--nowo-ui-surface',
        '--nowo-ui-border',
        '--nowo-ui-border-strong',
        '--nowo-ui-accent-soft',
        '--nowo-ui-surface-muted',
        '--nowo-ui-paper-accent',
        '--nowo-ui-paper-ink',
        '--nowo-ui-muted',
        '--nowo-ui-brand-mark',
        '--nowo-ui-shadow-rgb',
    ];

    public const DEFAULT_SELECTORS = [
        'root' => ':root',
        'light' => ':root, [data-theme="light"]',
        'dark' => '[data-theme="dark"]',
    ];

    private const COLOR_PATTERN = '/^(?:#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|[a-zA-Z]{3,32}|(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color|color-mix)\([0-9a-zA-Z.,%#\s\/+-]{1,200}\))$/';

    private AppearancePalette $fallbackLight;

    private AppearancePalette $fallbackDark;

    /** @var array{root: string, light: string, dark: string} */
    private array $selectors;

    /**
     * @param array<string, list<string>>                         $tokenAliases      kit token => alias property names
     * @param bool                                                $kitTokens         false = only the aliases are written
     * @param array{root?: string, light?: string, dark?: string} $selectors
     * @param string                                              $shadowRgbFallback RGB channels when the ink colour is not hex
     */
    public function __construct(
        private array $tokenAliases = [],
        private bool $kitTokens = true,
        array $selectors = [],
        private string $shadowRgbFallback = '15 23 42',
        ?AppearancePalette $fallbackLight = null,
        ?AppearancePalette $fallbackDark = null,
    ) {
        $this->selectors = array_merge(self::DEFAULT_SELECTORS, $selectors);
        $this->fallbackLight = $fallbackLight ?? BuiltInAppearancePresets::get('slate')->light;
        $this->fallbackDark = $fallbackDark ?? BuiltInAppearancePresets::get('midnight')->dark;
    }

    /**
     * The values per block, kit token names only (aliases not included).
     *
     * @return array{root: array<string, string>, light: array<string, string>, dark: array<string, string>}
     */
    public function tokens(Appearance $appearance): array
    {
        $radii = $appearance->corner()->radii();
        $border = $appearance->border()->tokens();

        return [
            'root' => [
                '--nowo-ui-radius' => $radii['control'],
                '--nowo-ui-card-radius' => $radii['card'],
                '--nowo-ui-border-width' => $border['width'],
            ],
            'light' => $this->modeTokens($this->sanitize($appearance->light, $this->fallbackLight), $border['mixLight'], false),
            'dark' => $this->modeTokens($this->sanitize($appearance->dark, $this->fallbackDark), $border['mixDark'], true),
        ];
    }

    /**
     * Stylesheet text (without the `<style>` element).
     */
    public function emit(Appearance $appearance): string
    {
        $tokens = $this->tokens($appearance);
        $lines = [];
        foreach (['root', 'light', 'dark'] as $block) {
            $lines[] = $this->selectors[$block].' {';
            foreach ($tokens[$block] as $name => $value) {
                if ($this->kitTokens) {
                    $lines[] = \sprintf('  %s: %s;', $name, $value);
                }
                foreach ($this->tokenAliases[$name] ?? [] as $alias) {
                    $lines[] = \sprintf('  %s: %s;', $alias, $value);
                }
            }
            $lines[] = '}';
        }

        return implode("\n", $lines);
    }

    /**
     * Whether a stored value is accepted as a CSS colour.
     */
    public static function isColor(string $value): bool
    {
        return 1 === preg_match(self::COLOR_PATTERN, trim($value));
    }

    /**
     * `#rgb` / `#rrggbb` → `"r g b"` channels, or null for any other notation.
     */
    public static function rgbChannels(string $color): ?string
    {
        $hex = ltrim(trim($color), '#');
        if (3 === \strlen($hex) && ctype_xdigit($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (6 !== \strlen($hex) || !ctype_xdigit($hex) || !str_starts_with(trim($color), '#')) {
            return null;
        }

        return \sprintf('%d %d %d', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    /**
     * Of `$a` / `$b`, the colour with the higher WCAG contrast against `$background` (hex only; null otherwise).
     */
    public static function contrastingOf(string $background, string $a, string $b): ?string
    {
        $bg = self::luminance($background);
        $la = self::luminance($a);
        $lb = self::luminance($b);
        if (\in_array(null, [$bg, $la, $lb], true)) {
            return null;
        }
        $ratio = static fn (float $x, float $y): float => (max($x, $y) + 0.05) / (min($x, $y) + 0.05);

        return $ratio($bg, $la) >= $ratio($bg, $lb) ? $a : $b;
    }

    private static function luminance(string $color): ?float
    {
        $channels = self::rgbChannels($color);
        if (null === $channels) {
            return null;
        }
        $linear = array_map(static function (string $c): float {
            $v = (int) $c / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, explode(' ', $channels));

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private function sanitize(AppearancePalette $palette, AppearancePalette $fallback): AppearancePalette
    {
        $values = $palette->toArray();
        $defaults = $fallback->toArray();
        foreach ($values as $key => $value) {
            $values[$key] = self::isColor($value) ? trim($value) : $defaults[$key];
        }

        return AppearancePalette::fromArray($values, $fallback);
    }

    /**
     * @return array<string, string>
     */
    private function modeTokens(AppearancePalette $p, int $borderMix, bool $dark): array
    {
        $mix = static fn (string $a, int $percent, string $b): string => \sprintf('color-mix(in srgb, %s %d%%, %s)', $a, $percent, $b);
        $border = $mix($p->ink, $borderMix, $p->paper);
        $tokens = [
            '--nowo-ui-primary' => $p->accent,
            '--nowo-ui-primary-deep' => $p->accentDeep,
            '--nowo-ui-primary-hover' => $p->accentDeep,
            '--nowo-ui-primary-contrast' => self::contrastingOf($p->accent, $p->surface, $p->ink),
            '--nowo-ui-danger' => $p->danger,
            '--nowo-ui-warning' => $p->warn,
            '--nowo-ui-paper' => $p->paper,
            '--nowo-ui-text' => $p->ink,
            '--nowo-ui-surface' => $p->surface,
            '--nowo-ui-border' => $border,
            '--nowo-ui-border-strong' => $border,
            '--nowo-ui-accent-soft' => $mix($p->accent, 10, $p->paper),
            '--nowo-ui-surface-muted' => $mix($p->ink, 5, $p->surface),
            '--nowo-ui-paper-accent' => $mix($p->accent, 8, $p->paper),
            '--nowo-ui-paper-ink' => $mix($p->ink, 6, $p->paper),
            '--nowo-ui-muted' => $mix($p->ink, 60, $p->surface),
            '--nowo-ui-brand-mark' => $p->accent,
            '--nowo-ui-shadow-rgb' => $dark ? '0 0 0' : (self::rgbChannels($p->ink) ?? $this->shadowRgbFallback),
        ];

        return array_filter($tokens, static fn (?string $v): bool => null !== $v);
    }
}
