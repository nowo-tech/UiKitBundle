<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;

/**
 * 2.5.3: `<button>` kebab items keep the item font size over Tailwind preflight / the UA stylesheet, and the locale tab
 * list is spaced by the locale tabs gap only (no `tabs` margin).
 */
final class KebabAndLocaleTabsCssTest extends TestCase
{
    public function testButtonKebabItemsReapplyTheItemFontSizeAfterFontInherit(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertStringContainsString(".nowo-ui-kebab__item {\n  display: block;\n  padding: 0.4rem 0.55rem;\n  border-radius: calc(var(--nowo-ui-radius) - 1px);\n  color: var(--nowo-ui-text);\n  text-decoration: none;\n  font-size: 0.875rem;\n}", $css, $file);
            self::assertStringContainsString("button.nowo-ui-kebab__item {\n  width: 100%;\n  border: 0;\n  background: transparent;\n  font: inherit;\n  font-size: 0.875rem;\n", $css, $file.': font-size after the font shorthand');
            self::assertStringContainsString("button.nowo-ui-kebab__item:hover,\nbutton.nowo-ui-kebab__item.is-current {\n  background: var(--nowo-ui-surface-muted);\n}", $css, $file);
            self::assertStringNotContainsString('.nowo-ui-kebab__menu[popover] button.nowo-ui-kebab__item', $css, $file.': no popover-only rule resetting the font size (and the hover background)');
        }

        $layered = $this->css('nowo-ui.css');
        self::assertStringStartsWith('@layer nowo-ui {', substr($layered, (int) strpos($layered, '@layer nowo-ui {')));
        self::assertGreaterThan((int) strpos($layered, '@layer nowo-ui {'), (int) strpos($layered, 'button.nowo-ui-kebab__item {'), 'Inside the nowo-ui layer (above Tailwind `base`)');
    }

    public function testLocaleTabListIsSpacedByTheGapToken(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertStringContainsString(".nowo-ui-locale-tabs {\n  display: grid;\n  gap: var(--nowo-ui-locale-tabs-gap, 0.75rem);", $css, $file);
            self::assertStringContainsString(".nowo-ui-locale-tabs > .nowo-ui-locale-tabs__nav {\n  margin-bottom: 0;\n}", $css, $file);
        }
        self::assertSame('nowo-ui-tabs nowo-ui-locale-tabs__nav', ThemeSlots::semantic('locale_tabs_nav'));
        self::assertStringContainsString('mb-3', ThemeSlots::defaults('tabs', 'tailwind'), 'Page-level tabs keep their margin');
        self::assertStringNotContainsString('mb-', ThemeSlots::defaults('locale_tabs_nav', 'tailwind'));
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/'.$file);
    }
}
