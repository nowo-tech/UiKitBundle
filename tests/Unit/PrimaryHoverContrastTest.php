<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 2.60: the primary button hover keeps its text at WCAG AA (4.5:1) in the default light and dark themes. The light
 * hover darkens the primary (85 % + black, the fallback of the button rules); the dark theme sets
 * rule lightens it (white mix), because its primary carries dark text. --nowo-ui-primary-hover stays an optional,
 * never-declared component token. 2.59 used a white mix in light
 * (3.97:1 with white text), which axe flags while the pointer rests on a primary button.
 */
final class PrimaryHoverContrastTest extends TestCase
{
    private const CSS = __DIR__.'/../../src/Resources/public/css/nowo-ui.unlayered.css';

    public function testRulesUseTheThemeAwareHover(): void
    {
        $css = (string) file_get_contents(self::CSS);

        self::assertSame(6, substr_count($css, 'var(--nowo-ui-primary-hover, color-mix(in srgb, var(--nowo-ui-primary) 85%, black))'), 'light: every button rule darkens');
        self::assertSame(1, preg_match("/:where\\(\\[data-theme='dark'\\]\\) \\.nowo-ui-btn--primary:not\\(\\.btn\\):hover \\{[^}]*85%, white\\)\\)\\);/s", $css), 'dark hover lightens');
        self::assertSame(6, substr_count($css, 'var(--nowo-ui-primary-hover, color-mix(in srgb, var(--nowo-ui-primary) 85%, white))'), 'dark: the button rule and the Bootstrap bridge hover + active');
        self::assertStringContainsString((string) file_get_contents(self::CSS), (string) file_get_contents(__DIR__.'/../../src/Resources/public/css/nowo-ui.css'));
    }

    public function testDefaultTokensMeetAaOnHover(): void
    {
        $css = (string) file_get_contents(self::CSS);
        self::assertSame(1, preg_match('/:root:where\(:not\(\[data-nowo-ui-host-tokens\]\)\),\s*:where\(:root\[data-nowo-ui-host-tokens\]\) \{(.*?)\}/s', $css, $light));
        self::assertSame(1, preg_match('/\[data-theme="dark"\]:where\(:not\(\[data-nowo-ui-host-tokens\], \[data-nowo-ui-host-tokens\] \*\)\),\s*:where\([^)]*\) \{(.*?)\}/s', $css, $dark));

        $lightHover = $this->mix($this->token($light[1], 'primary'), [0, 0, 0], 0.85);
        $darkHover = $this->mix($this->token($dark[1], 'primary'), [255, 255, 255], 0.85);

        self::assertGreaterThanOrEqual(4.5, $this->contrast($lightHover, $this->token($light[1], 'primary-contrast')));
        self::assertGreaterThanOrEqual(4.5, $this->contrast($darkHover, $this->token($dark[1], 'primary-contrast')));
        // The resting colours keep AA too.
        self::assertGreaterThanOrEqual(4.5, $this->contrast($this->token($light[1], 'primary'), $this->token($light[1], 'primary-contrast')));
        self::assertGreaterThanOrEqual(4.5, $this->contrast($this->token($dark[1], 'primary'), $this->token($dark[1], 'primary-contrast')));
    }

    /**
     * @return array{int, int, int}
     */
    private function token(string $block, string $name): array
    {
        self::assertSame(1, preg_match('/--nowo-ui-'.preg_quote($name, '/').':\s*#([0-9a-f]{6});/i', $block, $m), $name);
        $hex = $m[1];

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /**
     * color-mix(in srgb, a p, b): channel-wise.
     *
     * @param array{int, int, int} $a
     * @param array{int, int, int} $b
     *
     * @return array{int, int, int}
     */
    private function mix(array $a, array $b, float $p): array
    {
        return [(int) round($a[0] * $p + $b[0] * (1 - $p)), (int) round($a[1] * $p + $b[1] * (1 - $p)), (int) round($a[2] * $p + $b[2] * (1 - $p))];
    }

    /**
     * @param array{int, int, int} $a
     * @param array{int, int, int} $b
     */
    private function contrast(array $a, array $b): float
    {
        $l1 = $this->luminance($a);
        $l2 = $this->luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /**
     * @param array{int, int, int} $rgb
     */
    private function luminance(array $rgb): float
    {
        $c = array_map(static function (int $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }
}
