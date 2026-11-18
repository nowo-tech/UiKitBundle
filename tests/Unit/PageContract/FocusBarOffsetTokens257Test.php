<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;

/**
 * 2.57 focus bar offset tokens (--nowo-ui-focus-bar-height / -z, --nowo-ui-top-offset) and the edge_tabs region that
 * also takes the tabs of the `_edge_tab` partial and drops the edge-tabs gutter.
 */
final class FocusBarOffsetTokens257Test extends TestCase
{
    public function testTokensAreDeclaredOnRootAndTheBarUsesTheZToken(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertStringContainsString("  --nowo-ui-focus-bar-height: 0px;\n  --nowo-ui-focus-bar-z: 45;\n  --nowo-ui-top-offset: calc(var(--nowo-ui-host-top-height, 0px) + var(--nowo-ui-focus-bar-height, 0px));", $css, $file);
            self::assertStringContainsString("inset-inline-end: 0.75rem;\n  z-index: var(--nowo-ui-focus-bar-z, 45);", $css, $file);
            self::assertStringNotContainsString("inset-inline-end: 0.75rem;\n  z-index: 45;", $css, $file);
        }
    }

    public function testTheGutterDropsWhileTheRegionHidesTheLauncher(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertSame(1, substr_count($css, '.nowo-ui-edge-tabs-launcher[data-nowo-ui-edge-tabs-gutter]:not([hidden], [data-nowo-ui-focus-hidden])'), $file);
            self::assertSame(4, substr_count($css, ':not([hidden], [data-nowo-ui-focus-hidden])[data-nowo-ui-edge-tabs-gutter])'), $file);
            self::assertStringNotContainsString(':not([hidden])[data-nowo-ui-edge-tabs-gutter]', $css, $file);
        }
    }

    public function testTheEdgeTabPartialRootMatchesTheRegionSelector(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKit/partials/_edge_tab.html.twig', opts) }}"]);
        foreach ([['href' => '/m'], ['href' => '/m', 'button' => true], ['href' => '/m', 'slot' => 2]] as $opts) {
            self::assertStringContainsString('<div class="nowo-ui-edge-tab"', $twig->render('t', ['opts' => $opts]));
        }
        self::assertStringNotContainsString('<div class="nowo-ui-edge-tab"', $twig->render('t', ['opts' => ['href' => '/m', 'inline' => true]]), 'the inline link stays');
        $core = (string) file_get_contents(PageContractTwig::root().'/src/Resources/assets/src/nowo-ui-focus-mode-core.ts');
        self::assertStringContainsString("querySelectorAll('[data-nowo-ui-edge-tabs-launcher], .nowo-ui-edge-tab')", $core);
    }

    public function testDocsDescribeTheTokens(): void
    {
        $root = PageContractTwig::root();
        foreach (['USAGE', 'THEMING'] as $doc) {
            $text = (string) file_get_contents($root.'/docs/'.$doc.'.md');
            foreach (['--nowo-ui-focus-bar-height', '--nowo-ui-focus-bar-z', '--nowo-ui-top-offset', 'inset-block-start: var(--nowo-ui-top-offset)'] as $needle) {
                self::assertStringContainsString($needle, $text, $doc);
            }
        }
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/'.$file);
    }
}
