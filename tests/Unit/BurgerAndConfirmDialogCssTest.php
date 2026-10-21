<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 2.6.1: the pre-morph burger X only stands for a fully expanded aside on desktop (never the icons rail, never the
 * phone drawer), and `nowo-ui-confirm-dialog--plain` drops the kit dialog box.
 */
final class BurgerAndConfirmDialogCssTest extends TestCase
{
    public function testPreMorphBurgerGlyphIsKeyedOnTheServerRenderedState(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertStringNotContainsString("[aria-expanded='true']:has(.nowo-ui-burger__icon--close)", $css, $file.': no longer keyed on aria-expanded (true in rail mode)');
            self::assertStringContainsString(".nowo-ui-app-shell__burger:not(.is-morph-ready):not([data-nowo-ui-burger-glyph='close']) .nowo-ui-burger__icon--close {", $css, $file);
            self::assertMatchesRegularExpression("/@media \\(min-width: 901px\\) \\{\\s+\\.nowo-ui-app-shell__burger\\[data-nowo-ui-burger-glyph='close'\\]:has\\(\\.nowo-ui-burger__icon--close\\):not\\(\\.is-morph-ready\\) \\.nowo-ui-burger__icon--open \\{/", $css, $file.': the open glyph hides only on desktop');
            self::assertMatchesRegularExpression('/@media \\(max-width: 900px\\) \\{\\s+\\.nowo-ui-app-shell__burger:not\\(\\.is-morph-ready\\) \\.nowo-ui-burger__icon--close \\{/', $css, $file.': phones start with the menu glyph');
        }
    }

    public function testPlainConfirmDialogDropsTheKitBox(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            self::assertMatchesRegularExpression('/\.nowo-ui-confirm-dialog\.nowo-ui-confirm-dialog--plain \{[^}]*padding: 0;[^}]*background: transparent;[^}]*border: 0;[^}]*box-shadow: none;/s', $this->css($file), $file);
        }
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/'.$file);
    }
}
