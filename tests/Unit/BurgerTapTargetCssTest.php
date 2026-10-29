<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 2.17.3: the burger keeps a 32px tap target in a crowded phone header (it shrank to its 20px glyph), and the skip
 * link paints with the theme's surface / text pair, so it reads in light and dark themes alike.
 */
final class BurgerTapTargetCssTest extends TestCase
{
    public function testBurgerNeverShrinksBelowATapTarget(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            self::assertMatchesRegularExpression('/\n\s*\.nowo-ui-burger \{[^}]*flex-shrink: 0;[^}]*min-width: 2rem;[^}]*min-height: 2rem;/s', $this->css($file), $file);
        }
    }

    public function testSkipLinkUsesTheThemeSurfaceAndText(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            self::assertMatchesRegularExpression('/\n\s*\.nowo-ui-skip-link \{[^}]*background: var\(--nowo-ui-surface\);[^}]*color: var\(--nowo-ui-text\);/s', $this->css($file), $file);
        }
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/'.$file);
    }
}
