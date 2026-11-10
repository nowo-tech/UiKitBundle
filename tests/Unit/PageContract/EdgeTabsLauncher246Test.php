<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * 2.46 page-edge tabs launcher: the morphing chevron (kit inline SVG, CSS `d` transition, rotate fallback), the
 * launcher's own motion tokens and the softer default unfold.
 */
final class EdgeTabsLauncher246Test extends TestCase
{
    private const SVG = '<svg class="nowo-ui-edge-tabs-chevron" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path class="nowo-ui-edge-tabs-chevron__arm nowo-ui-edge-tabs-chevron__arm--upper" d="M15 6L9 12"/><path class="nowo-ui-edge-tabs-chevron__arm nowo-ui-edge-tabs-chevron__arm--lower" d="M9 12L15 18"/></svg>';

    public function testChevronRendersTheMorphSvgUnderEveryIconSet(): void
    {
        foreach (['bootstrap-icons', 'tabler-icons', 'svg_inline', 'ux_icon', 'none'] as $set) {
            $html = $this->twig($set)->render('t', ['opts' => ['icon' => 'chevron', 'label' => 'Tools']]);
            self::assertStringContainsString('<button type="button" class="nowo-ui-edge-tabs-launcher nowo-ui-edge-tabs-launcher--chevron" data-nowo-ui-edge-tabs-launcher', $html, $set);
            self::assertStringContainsString('aria-label="Tools" title="Tools"', $html, $set);
            self::assertStringContainsString('<span class="nowo-ui-edge-tab__icon" aria-hidden="true">'.self::SVG.'</span>', $html, $set);
            self::assertSame(1, substr_count($html, '<svg'), $set);
            self::assertStringNotContainsString('data-icon=', $html, $set.': ux_icon() is never called for the chevron');
            self::assertStringNotContainsString('chevron-left', $html, $set);
        }
    }

    public function testChevronIgnoresTheHostActionIconMapAndNeverReachesUxIcon(): void
    {
        $twig = $this->twig('ux_icon');
        $twig->addGlobal('nowo_ui_kit_action_icons', ['chevron_left' => 'tabler:missing-icon']);
        self::assertStringContainsString(self::SVG, $twig->render('t', ['opts' => ['icon' => 'chevron']]));
        // The default icon still goes through ux_icon (the strict stub throws on an unknown name).
        self::assertStringContainsString('<svg data-icon="bi:three-dots-vertical"></svg>', $twig->render('t', ['opts' => []]));
    }

    public function testTheLabelIsEscapedAndTheSvgHasNoVariableData(): void
    {
        $html = $this->twig('bootstrap-icons')->render('t', ['opts' => ['icon' => 'chevron', 'label' => '"><script>x</script>']]);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString(self::SVG, $html);
        $template = (string) file_get_contents(PageContractTwig::root().'/src/Resources/views/partials/_edge_tabs_launcher.html.twig');
        preg_match('#<svg class="nowo-ui-edge-tabs-chevron".*?</svg>#s', $template, $m);
        self::assertSame(self::SVG, $m[0] ?? null, 'a literal SVG: no Twig expression inside');
    }

    public function testMorphCss(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            $supports = $this->block($css, "@supports (d: path('M0 0')) {");
            self::assertStringContainsString('transition: d var(--nowo-ui-edge-tabs-icon-duration, 240ms) var(--nowo-ui-edge-tabs-icon-easing, cubic-bezier(0.34, 1.56, 0.64, 1));', $supports, $file);
            self::assertMatchesRegularExpression("/\\.nowo-ui-edge-tabs-chevron__arm--upper \\{\\s*d: path\\('M15 6L9 12'\\);/", $supports);
            self::assertMatchesRegularExpression("/\\.nowo-ui-edge-tabs-chevron__arm--lower \\{\\s*d: path\\('M9 12L15 18'\\);/", $supports);
            self::assertMatchesRegularExpression("/\\.nowo-ui-edge-tabs-launcher\\[aria-expanded='true'\\] \\.nowo-ui-edge-tabs-chevron__arm--upper \\{\\s*d: path\\('M6 9L12 15'\\);/", $supports);
            self::assertMatchesRegularExpression("/\\.nowo-ui-edge-tabs-launcher\\[aria-expanded='true'\\] \\.nowo-ui-edge-tabs-chevron__arm--lower \\{\\s*d: path\\('M12 15L18 9'\\);/", $supports);

            $fallback = $this->block($css, "@supports not (d: path('M0 0')) {");
            self::assertStringContainsString('transition: transform var(--nowo-ui-edge-tabs-icon-duration, 240ms) var(--nowo-ui-edge-tabs-icon-easing, cubic-bezier(0.34, 1.56, 0.64, 1));', $fallback);
            self::assertMatchesRegularExpression("/\\.nowo-ui-edge-tabs-launcher--chevron\\[aria-expanded='true'\\] \\.nowo-ui-edge-tab__icon \\{\\s*transform: rotate\\(-90deg\\);/", $fallback);

            // Same arm structure in both states (M + L), so the browser interpolates the end points.
            self::assertSame(4, preg_match_all("/d: path\\('M\\d+ \\d+L\\d+ \\d+'\\);/", $supports));
            self::assertStringContainsString('stroke: currentColor;', $css);

            // Reduced motion: no morph, no turn.
            $reduced = $this->block($css, '@media (prefers-reduced-motion: reduce) {', '.nowo-ui-edge-tab__link:not');
            self::assertStringContainsString('.nowo-ui-edge-tabs-chevron__arm):not([data-motion=\'full\'] *) {', $reduced);
            self::assertStringContainsString("[data-motion='reduce'] .nowo-ui-edge-tabs-chevron__arm) {\n  transition: none;", $css);
        }
    }

    public function testSofterUnfoldAndItsTokens(): void
    {
        $delay = 'calc(var(--nowo-ui-edge-tabs-order, 0) * var(--nowo-ui-edge-tabs-motion-stagger, var(--nowo-ui-edge-tabs-stagger, 30ms)))';
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertSame(2, substr_count($css, 'animation: nowo-ui-edge-tab-unfold var(--nowo-ui-edge-tabs-motion-duration, 260ms) var(--nowo-ui-edge-tabs-motion-easing, cubic-bezier(0.22, 1, 0.36, 1)) '.$delay.' both;'), $file);
            self::assertSame(2, substr_count($css, 'animation: nowo-ui-edge-tab-fold var(--nowo-ui-edge-tabs-motion-duration, 260ms) var(--nowo-ui-edge-tabs-motion-close-easing, var(--nowo-ui-motion-ease-in, ease-in)) '.$delay.' both;'), $file);
            self::assertSame(4, substr_count($css, "both;\n    transform-origin: 100% 50%;") + substr_count($css, "both;\n  transform-origin: 100% 50%;"), $file);
            self::assertSame(2, substr_count($css, 'scale(var(--nowo-ui-edge-tabs-motion-scale, 0.96));'), $file);
            self::assertStringNotContainsString('nowo-ui-edge-tab-unfold var(--nowo-ui-motion-medium', $css);
        }
    }

    private function twig(string $set): Environment
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKit/partials/_edge_tabs_launcher.html.twig', opts) }}"]);
        $twig->addGlobal('nowo_ui_kit_icon_set', $set);
        // Like symfony/ux-icons with no network: an icon it cannot resolve is an error (TaskBoard 2.0.2 hit a 500).
        $twig->addFunction(new TwigFunction('ux_icon', static function (string $name): string {
            if ('bi:three-dots-vertical' !== $name) {
                throw new RuntimeException('Icon "'.$name.'" not found.');
            }

            return '<svg data-icon="'.$name.'"></svg>';
        }, ['is_safe' => ['html']]));

        return $twig;
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/'.$file);
    }

    /** The text of the first block opening with $start (and, when given, containing $marker), braces balanced. */
    private function block(string $css, string $start, ?string $marker = null): string
    {
        $offset = 0;
        while (false !== ($at = strpos($css, $start, $offset))) {
            $depth = 0;
            for ($i = $at; $i < \strlen($css); ++$i) {
                if ('{' === $css[$i]) {
                    ++$depth;
                } elseif ('}' === $css[$i] && 0 === --$depth) {
                    break;
                }
            }
            $block = substr($css, $at, $i - $at + 1);
            if (null === $marker || str_contains($block, $marker)) {
                return $block;
            }
            $offset = $at + 1;
        }
        self::fail('No block '.$start);
    }
}
