<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * 2.45 page-edge tabs launcher: one size for every right-edge tab (size tokens), the spacing of the first unfolded tab
 * (step = height + gap), the unfold / fold animation and its reduced-motion block, and `icon: 'chevron'`.
 */
final class EdgeTabsLauncher245Test extends TestCase
{
    private const STEP = 'var(--nowo-ui-edge-tab-step, calc(var(--nowo-ui-edge-tab-height, 2.75rem) + var(--nowo-ui-edge-tab-gap, 0.5rem)))';

    public function testChevronIconRendersInEveryIconSet(): void
    {
        $expected = [
            'bootstrap-icons' => '<i class="bi bi-chevron-left" aria-hidden="true"></i>',
            'tabler-icons' => 'ti ti-chevron-left',
            'svg_inline' => 'M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z',
            'ux_icon' => '<svg data-icon="bi:chevron-left"></svg>',
        ];
        foreach ($expected as $set => $icon) {
            $html = $this->launcher($set, ['icon' => 'chevron']);
            self::assertStringContainsString('<button type="button" class="nowo-ui-edge-tabs-launcher nowo-ui-edge-tabs-launcher--chevron" data-nowo-ui-edge-tabs-launcher', $html, $set);
            self::assertStringContainsString($icon, $html, $set);
            self::assertStringNotContainsString('three-dots', $html, $set);
        }

        // The default stays `more` (BC) and prints no modifier.
        $html = $this->launcher('ux_icon', []);
        self::assertStringContainsString('<svg data-icon="bi:three-dots-vertical"></svg>', $html);
        self::assertStringNotContainsString('--chevron', $html);

        // Any other kit icon name still works; with a class the modifier comes first.
        $html = $this->launcher('bootstrap-icons', ['icon' => 'chevron', 'class' => 'mine']);
        self::assertStringContainsString('class="nowo-ui-edge-tabs-launcher nowo-ui-edge-tabs-launcher--chevron mine"', $html);
        self::assertStringContainsString('bi bi-grid', $this->launcher('bootstrap-icons', ['icon' => 'grid']));
    }

    public function testChevronFollowsTheHostActionIconMapOnUxIconHosts(): void
    {
        $twig = $this->twig('ux_icon');
        $twig->addGlobal('nowo_ui_kit_action_icons', ['chevron_left' => 'tabler:chevron-left']);
        self::assertStringContainsString('<svg data-icon="tabler:chevron-left"></svg>', $twig->render('t', ['opts' => ['icon' => 'chevron']]));
    }

    public function testSizeTokensDefaultToTodaysValues(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            $tab = $this->rule($css, '.nowo-ui-edge-tab__link');
            $launcher = $this->rule($css, '.nowo-ui-edge-tabs-launcher');
            foreach ([$tab, $launcher] as $rule) {
                self::assertStringContainsString('box-sizing: border-box;', $rule, $file);
                self::assertStringContainsString('width: var(--nowo-ui-edge-tab-width, 2.25rem);', $rule, $file);
                self::assertStringContainsString('height: var(--nowo-ui-edge-tab-height, 2.75rem);', $rule, $file);
                self::assertStringContainsString('font-size: var(--nowo-ui-edge-tab-icon, 1.15rem);', $rule, $file);
            }
            self::assertStringContainsString('top: calc(var(--nowo-ui-edge-tab-top, calc(var(--nowo-ui-header-height, 3.5rem) + 0.75rem)) + var(--nowo-ui-edge-tab-slot, 0) * '.self::STEP.');', $tab, $file);
            foreach (['.nowo-ui-edge-tab__link:hover', '.nowo-ui-edge-tabs-launcher:hover'] as $hover) {
                self::assertStringContainsString('width: var(--nowo-ui-edge-tab-hover-width, 2.6rem);', $this->rule($css, $hover), $hover);
            }
            $icon = $this->rule($css, '.nowo-ui-edge-tab__icon');
            self::assertStringContainsString('width: var(--nowo-ui-edge-tab-icon, 1.15rem);', $icon);
            self::assertStringContainsString('height: var(--nowo-ui-edge-tab-icon, 1.15rem);', $icon);
            self::assertSame(2, substr_count($css, '--nowo-ui-edge-tabs-gutter: var(--nowo-ui-edge-tab-width, 2.25rem);'));
            self::assertStringNotContainsString('--nowo-ui-edge-tab-step, 3.25rem', $css);
        }
    }

    /**
     * The open stacking puts tab `slot` at top + (slot + 1) × step and the launcher at top with the tab height, so the
     * first tab keeps the same gap (step − height) as the others. Defaults: 2.75 + 0.5 = the 3.25rem step of before.
     * Beacon: height 2.3rem, step 2.6rem → 0.3rem everywhere (it overlapped the 2.75rem launcher before 2.45).
     */
    public function testSpacingFormulaWithCustomHeightAndStep(): void
    {
        $css = $this->css('nowo-ui.unlayered.css');
        self::assertSame(2, substr_count($css, '+ (var(--nowo-ui-edge-tab-slot, 0) + 1) * '.self::STEP.');'), 'open stacking, both `when`');

        foreach ([[2.75, null, 0.5, 0.5], [2.3, 2.6, null, 0.3], [2.3, null, 0.3, 0.3], [2.0, 2.4, 0.1, 0.4]] as [$height, $step, $gap, $expectedGap]) {
            $resolvedStep = $step ?? ($height + ($gap ?? 0.5));
            $launcherBottom = 0 + $height;
            $tops = array_map(static fn (int $slot): float => 0 + ($slot + 1) * $resolvedStep, [0, 1, 2]);
            self::assertEqualsWithDelta($expectedGap, $tops[0] - $launcherBottom, 1e-9, 'launcher → first tab');
            self::assertEqualsWithDelta($expectedGap, $tops[1] - ($tops[0] + $height), 1e-9, 'tab → tab');
            self::assertEqualsWithDelta($expectedGap, $tops[2] - ($tops[1] + $height), 1e-9, 'tab → tab');
        }
        self::assertEqualsWithDelta(3.25, 2.75 + 0.5, 1e-9, 'default step unchanged');
    }

    public function testUnfoldAndFoldAnimationsAndReducedMotion(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertSame(2, substr_count($css, 'animation: nowo-ui-edge-tab-unfold var(--nowo-ui-motion-medium, 200ms) var(--nowo-ui-motion-ease-out, ease-out) calc(var(--nowo-ui-edge-tabs-order, 0) * var(--nowo-ui-edge-tabs-stagger, 30ms)) both;'), $file);
            self::assertSame(2, substr_count($css, 'animation: nowo-ui-edge-tab-fold var(--nowo-ui-motion-medium, 200ms) var(--nowo-ui-motion-ease-in, ease-in) calc(var(--nowo-ui-edge-tabs-order, 0) * var(--nowo-ui-edge-tabs-stagger, 30ms)) both;'), $file);
            $from = 'opacity: 0;\s*transform: translateY\(calc\(-1 \* \(var\(--nowo-ui-edge-tab-slot, 0\) \+ 1\) \* '.preg_quote(self::STEP, '/').'\)\);';
            self::assertMatchesRegularExpression('/@keyframes nowo-ui-edge-tab-unfold \{\s*from \{\s*'.$from.'\s*\}\s*\}/', $css, $file);
            self::assertMatchesRegularExpression('/@keyframes nowo-ui-edge-tab-fold \{\s*to \{\s*'.$from.'\s*\}\s*\}/', $css, $file);

            // Folding tabs stay painted and stacked while they fold; pinned / expanded tabs go back to their own top at once.
            self::assertSame(2, substr_count($css, ".nowo-ui-edge-tab__link:not([aria-expanded='true'], [data-nowo-ui-edge-tab-pinned], [data-nowo-ui-edge-tabs-folding]) {"));
            self::assertSame(2, preg_match_all('/:root\[data-nowo-ui-edge-tabs-ready\]\[data-nowo-ui-edge-tabs-open\]:has\([^)]*\)\) \.nowo-ui-edge-tab__link, :root\[data-nowo-ui-edge-tabs-ready\]\[data-nowo-ui-edge-tabs-closing\]:has\([^)]*\)\) \.nowo-ui-edge-tab__link\[data-nowo-ui-edge-tabs-folding\] \{/', $css));

            // Reduced motion: no unfold, no chevron turn.
            self::assertMatchesRegularExpression("/@media \\(prefers-reduced-motion: reduce\\) \\{\\s*\\.nowo-ui-edge-tab__link:not\\(\\[data-motion='full'\\] \\*\\) \\{\\s*animation: none !important;\\s*\\}\\s*:is\\(\\.nowo-ui-edge-tabs-launcher, \\.nowo-ui-edge-tabs-launcher \\.nowo-ui-edge-tab__icon\\):not\\(\\[data-motion='full'\\] \\*\\) \\{\\s*transition: none;\\s*\\}\\s*\\}/", $css, $file);
            self::assertMatchesRegularExpression("/\\[data-motion='reduce'\\] \\.nowo-ui-edge-tab__link \\{\\s*animation: none !important;\\s*\\}/", $css, $file);
            self::assertMatchesRegularExpression("/:is\\(\\[data-motion='reduce'\\] \\.nowo-ui-edge-tabs-launcher, \\[data-motion='reduce'\\] \\.nowo-ui-edge-tabs-launcher \\.nowo-ui-edge-tab__icon\\) \\{\\s*transition: none;\\s*\\}/", $css, $file);

            // The chevron points down while open, in about 200 ms.
            self::assertStringContainsString('transition: transform var(--nowo-ui-motion-medium, 200ms) var(--nowo-ui-motion-ease-out, ease-out);', $this->rule($css, '.nowo-ui-edge-tabs-launcher--chevron .nowo-ui-edge-tab__icon'));
            self::assertStringContainsString('transform: rotate(-90deg);', $this->rule($css, ".nowo-ui-edge-tabs-launcher--chevron[aria-expanded='true'] .nowo-ui-edge-tab__icon"));
        }
    }

    /**
     * @param array<string, mixed> $opts
     */
    private function launcher(string $set, array $opts): string
    {
        return $this->twig($set)->render('t', ['opts' => $opts]);
    }

    private function twig(string $set): Environment
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKit/partials/_edge_tabs_launcher.html.twig', opts) }}"]);
        $twig->addGlobal('nowo_ui_kit_icon_set', $set);
        // Like symfony/ux-icons with no network: an icon it cannot resolve is an error (TaskBoard 2.0.2 hit a 500).
        $twig->addFunction(new TwigFunction('ux_icon', static function (string $name): string {
            if (!\in_array($name, ['bi:chevron-left', 'bi:three-dots-vertical', 'tabler:chevron-left'], true)) {
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

    /** The body of the first rule whose selector is exactly $selector (at the start of a line). */
    private function rule(string $css, string $selector): string
    {
        self::assertMatchesRegularExpression('/^'.preg_quote($selector, '/').' \{\n(.*?)\n\}/ms', $css, $selector);
        preg_match('/^'.preg_quote($selector, '/').' \{\n(.*?)\n\}/ms', $css, $m);

        return $m[1];
    }
}
