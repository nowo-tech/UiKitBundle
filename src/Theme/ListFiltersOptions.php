<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Theme;

use InvalidArgumentException;

/**
 * Host options of the list scaffold filters (2.30, `nowo_ui_kit.theme.pages.list.filters`; read by
 * `page/_list_filters.html.twig` through `nowo_ui_theme.listFilters()`). The defaults print the 2.29 markup.
 *
 * - `display`: default display of the apply / clear / extra filter buttons (`ListPage::filtersDisplay` and
 *   `Action::display` win); set, the plain clear link (`ListPage::filtersClearHref`) follows it too.
 * - `submitIcon` / `clearIcon`: icons of the apply button / plain clear link when they show one (default `filter` /
 *   `filter_off`).
 * - `submitClass` / `clearClass`: host classes replacing the `btn_<variant>` slot classes of those buttons.
 * - `grid`: `auto-fit` (default), `auto-fill`, or a custom `grid-template-columns` track list (printed as the
 *   `--nowo-ui-filters-grid-columns` token by `nowo_ui_tokens()`: no inline style, CSP-safe).
 * - `fold*`: the fold toggle of the card (`partials/_filters_collapse.html.twig`, Stimulus peer `filters-collapse`).
 *
 * Immutable and request-independent (worker safe).
 */
final readonly class ListFiltersOptions
{
    public const DISPLAYS = ['text', 'icon', 'icon_text'];

    public const GRID_AUTO_FIT = 'auto-fit';

    public const GRID_AUTO_FILL = 'auto-fill';

    /** Grid modes a page may pick per call (`ListPage::filtersGrid`); the host option also takes a track list. */
    public const GRID_MODES = [self::GRID_AUTO_FIT, self::GRID_AUTO_FILL];

    /** Token (without `--nowo-ui-`) the custom track list is printed as. */
    public const GRID_TOKEN = 'filters-grid-columns';

    public const FOLD_COOKIE = 'nowo_ui_filters_collapsed';

    /** Keys of the `filters` config map. */
    private const KEYS = ['display', 'submit_icon', 'clear_icon', 'submit_class', 'clear_class', 'grid', 'fold'];

    /** Keys of the `filters.fold` config map. */
    private const FOLD_KEYS = ['enabled', 'cookie', 'cookie_path', 'icons', 'testids', 'morph'];

    /**
     * A track list: letters, digits, spaces and `. , % ( ) + * / -` only (repeat(), minmax(), min(), calc(), fr, rem,
     * var(--x)…); at most 200 characters, balanced parentheses, no url().
     */
    private const GRID_PATTERN = '#^[A-Za-z0-9 .,%()+*/-]{1,200}$#D';

    /** Cookie name: an RFC 6265 token subset. */
    private const COOKIE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/D';

    /** Cookie path: an absolute URL path without query, spaces or separators that would end the cookie string. */
    private const COOKIE_PATH_PATTERN = '#^/[A-Za-z0-9._~/-]{0,200}$#D';

    /** Icon name for components/_icon.html.twig (canonical kind or set-specific name such as `tabler:filter`). */
    private const ICON_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_:.-]{0,63}$/D';

    public function __construct(
        public ?string $display = null,
        public ?string $submitIcon = null,
        public ?string $clearIcon = null,
        public ?string $submitClass = null,
        public ?string $clearClass = null,
        public string $grid = self::GRID_AUTO_FIT,
        public bool $foldEnabled = false,
        public string $foldCookie = self::FOLD_COOKIE,
        public string $foldCookiePath = '/',
        public string $foldIconExpanded = 'chevron_up',
        public string $foldIconCollapsed = 'chevron_down',
        public bool $foldTestids = false,
        public bool $foldMorph = false,
    ) {
        if (null !== $display && !\in_array($display, self::DISPLAYS, true)) {
            throw new InvalidArgumentException(\sprintf('display "%s" must be null, %s.', $display, implode(', ', self::DISPLAYS)));
        }
        foreach (['submit_icon' => $submitIcon, 'clear_icon' => $clearIcon, 'fold.icons.expanded' => $foldIconExpanded, 'fold.icons.collapsed' => $foldIconCollapsed] as $key => $icon) {
            if (null !== $icon && 1 !== preg_match(self::ICON_PATTERN, $icon)) {
                throw new InvalidArgumentException(\sprintf('%s "%s" must be an icon name (letters, digits, "_", ":", ".", "-").', $key, $icon));
            }
        }
        foreach (['submit_class' => $submitClass, 'clear_class' => $clearClass] as $key => $class) {
            if (null !== $class && ('' === trim($class) || null !== Theme::classEntryError($class))) {
                throw new InvalidArgumentException(\sprintf('%s must be a non-empty class list (printable characters only).', $key));
            }
        }
        $error = self::gridError($grid);
        if (null !== $error) {
            throw new InvalidArgumentException($error);
        }
        if (1 !== preg_match(self::COOKIE_PATTERN, $foldCookie)) {
            throw new InvalidArgumentException(\sprintf('fold.cookie "%s" must be 1-64 letters, digits, "_" or "-".', $foldCookie));
        }
        if (1 !== preg_match(self::COOKIE_PATH_PATTERN, $foldCookiePath)) {
            throw new InvalidArgumentException(\sprintf('fold.cookie_path "%s" must be an absolute URL path (letters, digits, ".", "_", "~", "/", "-").', $foldCookiePath));
        }
    }

    /**
     * Builds the options from the processed `theme.pages.list.filters` map (missing keys = defaults).
     *
     * @param array<mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $unknown = array_diff(array_map(strval(...), array_keys($config)), self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('unknown key(s) "%s" (known: %s).', implode('", "', $unknown), implode(', ', self::KEYS)));
        }
        $fold = $config['fold'] ?? [];
        if (!\is_array($fold)) {
            throw new InvalidArgumentException('fold must be a map.');
        }
        $unknown = array_diff(array_map(strval(...), array_keys($fold)), self::FOLD_KEYS);
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('unknown fold key(s) "%s" (known: %s).', implode('", "', $unknown), implode(', ', self::FOLD_KEYS)));
        }
        $icons = $fold['icons'] ?? [];
        if (!\is_array($icons)) {
            throw new InvalidArgumentException('fold.icons must be a map {expanded, collapsed}.');
        }

        return new self(
            self::nullableString($config, 'display'),
            self::nullableString($config, 'submit_icon'),
            self::nullableString($config, 'clear_icon'),
            self::nullableString($config, 'submit_class'),
            self::nullableString($config, 'clear_class'),
            self::nullableString($config, 'grid') ?? self::GRID_AUTO_FIT,
            self::bool($fold, 'enabled', 'fold.enabled'),
            self::nullableString($fold, 'cookie', 'fold.cookie') ?? self::FOLD_COOKIE,
            self::nullableString($fold, 'cookie_path', 'fold.cookie_path') ?? '/',
            self::nullableString($icons, 'expanded', 'fold.icons.expanded') ?? 'chevron_up',
            self::nullableString($icons, 'collapsed', 'fold.icons.collapsed') ?? 'chevron_down',
            self::bool($fold, 'testids', 'fold.testids'),
            self::bool($fold, 'morph', 'fold.morph'),
        );
    }

    /**
     * Error message for an invalid `theme.pages.list.filters` map, null when valid.
     */
    public static function error(mixed $config): ?string
    {
        if (!\is_array($config)) {
            return 'a map is expected.';
        }
        try {
            self::fromConfig($config);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Error message for an invalid grid option (`auto-fit`, `auto-fill` or a safe track list), null when valid.
     */
    public static function gridError(string $grid): ?string
    {
        if (\in_array($grid, self::GRID_MODES, true)) {
            return null;
        }
        $message = \sprintf('grid "%s" must be auto-fit, auto-fill or a grid-template-columns track list (letters, digits, spaces and . , %% ( ) + * / - only, at most 200 characters, balanced parentheses, no url()).', $grid);
        if (1 !== preg_match(self::GRID_PATTERN, $grid) || 1 === preg_match('/url\s*\(/i', $grid) || '' === trim($grid)) {
            return $message;
        }
        $depth = 0;
        foreach (str_split($grid) as $char) {
            $depth += match ($char) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
            if ($depth < 0) {
                return $message;
            }
        }

        return 0 === $depth ? null : $message;
    }

    /**
     * The custom track list (printed as the `--nowo-ui-filters-grid-columns` token), null for auto-fit / auto-fill.
     */
    public function gridColumns(): ?string
    {
        return \in_array($this->grid, self::GRID_MODES, true) ? null : trim($this->grid);
    }

    /**
     * Modifier class of the filters form for the effective grid mode ('' = the stylesheet default: auto-fit, or the
     * host track list token). `$perCall` (`ListPage::filtersGrid`) wins over the host option.
     */
    public function gridClass(?string $perCall = null): string
    {
        $mode = $perCall ?? $this->grid;
        if (self::GRID_AUTO_FILL === $mode) {
            return 'nowo-ui-filters--grid-auto-fill';
        }
        if (self::GRID_AUTO_FIT === $mode && null !== $this->gridColumns()) {
            return 'nowo-ui-filters--grid-auto-fit';
        }

        return '';
    }

    /**
     * Variables of `partials/_filters_collapse.html.twig` for the list scaffold (without the page-specific ones).
     *
     * @return array{fold_cookie: string, fold_cookie_path: string, icons: array{expanded: string, collapsed: string}, testids: bool, morph: bool}
     */
    public function foldVariables(): array
    {
        return [
            'fold_cookie' => $this->foldCookie,
            'fold_cookie_path' => $this->foldCookiePath,
            'icons' => ['expanded' => $this->foldIconExpanded, 'collapsed' => $this->foldIconCollapsed],
            'testids' => $this->foldTestids,
            'morph' => $this->foldMorph,
        ];
    }

    /**
     * @param array<mixed> $map
     */
    private static function nullableString(array $map, string $key, ?string $path = null): ?string
    {
        $value = $map[$key] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('%s must be a string or null, %s given.', $path ?? $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<mixed> $map
     */
    private static function bool(array $map, string $key, string $path): bool
    {
        $value = $map[$key] ?? false;
        if (!\is_bool($value)) {
            throw new InvalidArgumentException(\sprintf('%s must be a boolean, %s given.', $path, get_debug_type($value)));
        }

        return $value;
    }
}
