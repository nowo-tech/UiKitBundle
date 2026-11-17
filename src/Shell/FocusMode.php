<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

/**
 * Shell focus mode (2.55, config `nowo_ui_kit.theme.focus_mode`, Twig global `nowo_ui_kit_focus_mode`): the names and
 * rules the config validates. The runtime is `js/nowo-ui-focus-mode.js`; the markup contract is in docs/USAGE.md.
 *
 * Regions are the shell parts hidden in focus mode. The built-in ones map to the kit shell markup (sidebar = the aside
 * and its phone backdrop, header = the top bar, footer, side_panels = the 2.25 side panels with their toggles,
 * edge_tabs = the 2.41 launcher); `dock` and any other name only take the elements a host marks with
 * `data-nowo-ui-focus-region="<name>"` (a messages dock, a chat launcher…), which every region also takes.
 *
 * The keep list names elements that stay visible inside hidden regions (`data-nowo-ui-focus-keep="<name>"`, e.g. a
 * global search or a timer chip); `breadcrumb_min` is built in: the focus bar then shows a one-line breadcrumb next to
 * its exit button.
 */
final class FocusMode
{
    /** Region names with a built-in selector in the kit shell. */
    public const BUILT_IN_REGIONS = ['sidebar', 'header', 'footer', 'side_panels', 'edge_tabs', 'dock'];

    /** Regions hidden by default (all the built-in ones). */
    public const DEFAULT_REGIONS = self::BUILT_IN_REGIONS;

    /** Keep name with built-in behaviour: the minimal breadcrumb line of the focus bar. */
    public const KEEP_BREADCRUMB = 'breadcrumb_min';

    /** Default keyboard shortcut (no modifier: never while typing). */
    public const DEFAULT_SHORTCUT = 'F';

    private const NAME = '/^[a-z][a-z0-9_]{0,31}$/D';

    private const SHORTCUT = '/^(?:(?:ctrl|control|alt|shift|meta)\+){0,4}[a-z0-9]$/iD';

    /**
     * Region and keep names: a lower-case letter, then up to 31 of `a-z 0-9 _`.
     */
    public static function isValidName(mixed $name): bool
    {
        return \is_string($name) && 1 === preg_match(self::NAME, $name);
    }

    /**
     * A shortcut: optional `Ctrl+` / `Control+` / `Alt+` / `Shift+` / `Meta+` modifiers and one letter or digit
     * (`F`, `Alt+Shift+F`).
     */
    public static function isValidShortcut(mixed $shortcut): bool
    {
        return \is_string($shortcut) && 1 === preg_match(self::SHORTCUT, $shortcut);
    }
}
