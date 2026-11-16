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
