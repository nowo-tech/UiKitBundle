<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 2.52 inset sidebar style: the four tokens have light and dark defaults (the surfaces) or apply in both modes (gap,
 * radius); every inset rule sits inside the desktop media query, so phones keep the classic shell; nothing in it hides
 * or shows an element (no visibility that a host `[hidden]` / layer order could break); the panel never clips, so
 * sticky content keeps working; and the layered stylesheet carries the same rules.
 */
final class InsetShellCssTest extends TestCase
{
    private const DIR = __DIR__.'/../../src/Resources/public/css';

    private const SELECTOR = "[data-nowo-ui-sidebar-style='inset']";

    public function testTokensHaveLightAndDarkDefaults(): void
    {
        $css = $this->css();
        $light = $this->block($css, ':root:where(:not([data-nowo-ui-host-tokens])),');
        $dark = $this->block($css, '/* --- Dark theme token defaults');
        $metrics = $this->block($css, ":root {\n  --nowo-ui-aside-width:");

        self::assertStringContainsString('--nowo-ui-shell-inset-surface: var(--nowo-ui-surface-muted);', $light);
        self::assertStringContainsString('--nowo-ui-shell-inset-panel-surface: var(--nowo-ui-surface);', $light);
        self::assertStringContainsString('--nowo-ui-shell-inset-surface: var(--nowo-ui-surface);', $dark);
        self::assertStringContainsString('--nowo-ui-shell-inset-panel-surface: var(--nowo-ui-surface-muted);', $dark);
        self::assertStringContainsString('--nowo-ui-shell-inset-gap: 0.5rem;', $metrics);
        self::assertStringContainsString('--nowo-ui-shell-inset-radius: 0.75rem;', $metrics);
    }

    public function testEveryInsetRuleIsDesktopOnlyAndHidesNothing(): void
    {
        $css = $this->css();
        $start = strpos($css, '/* --- Inset sidebar style (2.52');
        self::assertNotFalse($start);
        $media = strpos($css, '@media (min-width: 901px) {', $start);
        self::assertNotFalse($media);
        $section = substr($css, $media, (int) strpos($css, '/* --- Toasts --- */', $media) - $media);

        // Every occurrence of the hook in the stylesheet is inside this one desktop block.
        self::assertSame(substr_count($css, self::SELECTOR), substr_count($section, self::SELECTOR));
        self::assertGreaterThanOrEqual(6, substr_count($section, self::SELECTOR));
        self::assertStringContainsString('margin: var(--nowo-ui-shell-inset-gap);', $section);
        self::assertStringContainsString('border-radius: var(--nowo-ui-shell-inset-radius);', $section);
        self::assertStringContainsString('background: var(--nowo-ui-shell-inset-panel-surface);', $section);
        self::assertStringContainsString('background: var(--nowo-ui-shell-inset-surface);', $section);
        self::assertStringContainsString('border-color: transparent;', $section);
        foreach (['display:', 'visibility:', 'opacity:', 'overflow', 'position:', 'transform', 'contain:', '!important'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $section, 'the inset style only paints: '.$forbidden);
        }
    }

    /**
     * 2.52.1 (audit RK-L1): with inset, content width and an edge-tabs gutter, the end padding is the centring padding
     * plus the gutter, in a rule that outranks the edge-tabs gutter rule (0,7,0).
     */
    public function testEdgeTabsGutterAddsToTheCentringPadding(): void
    {
        $css = $this->css();
        $rule = ":root[data-nowo-ui-edge-tabs-ready]:has(.nowo-ui-edge-tabs-launcher[data-nowo-ui-edge-tabs-gutter]:not([hidden])) .nowo-ui-shell[data-nowo-ui-sidebar-style='inset'].is-main-content > .nowo-ui-main {\n    padding-inline-end: calc(max(1.25rem, calc((100% - 2 * var(--nowo-ui-shell-inset-gap) - var(--nowo-ui-content-max-width)) / 2 + 1.25rem)) + var(--nowo-ui-edge-tabs-gutter, 0px));";
        self::assertSame(1, substr_count($css, $rule));
        self::assertStringContainsString(":root[data-nowo-ui-edge-tabs-ready]:has(.nowo-ui-edge-tabs-launcher[data-nowo-ui-edge-tabs-when='always']:not([hidden])[data-nowo-ui-edge-tabs-gutter]) .nowo-ui-main {\n  padding-inline-end: calc(1.25rem + var(--nowo-ui-edge-tabs-gutter));", $css, 'the classic gutter rule is unchanged');
    }

    /**
     * 2.52.1 (audit RK-M1 / RK-I5, WCAG AA): the classic current aside item mixes the primary 85 % with the text colour
     * (as the inset style did), and every :focus-visible outline falls back to an 80 % primary (3:1 or more on the
     * default surfaces); the input / Bootstrap-bridge box-shadow halos keep the 35 % mix next to their own indicator.
     */
    public function testContrastFallbacks(): void
    {
        $css = $this->css();
        self::assertStringContainsString(".nowo-ui-aside__link.is-current,\n.nowo-ui-aside__link--active {\n  background: var(--nowo-ui-nav-active-bg, color-mix(in srgb, var(--nowo-ui-primary) 12%, transparent));\n  color: var(--nowo-ui-nav-active-text, color-mix(in srgb, var(--nowo-ui-primary) 85%, var(--nowo-ui-text)));", $css);
        self::assertStringNotContainsString('color: var(--nowo-ui-nav-active-text, var(--nowo-ui-primary));', $css);
        self::assertSame(0, substr_count($css, 'outline: 2px solid var(--nowo-ui-focus-ring-color, color-mix(in srgb, var(--nowo-ui-primary) 35%, transparent));'));
        self::assertGreaterThanOrEqual(24, substr_count($css, 'outline: 2px solid var(--nowo-ui-focus-ring-color, color-mix(in srgb, var(--nowo-ui-primary) 80%, transparent));'));
        self::assertSame(0, preg_match('/outline:[^;]*35%, transparent/', $css), 'no outline keeps the 35 % ring');
    }

    public function testLayeredStylesheetCarriesTheSameRules(): void
    {
        $layered = (string) file_get_contents(self::DIR.'/nowo-ui.css');
        self::assertSame(substr_count($this->css(), self::SELECTOR), substr_count($layered, self::SELECTOR));
        self::assertStringContainsString('--nowo-ui-shell-inset-panel-surface: var(--nowo-ui-surface-muted);', $layered);
    }

    private function css(): string
    {
        return (string) file_get_contents(self::DIR.'/nowo-ui.unlayered.css');
    }

    private function block(string $css, string $marker): string
    {
        $start = strpos($css, $marker);
        self::assertNotFalse($start, $marker);

        return substr($css, $start, (int) strpos($css, '}', $start) - $start);
    }
}
