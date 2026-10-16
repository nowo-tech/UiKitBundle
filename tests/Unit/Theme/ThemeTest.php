<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Theme;

use Nowo\UiKitBundle\Theme\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    public function testClassResolutionMergesFrameworkDefaultsHostAndBundleExceptions(): void
    {
        $theme = new Theme('bootstrap5', classes: [
            'btn_primary' => 'btn btn-moss',
            'table' => 'data-table',
        ], bundles: [
            'NowoHttpLogBundle' => ['classes' => ['table' => 'data-table data-table--dense']],
        ]);

        self::assertSame('bootstrap5', $theme->framework());
        // semantic + framework default + host, de-duplicated (btn appears once)
        self::assertSame('nowo-ui-btn nowo-ui-btn--primary btn btn-primary btn-moss', $theme->classFor('btn_primary'));
        self::assertSame('nowo-ui-table nowo-ui-table--striped table table-hover align-middle data-table', $theme->classFor('table'));
        // bundle exception replaces the host classes of that slot
        self::assertSame('nowo-ui-table nowo-ui-table--striped table table-hover align-middle data-table data-table--dense', $theme->classFor('table', 'NowoHttpLogBundle'));
        // other bundles / slots fall back to host / defaults
        self::assertSame('nowo-ui-table nowo-ui-table--striped table table-hover align-middle data-table', $theme->classFor('table', 'OtherBundle'));
        self::assertSame('nowo-ui-table__row', $theme->classFor('table_row'));
        // framework override (partials with `framework`)
        self::assertSame('nowo-ui-table-wrap overflow-x-auto', $theme->classFor('table_wrap', null, 'tailwind'));
        self::assertSame('nowo-ui-table-wrap table-responsive', $theme->classFor('table_wrap', null, '  '));
        self::assertSame('data-table data-table--dense', $theme->extraClasses('table', 'NowoHttpLogBundle'));
        self::assertSame('', $theme->extraClasses('page_header'));
    }

    public function testCustomFrameworkKeepsOnlySemanticAndHostClasses(): void
    {
        $theme = new Theme('custom', classes: ['page_header' => '  app-header   app-header--sticky ']);

        self::assertSame('nowo-ui-page-header app-header app-header--sticky', $theme->classFor('page_header'));
    }

    public function testAttributesMergeAndRenderEscaped(): void
    {
        $theme = new Theme(attributes: [
            'toasts' => ['data-controller' => 'toast-stack', 'data-flag' => true, 'hidden' => false, 'title' => null],
        ], bundles: [
            'NowoXBundle' => ['attributes' => ['toasts' => ['data-controller' => 'x-toasts', 'data-n' => 3]]],
        ]);

        self::assertSame(['data-controller' => 'toast-stack', 'data-flag' => true, 'hidden' => false, 'title' => null], $theme->attributesFor('toasts'));
        self::assertSame(' data-controller="toast-stack" data-flag', $theme->renderAttributes('toasts'));
        self::assertSame(' data-controller="x-toasts" data-flag data-n="3"', $theme->renderAttributes('toasts', 'NowoXBundle'));
        self::assertSame(' data-controller="toast-stack" data-flag data-x="&quot;&lt;b&gt;&quot;"', $theme->renderAttributes('toasts', null, ['data-x' => '"<b>"']));
        self::assertSame('', $theme->renderAttributes('table'));
    }

    public function testRenderAttributesRefusesUnsafeCallSiteAttributes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('inline event handler');
        (new Theme())->renderAttributes('btn', null, ['onclick' => 'x()']);
    }

    public function testPartialResolution(): void
    {
        $theme = new Theme(partials: ['pagination' => 'kit/_pagination.html.twig'], bundles: [
            'NowoXBundle' => ['partials' => ['pagination' => 'x/_pagination.html.twig']],
        ]);

        self::assertSame('kit/_pagination.html.twig', $theme->partial('pagination'));
        self::assertSame('x/_pagination.html.twig', $theme->partial('pagination', 'NowoXBundle'));
        self::assertSame('@NowoUiKitBundle/partials/_toasts.html.twig', $theme->partial('toasts', 'NowoXBundle'));
    }

    public function testTokens(): void
    {
        self::assertFalse((new Theme())->hasTokens());
        self::assertSame('', (new Theme())->tokensCss());

        $theme = new Theme(tokens: ['primary' => 'var(--color-moss)', '--nowo-ui-surface' => '#fff'], tokensDark: ['primary' => '#9c9'], bundles: [
            'NowoXBundle' => ['tokens' => ['radius' => '0'], 'tokens_dark' => ['primary' => 'oklch(0.7 0.1 140)']],
        ]);

        self::assertTrue($theme->hasTokens());
        self::assertSame(<<<'CSS'
            :root {
              --nowo-ui-primary: var(--color-moss);
              --nowo-ui-surface: #fff;
            }
            :root[data-theme="dark"] {
              --nowo-ui-primary: #9c9;
            }
            [data-nowo-ui-bundle="NowoXBundle"] {
              --nowo-ui-radius: 0;
            }
            [data-theme="dark"] [data-nowo-ui-bundle="NowoXBundle"] {
              --nowo-ui-primary: oklch(0.7 0.1 140);
            }
            CSS, $theme->tokensCss());

        self::assertTrue((new Theme(tokensDark: ['primary' => 'red']))->hasTokens());
        self::assertTrue((new Theme(bundles: ['NowoXBundle' => ['tokens_dark' => ['primary' => 'red']]]))->hasTokens());
        self::assertFalse((new Theme(bundles: ['NowoXBundle' => ['classes' => ['btn' => 'x']]]))->hasTokens());
    }

    public function testFromConfig(): void
    {
        $theme = Theme::fromConfig('tailwind', ['classes' => ['btn' => 'x'], 'tokens' => ['primary' => 'red']]);

        self::assertSame('tailwind', $theme->framework());
        self::assertStringEndsWith(' x', $theme->classFor('btn'));
        self::assertTrue($theme->hasTokens());
        self::assertSame('@NowoUiKitBundle/partials/_empty.html.twig', Theme::fromConfig('none', [])->partial('empty'));
    }

    public function testValidationHelpers(): void
    {
        self::assertNull(Theme::tokenError('primary', 'var(--x)'));
        self::assertNull(Theme::tokenError('--nowo-ui-radius', 4));
        self::assertNotNull(Theme::tokenError('Primary', 'red'));
        self::assertNotNull(Theme::tokenError('primary', ['red']));
        self::assertNotNull(Theme::tokenError('primary', 'red; } body { x'));
        self::assertNotNull(Theme::tokenError('primary', '  '));
        self::assertNull(Theme::attributeNameError('data-controller'));
        self::assertNotNull(Theme::attributeNameError('on-click'));
        self::assertNotNull(Theme::attributeNameError('a b'));
        self::assertSame('primary', Theme::normalizeTokenName('--nowo-ui-primary'));
    }

    /**
     * @return iterable<string, array{\Closure(): Theme, string}>
     */
    public static function invalidThemes(): iterable
    {
        yield 'unknown class slot' => [static fn (): Theme => new Theme(classes: ['btn_moss' => 'x']), 'Unknown UiKit theme slot "btn_moss"'];
        yield 'unknown bundle class slot' => [static fn (): Theme => new Theme(bundles: ['NowoXBundle' => ['classes' => ['nope' => 'x']]]), 'Unknown UiKit theme slot "nope"'];
        yield 'unknown attribute slot' => [static fn (): Theme => new Theme(attributes: ['nope' => []]), 'Unknown UiKit theme slot "nope"'];
        yield 'on* attribute' => [static fn (): Theme => new Theme(attributes: ['btn' => ['onclick' => 'x()']]), 'inline event handler'];
        yield 'unknown partial' => [static fn (): Theme => new Theme(partials: ['layout' => 'x.twig']), 'Unknown UiKit theme partial "layout"'];
        yield 'bad token' => [static fn (): Theme => new Theme(tokens: ['primary' => '</style>']), 'unsafe value'];
        yield 'bad bundle token' => [static fn (): Theme => new Theme(bundles: ['NowoXBundle' => ['tokens_dark' => ['X' => 'red']]]), 'token name "X"'];
        yield 'bad bundle name' => [static fn (): Theme => new Theme(bundles: ['nowo-x' => []]), 'Invalid UiKit theme bundle key "nowo-x"'];
    }

    /**
     * @param \Closure(): Theme $factory
     */
    #[DataProvider('invalidThemes')]
    public function testRejectsInvalidThemes(\Closure $factory, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $factory();
    }

    public function testUnknownSlotAndPartialAtRuntime(): void
    {
        $theme = new Theme();
        foreach ([static fn () => $theme->classFor('x'), static fn () => $theme->extraClasses('x'), static fn () => $theme->attributesFor('x'), static fn () => $theme->partial('x')] as $call) {
            try {
                $call();
                self::fail('Expected exception');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Unknown UiKit theme', $e->getMessage());
            }
        }
    }
}
