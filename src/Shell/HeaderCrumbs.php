<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

/**
 * The compact breadcrumb of the top bar (2.27, partial `header_crumbs`), computed from translated levels:
 *
 * - the last level is the current page (never a link, `aria-current="page"`), whatever its href;
 * - with `dropHomeCrumb`, the first level is dropped when its path equals the home link's (query and trailing slash
 *   ignored) and at least one other level remains, because the home link already stands for it;
 * - with more than `maxVisible` levels (at least 2), every level except the first and the last `maxVisible - 1` is a
 *   "middle" level: hidden behind the "…" disclosure once the script collapses the trail, and listed in its panel;
 * - `up` is the nearest linked ancestor of the current level (the phone "‹" link), null without one.
 *
 * Pure value object: nothing is kept between requests.
 */
final readonly class HeaderCrumbs
{
    public const DEFAULT_MAX_VISIBLE = 3;

    /**
     * @param list<array{label: string, href: string|null, middle: bool, current: bool}> $items
     * @param list<array{label: string, href: string|null}>                              $panel the middle levels, in order
     * @param array{label: string, href: string}|null                                    $up
     */
    public function __construct(
        public array $items = [],
        public array $panel = [],
        public ?array $up = null,
    ) {
    }

    /**
     * @param list<array{label: string, href: string|null}> $levels translated levels, first to current
     */
    public static function build(array $levels, ?string $homeHref = null, bool $dropHomeCrumb = true, int $maxVisible = self::DEFAULT_MAX_VISIBLE): self
    {
        if ($dropHomeCrumb && \count($levels) > 1 && null !== $homeHref && null !== $levels[0]['href'] && self::samePath($levels[0]['href'], $homeHref)) {
            array_shift($levels);
        }
        $count = \count($levels);
        $maxVisible = max(2, $maxVisible);
        $lastMiddle = $count > $maxVisible ? $count - $maxVisible : 0;
        $items = [];
        $panel = [];
        foreach ($levels as $i => $level) {
            $middle = $i >= 1 && $i <= $lastMiddle;
            $current = $i === $count - 1;
            $items[] = ['label' => $level['label'], 'href' => $current ? null : $level['href'], 'middle' => $middle, 'current' => $current];
            if ($middle) {
                $panel[] = ['label' => $level['label'], 'href' => $level['href']];
            }
        }
        $up = null;
        for ($i = $count - 2; $i >= 0; --$i) {
            $href = $levels[$i]['href'];
            if (null !== $href && '' !== $href) {
                $up = ['label' => $levels[$i]['label'], 'href' => $href];
                break;
            }
        }

        return new self($items, $panel, $up);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /** Whether some levels collapse behind the "…" disclosure (the `data-collapsible` flag and the "more" item). */
    public function isCollapsible(): bool
    {
        return [] !== $this->panel;
    }

    private static function samePath(string $a, string $b): bool
    {
        return self::path($a) === self::path($b);
    }

    private static function path(string $href): string
    {
        $path = parse_url($href, \PHP_URL_PATH);
        $path = \is_string($path) && '' !== $path ? $path : '/';

        return '/' === $path ? $path : rtrim($path, '/');
    }
}
