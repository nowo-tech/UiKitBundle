<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.63.2: inline text links are underlined (axe `link-in-text-block`) and the UxLinkBundle share list has no bullets.
 */
final class InlineLinkAndShareListCssTest extends TestCase
{
    private const DIR = __DIR__.'/../../src/Resources/public/css';

    /**
     * @return iterable<string, array{string}>
     */
    public static function stylesheets(): iterable
    {
        yield 'unlayered' => ['nowo-ui.unlayered.css'];
        yield 'layered' => ['nowo-ui.css'];
    }

    #[DataProvider('stylesheets')]
    public function testInlineLinkIsUnderlinedInTextBlocks(string $file): void
    {
        $css = (string) file_get_contents(self::DIR.'/'.$file);

        self::assertMatchesRegularExpression('/:where\(p,[^)]*\) \.nowo-ui-link:not\(\.nowo-ui-btn\)\s*\{[^}]*text-decoration: underline;/', $css);
    }

    #[DataProvider('stylesheets')]
    public function testShareListHasNoBullets(string $file): void
    {
        $css = (string) file_get_contents(self::DIR.'/'.$file);

        self::assertMatchesRegularExpression('/\.ux-share-links__list\s*\{[^}]*list-style: none;[^}]*\}/', $css);
        self::assertMatchesRegularExpression('/\.ux-share-links__list\s*\{[^}]*padding: 0;/', $css);
    }
}
