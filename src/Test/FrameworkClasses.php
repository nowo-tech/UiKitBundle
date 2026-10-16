<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Test;

use Nowo\UiKitBundle\Theme\ThemeSlots;

/**
 * Framework utility-class detection and the per-`css_framework` allow-lists of the page contract test
 * (REQ-UI-003 "no framework utility classes outside theme slots").
 *
 * A class is a violation when it looks like a Bootstrap / Tabler / Tailwind / Foundation class
 * ({@see self::isFrameworkClass()}) and is not allowed for the rendered framework ({@see self::allowed()}): UiKit
 * slot defaults of that framework ({@see ThemeSlots}), the framework classes the UiKit macros add on top of them
 * (row actions, icons sizing…) and UiKit's own helper classes. `nowo-ui-*` classes and bundle-prefixed classes
 * never match the detection patterns. Detection is a heuristic denylist: keep it in sync with the slot defaults.
 */
final class FrameworkClasses
{
    /**
     * Non-prefixed classes UiKit itself emits for every framework: helpers defined in nowo-ui.css / its JS, state
     * classes, and the 1.x pagination list markup (`ul.pagination > li.page-item > .page-link`, styled by
     * nowo-ui.css on every stack and kept for BC).
     *
     * @var list<string>
     */
    public const UIKIT = ['visually-hidden', 'is-active', 'is-disabled', 'is-open', 'active', 'show', 'disabled', 'collapsed', 'pagination', 'page-item', 'page-link'];

    /**
     * Framework classes emitted by UiKit macros outside the slot defaults (`ui.btn` outline / link variants), per
     * framework family.
     *
     * @var array<string, list<string>>
     */
    public const MACRO_EXTRAS = [
        'bootstrap' => ['btn-outline-primary', 'btn-link'],
        'bootstrap4' => ['btn-outline-primary', 'btn-link'],
        'tailwind' => ['underline-offset-2', 'px-0'],
    ];

    /**
     * Detection patterns (whole class name).
     *
     * @var list<string>
     */
    private const PATTERNS = [
        // Tailwind variants / arbitrary values are always framework classes
        '/[:\[\]]/',
        // Bootstrap 4/5 + Tabler
        '/^(btn|btn-.+|d-(none|inline|inline-block|block|flex|inline-flex|grid|table|table-cell|table-row)|d-(sm|md|lg|xl|xxl)-.+|flex-(row|column|wrap|nowrap|fill|grow-\d|shrink-\d).*|justify-content-.+|align-(items|self|content)-.+|order-.+)$/',
        '/^([mp][tbsexylr]?-(\d|auto|n\d)|[mp][tbsexylr]?-(sm|md|lg|xl|xxl)-.+|g[xy]?-\d|gap-\d|row-cols-.+|col(-.+)?|row|container(-fluid|-sm|-md|-lg|-xl|-xxl)?)$/',
        '/^(text-(start|end|center|left|right|justify|muted|primary|secondary|success|danger|warning|info|light|dark|body|white|black|wrap|nowrap|truncate|uppercase|lowercase|capitalize|decoration-.+|bg-.+|reset|opacity-.+|break)|bg-.+|border(-(top|bottom|start|end|left|right|0|1|2|3|4|5|primary|secondary|success|danger|warning|info|light|dark|white|opacity-.+))?|rounded(-.+)?|shadow(-.+)?|w-\d+|h-\d+|mw-\d+|mh-\d+|vw-\d+|vh-\d+|fw-.+|fst-.+|fs-\d|lh-.+|small|lead|display-\d|h[1-6]|font-monospace)$/',
        '/^(table(-.+)?|card(-.+)?|alert(-.+)?|badge(-.+)?|nav|nav-.+|navbar(-.+)?|form-.+|input-group(-.+)?|list-group(-.+)?|list-unstyled|list-inline(-.+)?|page-item|page-link|pagination(-.+)?|dropdown(-.+)?|modal(-.+)?|position-.+|float-.+|overflow-.+|opacity-\d+|sticky-.+|fixed-.+|stretched-link|text-truncate|link-.+|ratio(-.+)?|img-fluid|img-thumbnail|figure(-.+)?|spinner-.+|progress(-.+)?|accordion(-.+)?|breadcrumb(-.+)?|btn-close|offcanvas(-.+)?|toast(-.+)?|tooltip(-.+)?|popover(-.+)?|placeholder(-.+)?|avatar(-.+)?|page-header|page-title|page-pretitle|page-body|page-wrapper|datagrid(-.+)?|empty-.+|card-.+|hr-text|icon-.+|status(-.+)?|ribbon(-.+)?)$/',
        // Tailwind (no variant prefix)
        '/^(flex|inline-flex|grid|block|inline-block|inline|hidden|contents|items-.+|justify-(start|end|center|between|around|evenly|items-.+|self-.+)|content-.+|self-.+|place-.+|gap(-[xy])?-.+|space-[xy]-.+|-?[mp][trblxyse]?-(px|\d.*|auto)|w-.+|h-.+|min-[wh]-.+|max-[wh]-.+|size-.+|text-(xs|sm|base|lg|\d?xl|left|right|center|justify|start|end|[a-z]+-\d{2,3}|white|black|transparent|current|inherit)|font-(thin|extralight|light|normal|medium|semibold|bold|extrabold|black|sans|serif|mono)|leading-.+|tracking-.+|from-.+|via-.+|to-.+|divide-.+|ring(-.+)?|outline-.+|truncate|uppercase|lowercase|capitalize|italic|underline|no-underline|line-through|whitespace-.+|break-.+|absolute|relative|fixed|sticky|static|inset-.+|top-.+|left-.+|right-.+|bottom-.+|z-.+|grid-cols-.+|grid-rows-.+|col-span-.+|row-span-.+|basis-.+|grow(-.+)?|shrink(-.+)?|transition(-.+)?|duration-.+|ease-.+|cursor-.+|select-.+|sr-only|not-sr-only|mx-auto|prose(-.+)?|aspect-.+|object-.+|pointer-events-.+|shadow|antialiased|tabular-nums)$/',
        // Foundation 6
        '/^(button|button-group|callout|grid-x|grid-y|grid-container|cell|grid-margin-[xy]|grid-padding-[xy]|(small|medium|large)-(\d+|up-\d+|offset-\d+|auto|shrink)|align-(middle|center|right|justify|spaced|top|bottom|stretch)|hollow|tiny|expanded|clear|top-bar(-.+)?|menu|vertical|horizontal|show-for-.+|hide-for-.+|stack|hover|unstriped|tabs|tabs-title|tabs-content|tabs-panel|reveal|tiny|label|is-active)$/',
    ];

    /**
     * Whether a class looks like a framework class (Bootstrap / Tabler / Tailwind / Foundation).
     */
    public static function isFrameworkClass(string $class): bool
    {
        if (str_starts_with($class, 'nowo-ui-')) {
            return false;
        }
        foreach (self::PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Framework classes allowed in output rendered with a `css_framework`: slot defaults + macro extras + UiKit's
     * own helpers.
     *
     * @return list<string>
     */
    public static function allowed(string $framework): array
    {
        $family = ThemeSlots::family($framework);
        $allowed = self::UIKIT;
        if (null !== $family) {
            foreach (ThemeSlots::names() as $slot) {
                $allowed = [...$allowed, ...self::split(ThemeSlots::defaults($slot, $framework))];
            }
            $allowed = [...$allowed, ...(self::MACRO_EXTRAS[$family] ?? [])];
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Framework classes of a class list that are not allowed for the framework.
     *
     * @param list<string> $classes
     * @param list<string> $extraAllowed bundle allow-list (e.g. classes documented as host-provided)
     *
     * @return list<string>
     */
    public static function violations(array $classes, string $framework, array $extraAllowed = []): array
    {
        $allowed = array_flip([...self::allowed($framework), ...$extraAllowed]);

        return array_values(array_filter(
            array_unique($classes),
            static fn (string $class): bool => !isset($allowed[$class]) && self::isFrameworkClass($class),
        ));
    }

    /**
     * @return list<string>
     */
    private static function split(string $classes): array
    {
        return preg_split('/\s+/', $classes, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
