<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.3.1: nowo-ui.css ships inside `@layer nowo-ui`, where a normal declaration loses to any unlayered host rule or
 * utility class (`.d-flex`, `.grid`, `.flex`…) on the same element. Rules that hide an element because of a state
 * (folded filters, rail aside, closed dialog / group, `[hidden]`, dark theme, morph-ready icons) are `!important`, as
 * are the state rules that show the paired element again (same specificity order as before).
 */
final class StateHideCssTest extends TestCase
{
    private const DIR = __DIR__.'/../../src/Resources/public/css';

    /** Selector fragments that mark a state-dependent rule. */
    private const STATE_MARKERS = ['.is-collapsed', '.is-aside-rail', '.is-main-content', '.is-open', '[hidden]', ':not([open])', '[data-theme="dark"]', '.is-morph-ready', ':not(.is-morph-ready)'];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function stateRules(): iterable
    {
        yield 'folded filters hide the fields' => ['form:has(> .nowo-ui-filters-collapse.is-collapsed) > :not(.nowo-ui-filters-collapse)', 'display: none !important;'];
        yield 'filters summary [hidden]' => ['.nowo-ui-filters-collapse__summary[hidden]', 'display: none !important;'];
        yield 'row detail button [hidden]' => ['.nowo-ui-row-detail-btn[hidden]', 'display: none !important;'];
        yield 'closed dialog' => ['.nowo-ui-dialog:not([open])', 'display: none !important;'];
        yield 'rail aside labels' => ['.nowo-ui-shell.is-aside-rail .nowo-ui-aside__chevron', 'display: none !important;'];
        yield 'closed aside group' => ['.nowo-ui-aside__group-panel', 'display: none !important;'];
        yield 'open aside group' => ['.nowo-ui-aside__group.is-open .nowo-ui-aside__group-panel', 'display: flex !important;'];
        yield 'theme toggle (light)' => ['.nowo-ui-theme-toggle__icon--moon', 'display: none !important;'];
        yield 'theme toggle (dark sun)' => ['[data-theme="dark"] .nowo-ui-theme-toggle__icon--sun', 'display: none !important;'];
        yield 'theme toggle (dark moon)' => ['[data-theme="dark"] .nowo-ui-theme-toggle__icon--moon', 'display: block !important;'];
        yield 'width toggle (full)' => ['.nowo-ui-width-toggle__icon--content', 'display: none !important;'];
        yield 'width toggle (content, hide)' => ['.nowo-ui-shell.is-main-content .nowo-ui-width-toggle__icon--full', 'display: none !important;'];
        yield 'width toggle (content, show)' => ['.nowo-ui-shell.is-main-content .nowo-ui-width-toggle__icon--content', 'display: block !important;'];
        yield 'responsive table icons' => ['.nowo-ui-responsive-table__toggle:not(.is-morph-ready) .nowo-ui-responsive-table__morph', 'display: none !important;'];
        yield 'responsive table icon (responsive mode)' => [".nowo-ui-responsive-table[data-table-mode='responsive'] .nowo-ui-responsive-table__toggle:not(.is-morph-ready) .nowo-ui-responsive-table__icon--responsive", 'display: inline-flex !important;'];
        yield 'filters fold icons' => ['.nowo-ui-filters-collapse__toggle:not(.is-morph-ready) .nowo-ui-filters-collapse__morph', 'display: none !important;'];
        yield 'filters fold icon (folded)' => ['.nowo-ui-filters-collapse.is-collapsed .nowo-ui-filters-collapse__toggle:not(.is-morph-ready) .nowo-ui-filters-collapse__icon--collapsed', 'display: inline-flex !important;'];
        yield 'disclosure icons' => [':not(.is-morph-ready) > .nowo-ui-disclosure-icon .nowo-ui-disclosure-icon__morph', 'display: none !important;'];
        yield 'disclosure icon (closed)' => ['details:not([open]) > summary:not(.is-morph-ready) > .nowo-ui-disclosure-icon .nowo-ui-disclosure-icon__static--closed', 'display: inline-flex !important;'];
    }

    #[DataProvider('stateRules')]
    public function testStateRuleIsImportantInBothStylesheets(string $selector, string $declaration): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $bodies = array_values(array_filter(self::rules($file), static fn (array $rule): bool => \in_array($selector, $rule[0], true)));

            self::assertCount(1, $bodies, $file.': one rule for '.$selector);
            self::assertStringContainsString($declaration, $bodies[0][1], $file.': '.$selector);
        }
    }

    /**
     * Guard for future rules: a state-dependent rule that hides an element must be `!important`.
     */
    public function testEveryStateHideRuleIsImportant(): void
    {
        $offenders = [];
        foreach (self::rules('nowo-ui.unlayered.css') as [$selectors, $body]) {
            if (1 !== preg_match('/(?<![-\w])display:\s*none\s*;/', $body)) {
                continue;
            }
            foreach ($selectors as $selector) {
                foreach (self::STATE_MARKERS as $marker) {
                    if (str_contains($selector, $marker)) {
                        $offenders[] = $selector;

                        continue 3;
                    }
                }
            }
        }

        self::assertSame([], $offenders, 'State-hide rules must use `display: none !important` (they live in @layer nowo-ui).');
    }

    /**
     * @return list<array{list<string>, string}> [selectors, declarations] of every innermost rule
     */
    private static function rules(string $file): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(self::DIR.'/'.$file));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, \PREG_SET_ORDER);
        $rules = [];
        foreach ($matches as $match) {
            $selectors = array_map(static fn (string $s): string => trim((string) preg_replace('/\s+/', ' ', $s)), explode(',', $match[1]));
            $rules[] = [$selectors, $match[2]];
        }

        return $rules;
    }
}
