<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.51 — `nowo-ui-base.css`, UiKit's opt-in default theme (`nowo_ui_kit.base_stylesheet`).
 *
 * `nowo-ui.css` paints the components but never the document, so a host with no CSS framework and no theme file
 * fell back to the browser defaults (serif body font, no page gutter, a one-line masthead). This file is that
 * missing base, and these tests pin the two properties that make it safe for hosts that DO have a theme:
 *
 * - its cascade layer `nowo-ui-base` is declared FIRST, i.e. below `theme`, `base`, `nowo-ui`, `components` and
 *   `utilities`, so a framework, the kit itself, host utilities and all unlayered host CSS beat it;
 * - everything it paints reads a `--nowo-ui-*` token, so host tokens and the dark defaults follow by themselves.
 */
final class BaseStylesheetCssTest extends TestCase
{
    private const FILE = __DIR__.'/../../src/Resources/public/css/nowo-ui-base.css';

    private function css(): string
    {
        $css = file_get_contents(self::FILE);
        self::assertIsString($css);

        return $css;
    }

    public function testLayerOrderPutsTheBaseThemeBelowEverythingElse(): void
    {
        $css = $this->css();
        self::assertStringContainsString(
            '@layer theme, base, nowo-ui, components, utilities;',
            $css,
            'The base theme states UiKit\'s canonical layer order, exactly as nowo-ui.css does.',
        );
        self::assertStringNotContainsString(
            '@layer nowo-ui-base,',
            $css,
            'A NEW top-level layer would be APPENDED on top of an order a host already fixed, outranking its framework. '
            .'The base theme must nest inside an existing layer instead.',
        );
    }

    public function testEveryRuleSitsInsideTheBaseLayer(): void
    {
        $css = $this->css();
        self::assertIsInt(
            strpos($css, '@layer theme.nowo-ui-base {'),
            'The rules must be wrapped in @layer theme.nowo-ui-base { … }: a sub-layer of the first layer of the order.',
        );

        // Nothing but whitespace may follow the closing brace of the layer block.
        self::assertSame('}', trim(substr($css, strrpos($css, '}'))), 'The file must end with the layer block.');
        // At-rules start a line; mentions inside the header comment are indented by " * ".
        self::assertSame(
            2,
            preg_match_all('/^@layer /m', $css),
            'Only the order statement and the single block may use @layer.',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredDeclarations(): iterable
    {
        // A sans-serif stack is the whole point: without it the browser default is serif (Times).
        yield 'body font stack' => ['font-family: var(--nowo-ui-font-family, ui-sans-serif, system-ui'];
        yield 'body colour token' => ['color: var(--nowo-ui-text);'];
        yield 'body background token' => ['background: var(--nowo-ui-surface-muted);'];
        yield 'controls inherit the font' => ['font: inherit;'];
        yield 'page gutter' => ['.nowo-ui-page:not(.nowo-ui-page--guest) > .nowo-ui-content {'];
        yield 'page gutter max width token' => ['max-width: var(--nowo-ui-content-max-width, 72rem);'];
        yield 'masthead height token' => ['min-height: var(--nowo-ui-header-height, 3.25rem);'];
        // The masthead gutter travels as a token: a `padding-inline` here would lose to the `nowo-ui` layer.
        yield 'masthead gutter token' => ['--nowo-ui-header-padding-inline: max('];
    }

    #[DataProvider('requiredDeclarations')]
    public function testBaseThemeDeclaration(string $needle): void
    {
        self::assertStringContainsString($needle, $this->css());
    }

    public function testBaseThemeNeverUsesImportant(): void
    {
        self::assertStringNotContainsString(
            '!important',
            $this->css(),
            'The base theme must never out-shout a host: it is the lowest layer and stays there.',
        );
    }

    /** The masthead padding must stay a token in nowo-ui.css, or the base theme cannot align it with the gutter. */
    public function testKitHeaderPaddingIsTokenised(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = file_get_contents(__DIR__.'/../../src/Resources/public/css/'.$file);
            self::assertIsString($css);
            self::assertStringContainsString('padding-inline: var(--nowo-ui-header-padding-inline, 1rem);', $css, $file);
            self::assertStringContainsString('.nowo-ui-header__nav,', $css, $file);
        }
    }
}
