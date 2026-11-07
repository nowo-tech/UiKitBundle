<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The motion scale: tokens with defaults, used by the surfaces, overridable by a host (no `!important`), and shorter under reduced motion.
 */
final class MotionCssTest extends TestCase
{
    private const TOKENS = ['small' => '150ms', 'small-close' => '110ms', 'medium' => '200ms', 'medium-close' => '150ms', 'lift' => '4px', 'scale' => '0.98'];

    private function motionBlock(): string
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/nowo-ui.unlayered.css');

        return substr($css, (int) strpos($css, ' * Motion: one scale'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokens(): iterable
    {
        foreach (self::TOKENS as $name => $value) {
            yield $name => [$name, $value];
        }
    }

    #[DataProvider('tokens')]
    public function testEveryTokenHasADefaultAtZeroSpecificity(string $name, string $value): void
    {
        self::assertMatchesRegularExpression('/:where\(:root\) \{[^}]*--nowo-ui-motion-'.$name.': '.preg_quote($value, '/').';/', $this->motionBlock());
    }

    public function testNoImportantSoAHostCanOverride(): void
    {
        // After the first comment, which names `display: none !important`.
        $all = $this->motionBlock();
        $block = preg_replace('~/\*.*?\*/~s', '', substr($all, (int) strpos($all, '*/')));

        self::assertIsString($block);
        self::assertStringNotContainsString('!important', $block);
    }

    public function testReducedMotionAndDataMotionKeepOnlyAShortFade(): void
    {
        $block = $this->motionBlock();

        foreach (['/@media \(prefers-reduced-motion: reduce\) \{\s+:where\(:root\) \{([^}]*)\}/', "/:where\\(\\[data-motion='reduce'\\]\\) \\{([^}]*)\\}/"] as $pattern) {
            self::assertSame(1, preg_match($pattern, $block, $m), $pattern);
            self::assertStringContainsString('--nowo-ui-motion-lift: 0px', $m[1]);
            self::assertStringContainsString('--nowo-ui-motion-scale: 1', $m[1]);
            self::assertStringContainsString('--nowo-ui-motion-medium-close: 80ms', $m[1]);
        }
        self::assertLessThan(strpos($block, "[data-motion='full']"), strpos($block, 'prefers-reduced-motion: reduce'), 'an explicit setting comes after the system one');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function surfaces(): iterable
    {
        foreach (['.nowo-ui-dialog', '.nowo-ui-confirm-dialog', '.nowo-ui-confirm', '.nowo-ui-menu__panel', '.nowo-ui-kebab__menu', '.nowo-ui-popover', '.nowo-ui-date-range__panel', '.nowo-ui-emoji-picker__panel', '[data-collapse-panel-target=\'body\']', '.nowo-ui-filters-collapse', '[data-nowo-ui-tabs-panel]', '[data-rail-label]', '::backdrop', '@starting-style', 'allow-discrete'] as $surface) {
            yield $surface => [$surface];
        }
    }

    #[DataProvider('surfaces')]
    public function testEverySurfaceIsCovered(string $surface): void
    {
        self::assertStringContainsString($surface, $this->motionBlock());
    }

    public function testTheTokensAreDocumented(): void
    {
        $doc = (string) file_get_contents(\dirname(__DIR__, 2).'/docs/THEMING.md');
        foreach (array_keys(self::TOKENS) as $name) {
            self::assertStringContainsString('`--nowo-ui-motion-'.$name.'`', $doc);
        }
    }
}
