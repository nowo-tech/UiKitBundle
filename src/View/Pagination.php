<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use ArrayAccess;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Request;

/**
 * The only pagination shape of the page contract (REQ-UI-003). `route` null = current request route.
 * Factories normalise the shapes found in bundles (`fromArray`), Pagerfanta (`fromPagerfanta`) and
 * KnpPaginator (`fromKnp`) by duck typing — neither library is a dependency.
 *
 * Implements `ArrayAccess` with the 1.x snake_case keys of {@see self::toArray()} (read-only), so host overrides of
 * `_pagination` written for 1.x read `pagination.per_page` / `pagination.total_pages` from the object too.
 *
 * 2.4: {@see self::fromTotal()} / {@see self::fromRequest()} build a clamped pagination from a row count and the
 * requested page / per-page (the `PagePagination::fromTotal()` helper copied across the apps), with
 * {@see self::offset()} / {@see self::limit()} for the query; `$pagination['offset']` and `['limit']` read them too.
 *
 * 2.6: {@see self::$variant} `compact` renders the `pagination_compact` partial (previous / "Page n of m" / next and,
 * with {@see self::$allowedPerPage}, a per-page `<select>`) instead of the numbered pills.
 *
 * 2.23: {@see self::$mode} `infinite` / `responsive` (opt-in; default `pager`, unchanged markup) — the pager links are
 * still rendered (no-JS fallback) and the `list-pagination` Stimulus peer loads the next pages into the list
 * (`data-nowo-ui-list-rows`) as the user scrolls, with a "Load more" button; `responsive` picks
 * {@see self::$modeNarrow} / {@see self::$modeWide} by the {@see self::$breakpoint} media query. See
 * {@see self::withMode()} and docs/USAGE.md#pagination-modes-223.
 *
 * 2.77: keyset cursors for the infinite peer — {@see self::withNextQuery()} merges query parameters (e.g.
 * `['after' => $cursor]`) only into the "next" URL (`data-nowo-ui-pagination-next` and the pager's Next link), and
 * names the parameters the peer strips from the address bar ({@see self::$cursorParams}, default the next-query keys),
 * so a reload or a shared link falls back to `?page=N`. {@see self::withoutTotal()} / {@see self::progressiveFetch()}
 * build a pagination without a row count for the "Load more" requests ({@see PaginationRequest::isLoadMore()}): no
 * "of N" line, the next URL while `$hasNext`. The cursor is opaque to the kit (a string): hosts sign or validate it.
 *
 * @implements ArrayAccess<array-key, mixed>
 */
final readonly class Pagination implements ArrayAccess
{
    /** Per-page values {@see self::fromTotal()} accepts by default. */
    public const ALLOWED_PER_PAGE = [10, 25, 50, 100];

    /** Per-page value {@see self::fromTotal()} falls back to (when allowed, else the first allowed value). */
    public const DEFAULT_PER_PAGE = 25;

    /** Numbered pills with the "Showing x–y of z" line (default, 1.x markup). */
    public const VARIANT_FULL = 'full';

    /** Previous / "Page n of m" / next (+ per-page select), 2.6. */
    public const VARIANT_COMPACT = 'compact';

    /** @var list<string> */
    public const VARIANTS = [self::VARIANT_FULL, self::VARIANT_COMPACT];

    /** Numbered links only (default, 2.22 markup). */
    public const MODE_PAGER = 'pager';

    /** Pager links + the `list-pagination` peer appending the next pages (2.23). */
    public const MODE_INFINITE = 'infinite';

    /** `modeNarrow` below the breakpoint, `modeWide` above (2.23). */
    public const MODE_RESPONSIVE = 'responsive';

    /** @var list<string> */
    public const MODES = [self::MODE_PAGER, self::MODE_INFINITE, self::MODE_RESPONSIVE];

    /** Media query of the narrow side of `responsive` (2.23). */
    public const DEFAULT_BREAKPOINT = '(max-width: 900px)';

    /** Automatic loads before only the "Load more" button loads more (2.23). */
    public const DEFAULT_AUTO_LOADS = 3;

    /** Longest next-query value accepted (2.77): a cursor is short, an unbounded one is a mistake. */
    public const MAX_NEXT_QUERY_VALUE_LENGTH = 2048;

    public int $page;

    public int $perPage;

    public int $total;

    public int $totalPages;

    /** @var array<string, string> query parameters merged only into the next URL (2.77) */
    public array $nextQuery;

    /** @var list<string> query parameters the peer strips from the address bar (2.77; default the next-query keys) */
    public array $cursorParams;

    /**
     * @param array<string, mixed>      $query          extra query parameters kept across pages (filters, sort)
     * @param array<string, mixed>|null $routeParams    route parameters (null: the current request's)
     * @param list<int>                 $allowedPerPage per-page choices (2.4; `_pagination` with `per_page_selector: true` links them)
     * @param string                    $variant        `full` (default) or `compact` (2.6)
     * @param string                    $mode           `pager` (default), `infinite` or `responsive` (2.23)
     * @param string                    $modeNarrow     `responsive`: mode below the breakpoint, `pager` | `infinite` (2.23)
     * @param string                    $modeWide       `responsive`: mode above the breakpoint, `pager` | `infinite` (2.23)
     * @param string                    $breakpoint     `responsive`: media query of the narrow side (2.23)
     * @param int                       $autoLoads      pages loaded on scroll before only "Load more" loads (2.23; 0 = button only)
     * @param array<string, mixed>      $nextQuery      parameters merged only into the next URL, e.g. a keyset cursor (2.77; scalar values, cast to strings, null dropped)
     * @param list<string>|null         $cursorParams   parameters the peer strips from the address bar (2.77; null: the next-query keys)
     * @param bool                      $totalKnown     false: no row count (2.77, "Load more" requests); `$total` is 0 and `$totalPages` page (+1 with a next page)
     */
    public function __construct(
        int $page,
        int $perPage,
        int $total,
        ?int $totalPages = null,
        public ?string $route = null,
        public array $query = [],
        public ?array $routeParams = null,
        public string $pageParam = 'page',
        public string $perPageParam = 'per_page',
        public array $allowedPerPage = [],
        public string $variant = self::VARIANT_FULL,
        public string $mode = self::MODE_PAGER,
        public string $modeNarrow = self::MODE_INFINITE,
        public string $modeWide = self::MODE_PAGER,
        public string $breakpoint = self::DEFAULT_BREAKPOINT,
        public int $autoLoads = self::DEFAULT_AUTO_LOADS,
        array $nextQuery = [],
        ?array $cursorParams = null,
        public bool $totalKnown = true,
    ) {
        if (!\in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException(\sprintf('Unknown pagination variant "%s" (known: %s).', $variant, implode(', ', self::VARIANTS)));
        }
        if (!\in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException(\sprintf('Unknown pagination mode "%s" (known: %s).', $mode, implode(', ', self::MODES)));
        }
        foreach (['modeNarrow' => $modeNarrow, 'modeWide' => $modeWide] as $name => $value) {
            if (!\in_array($value, [self::MODE_PAGER, self::MODE_INFINITE], true)) {
                throw new InvalidArgumentException(\sprintf('Invalid pagination %s "%s" (pager|infinite).', $name, $value));
            }
        }
        if ('' === trim($breakpoint) || \strlen($breakpoint) > 200 || 1 === preg_match('/[<>"{};]/', $breakpoint)) {
            throw new InvalidArgumentException(\sprintf('Invalid pagination breakpoint "%s" (a media query such as "(max-width: 900px)").', $breakpoint));
        }
        if ($autoLoads < 0) {
            throw new InvalidArgumentException('Pagination autoLoads must be 0 or more.');
        }
        $this->nextQuery = $this->normaliseNextQuery($nextQuery, $pageParam, $perPageParam);
        $this->cursorParams = $this->normaliseCursorParams($cursorParams ?? array_keys($this->nextQuery), $pageParam, $perPageParam);
        $this->perPage = max(1, $perPage);
        $this->total = $totalKnown ? max(0, $total) : 0;
        $this->page = max(1, $page);
        $this->totalPages = $totalKnown ? max(1, $totalPages ?? (int) ceil($this->total / $this->perPage)) : max($this->page, $totalPages ?? $this->page);
    }

    /**
     * Pagination of a "Load more" request without a row count (2.77): `$hasNext` says whether a next page exists
     * (e.g. the query read `$perPage + 1` rows). Mode `infinite` by default, so the partial prints the peer contract
     * the peer reads (the next URL); no "of N" info line, no page numbers. Pair with {@see self::withNextQuery()}.
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $routeParams
     *
     * @return self Fluent interface
     */
    public static function progressiveFetch(
        int $page,
        int $perPage,
        bool $hasNext,
        ?string $route = null,
        array $query = [],
        ?array $routeParams = null,
        string $pageParam = 'page',
        string $perPageParam = 'per_page',
        string $mode = self::MODE_INFINITE,
    ): self {
        $page = max(1, $page);

        return new self($page, $perPage, 0, $page + ($hasNext ? 1 : 0), $route, $query, $routeParams, $pageParam, $perPageParam, mode: $mode, totalKnown: false);
    }

    /**
     * Accepts the 1.x `_pagination` shape and the bundle variants: page|current_page|currentPage,
     * per_page|perPage|page_size|limit, total|total_items|totalItems|count, total_pages|totalPages|pages|last_page,
     * plus route, query, route_params, (2.6) variant and (2.23) mode, mode_narrow, mode_wide, breakpoint, auto_loads.
     *
     * @param array<string, mixed> $data
     *
     * @return self Fluent interface
     */
    public static function fromArray(array $data): self
    {
        $int = static function (array $keys, ?int $default) use ($data): ?int {
            foreach ($keys as $key) {
                if (isset($data[$key]) && is_numeric($data[$key])) {
                    return (int) $data[$key];
                }
            }

            return $default;
        };
        $query = $data['query'] ?? [];
        $routeParams = $data['route_params'] ?? null;

        return new self(
            (int) $int(['page', 'current_page', 'currentPage'], 1),
            (int) $int(['per_page', 'perPage', 'page_size', 'limit'], 25),
            (int) $int(['total', 'total_items', 'totalItems', 'count'], 0),
            $int(['total_pages', 'totalPages', 'pages', 'last_page'], null),
            isset($data['route']) && \is_string($data['route']) && '' !== $data['route'] ? $data['route'] : null,
            \is_array($query) ? $query : [],
            \is_array($routeParams) ? $routeParams : null,
            variant: isset($data['variant']) && \is_string($data['variant']) ? $data['variant'] : self::VARIANT_FULL,
            mode: isset($data['mode']) && \is_string($data['mode']) ? $data['mode'] : self::MODE_PAGER,
            modeNarrow: isset($data['mode_narrow']) && \is_string($data['mode_narrow']) ? $data['mode_narrow'] : self::MODE_INFINITE,
            modeWide: isset($data['mode_wide']) && \is_string($data['mode_wide']) ? $data['mode_wide'] : self::MODE_PAGER,
            breakpoint: isset($data['breakpoint']) && \is_string($data['breakpoint']) ? $data['breakpoint'] : self::DEFAULT_BREAKPOINT,
            autoLoads: (int) $int(['auto_loads'], self::DEFAULT_AUTO_LOADS),
        );
    }

    /**
     * Pagination of `$total` rows for the requested page / per-page (2.4): `$perPage` not in `$allowedPerPage` falls
     * back to `$defaultPerPage` (default {@see self::DEFAULT_PER_PAGE}; the first allowed value when that is not
     * allowed), the page is clamped to 1..totalPages (an empty list has one page). Same results as the apps'
     * `PagePagination::fromTotal()` (page, per_page, total, total_pages, offset) with the default arguments.
     *
     * @param list<int>                 $allowedPerPage
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $routeParams
     *
     * @return self Fluent interface
     */
    public static function fromTotal(
        int $total,
        int $page,
        int $perPage,
        array $allowedPerPage = self::ALLOWED_PER_PAGE,
        ?int $defaultPerPage = null,
        ?string $route = null,
        array $query = [],
        ?array $routeParams = null,
        string $pageParam = 'page',
        string $perPageParam = 'per_page',
        string $variant = self::VARIANT_FULL,
    ): self {
        $allowed = array_values(array_unique(array_filter($allowedPerPage, static fn (int $value): bool => $value > 0)));
        if ([] === $allowed) {
            throw new InvalidArgumentException('Pagination::fromTotal() needs at least one positive allowed per-page value.');
        }
        if (!\in_array($perPage, $allowed, true)) {
            $default = $defaultPerPage ?? self::DEFAULT_PER_PAGE;
            $perPage = \in_array($default, $allowed, true) ? $default : $allowed[0];
        }
        $total = max(0, $total);
        $totalPages = max(1, (int) ceil($total / $perPage));

        return new self(max(1, min($page, $totalPages)), $perPage, $total, $totalPages, $route, $query, $routeParams, $pageParam, $perPageParam, $allowed, $variant);
    }

    /**
     * {@see self::fromTotal()} with the page / per-page read from the request query (`page` / `per_page`, or the
     * given parameter names); the other query parameters are kept across pages.
     *
     * @param list<int> $allowedPerPage
     *
     * @return self Fluent interface
     */
    public static function fromRequest(
        Request $request,
        int $total,
        int $defaultPerPage = self::DEFAULT_PER_PAGE,
        array $allowedPerPage = self::ALLOWED_PER_PAGE,
        ?string $route = null,
        string $pageParam = 'page',
        string $perPageParam = 'per_page',
        string $variant = self::VARIANT_FULL,
    ): self {
        $query = $request->query->all();
        unset($query[$pageParam], $query[$perPageParam]);

        return self::fromTotal(
            $total,
            $request->query->getInt($pageParam, 1),
            $request->query->getInt($perPageParam, $defaultPerPage),
            $allowedPerPage,
            $defaultPerPage,
            $route,
            $query,
            null,
            $pageParam,
            $perPageParam,
            $variant,
        );
    }

    /**
     * Pagerfanta (`getCurrentPage`, `getMaxPerPage`, `getNbResults`, `getNbPages`).
     *
     * @param array<string, mixed> $query
     *
     * @return self Fluent interface
     */
    public static function fromPagerfanta(object $pager, ?string $route = null, array $query = []): self
    {
        self::assertMethods($pager, ['getCurrentPage', 'getMaxPerPage', 'getNbResults', 'getNbPages'], 'Pagerfanta');

        /* @phpstan-ignore method.notFound, method.notFound, method.notFound, method.notFound */
        return new self((int) $pager->getCurrentPage(), (int) $pager->getMaxPerPage(), (int) $pager->getNbResults(), (int) $pager->getNbPages(), $route, $query);
    }

    /**
     * KnpPaginator pagination (`getCurrentPageNumber`, `getItemNumberPerPage`, `getTotalItemCount`; route and
     * params from `getRoute` / `getParams` when available).
     *
     * @param array<string, mixed> $query
     *
     * @return self Fluent interface
     */
    public static function fromKnp(object $pagination, ?string $route = null, array $query = []): self
    {
        self::assertMethods($pagination, ['getCurrentPageNumber', 'getItemNumberPerPage', 'getTotalItemCount'], 'KnpPaginator');
        if (null === $route && method_exists($pagination, 'getRoute')) {
            $knpRoute = $pagination->getRoute();
            $route = \is_string($knpRoute) && '' !== $knpRoute ? $knpRoute : null;
        }
        if ([] === $query && method_exists($pagination, 'getParams')) {
            $params = $pagination->getParams();
            $query = \is_array($params) ? $params : [];
            unset($query['page']);
        }

        /* @phpstan-ignore method.notFound, method.notFound, method.notFound */
        return new self((int) $pagination->getCurrentPageNumber(), (int) $pagination->getItemNumberPerPage(), (int) $pagination->getTotalItemCount(), null, $route, $query);
    }

    /**
     * 1-based index of the first item on this page (0 when empty).
     */
    public function from(): int
    {
        if (!$this->totalKnown) {
            return $this->offset() + 1;
        }

        return 0 === $this->total ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    /**
     * Index of the last item on this page.
     *
     * @param int|null $itemCount rows actually rendered (default: computed from total; a full page without a total)
     */
    public function to(?int $itemCount = null): int
    {
        if (!$this->totalKnown) {
            return $this->offset() + max(0, $itemCount ?? $this->perPage);
        }
        if (0 === $this->total) {
            return 0;
        }
        $count = $itemCount ?? max(0, min($this->perPage, $this->total - ($this->page - 1) * $this->perPage));

        return ($this->page - 1) * $this->perPage + $count;
    }

    /**
     * Zero-based index of the first row of this page, for the query (`OFFSET`, `setFirstResult()`).
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Rows per page, for the query (`LIMIT`, `setMaxResults()`).
     */
    public function limit(): int
    {
        return $this->perPage;
    }

    /**
     * Same pagination with another variant (2.6): `full` or `compact`.
     *
     * @return self Fluent interface
     */
    public function withVariant(string $variant): self
    {
        return new self($this->page, $this->perPage, $this->total, $this->totalPages, $this->route, $this->query, $this->routeParams, $this->pageParam, $this->perPageParam, $this->allowedPerPage, $variant, $this->mode, $this->modeNarrow, $this->modeWide, $this->breakpoint, $this->autoLoads, $this->nextQuery, $this->cursorParams, $this->totalKnown);
    }

    /**
     * Same pagination with another mode (2.23): `pager`, `infinite`, or `responsive` (`$narrow` below `$breakpoint`,
     * `$wide` above; each `pager` | `infinite`). Null arguments keep the current values.
     *
     * @return self Fluent interface
     */
    public function withMode(string $mode, ?string $narrow = null, ?string $wide = null, ?string $breakpoint = null, ?int $autoLoads = null): self
    {
        return new self($this->page, $this->perPage, $this->total, $this->totalPages, $this->route, $this->query, $this->routeParams, $this->pageParam, $this->perPageParam, $this->allowedPerPage, $this->variant, $mode, $narrow ?? $this->modeNarrow, $wide ?? $this->modeWide, $breakpoint ?? $this->breakpoint, $autoLoads ?? $this->autoLoads, $this->nextQuery, $this->cursorParams, $this->totalKnown);
    }

    /**
     * Same pagination with query parameters merged only into the next URL (2.77): the infinite peer's
     * `data-nowo-ui-pagination-next` and the pager's Next link — e.g. `['after' => $cursor]` for a keyset read. Every
     * other link (numbers, Previous, per page) is unchanged; the page parameter still moves to page + 1, so a reload
     * without the cursor falls back to the offset. `$cursorParams` are the parameters the peer strips from the address
     * bar after loading more (default: the keys of `$query`). Values are scalars cast to strings (null drops the key),
     * the URL is built and escaped server-side by the router; the kit does not read or trust the cursor.
     *
     * @param array<string, mixed> $query
     * @param list<string>|null    $cursorParams
     *
     * @return self Fluent interface
     */
    public function withNextQuery(array $query, ?array $cursorParams = null): self
    {
        return new self($this->page, $this->perPage, $this->total, $this->totalPages, $this->route, $this->query, $this->routeParams, $this->pageParam, $this->perPageParam, $this->allowedPerPage, $this->variant, $this->mode, $this->modeNarrow, $this->modeWide, $this->breakpoint, $this->autoLoads, $query, $cursorParams, $this->totalKnown);
    }

    /**
     * Same pagination without a row count (2.77, see {@see self::progressiveFetch()}): `$hasNext` decides whether the
     * next URL is printed.
     *
     * @return self Fluent interface
     */
    public function withoutTotal(bool $hasNext): self
    {
        return new self($this->page, $this->perPage, 0, $this->page + ($hasNext ? 1 : 0), $this->route, $this->query, $this->routeParams, $this->pageParam, $this->perPageParam, $this->allowedPerPage, $this->variant, $this->mode, $this->modeNarrow, $this->modeWide, $this->breakpoint, $this->autoLoads, $this->nextQuery, $this->cursorParams, false);
    }

    /**
     * Whether a mode other than `pager` is set (2.23): the partial then adds the `list-pagination` peer contract and
     * the list scaffold marks its rows (`data-nowo-ui-list-rows`).
     */
    public function isProgressive(): bool
    {
        return self::MODE_PAGER !== $this->mode;
    }

    /**
     * Whether the `compact` variant renders (2.6).
     */
    public function isCompact(): bool
    {
        return self::VARIANT_COMPACT === $this->variant;
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages;
    }

    /**
     * 1.x `_pagination` hash (`page`, `per_page`, `total`, `total_pages`, `route`, `query`, `route_params`, `page_param`, `per_page_param`).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'total_pages' => $this->totalPages,
            'route' => $this->route,
            'query' => $this->query,
            'route_params' => $this->routeParams,
            'page_param' => $this->pageParam,
            'per_page_param' => $this->perPageParam,
        ];
    }

    /**
     * @param array<mixed, mixed> $query
     *
     * @return array<string, string>
     */
    private function normaliseNextQuery(array $query, string $pageParam, string $perPageParam): array
    {
        $normalised = [];
        foreach ($query as $name => $value) {
            if (!\is_string($name) || '' === trim($name) || $name === $pageParam || $name === $perPageParam) {
                throw new InvalidArgumentException(\sprintf('Invalid pagination next-query parameter "%s": a non-empty name other than the page / per-page parameters.', (string) $name));
            }
            if (null === $value) {
                continue;
            }
            if (!\is_scalar($value)) {
                throw new InvalidArgumentException(\sprintf('Invalid pagination next-query value for "%s": scalars only (the cursor is an opaque string).', $name));
            }
            $value = \is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            if (\strlen($value) > self::MAX_NEXT_QUERY_VALUE_LENGTH) {
                throw new InvalidArgumentException(\sprintf('Pagination next-query value for "%s" is longer than %d bytes.', $name, self::MAX_NEXT_QUERY_VALUE_LENGTH));
            }
            $normalised[$name] = $value;
        }

        return $normalised;
    }

    /**
     * @param array<mixed, mixed> $params
     *
     * @return list<string>
     */
    private function normaliseCursorParams(array $params, string $pageParam, string $perPageParam): array
    {
        $normalised = [];
        foreach ($params as $param) {
            if (!\is_string($param) || 1 !== preg_match('/^[^\s&=#"<>]{1,100}$/', $param) || $param === $pageParam || $param === $perPageParam) {
                throw new InvalidArgumentException(\sprintf('Invalid pagination cursor parameter "%s": a query parameter name (no blanks, &, =, #, quotes or angle brackets) other than the page / per-page parameters.', \is_string($param) ? $param : get_debug_type($param)));
            }
            $normalised[] = $param;
        }

        return array_values(array_unique($normalised));
    }

    /**
     * @param list<string> $methods
     */
    private static function assertMethods(object $object, array $methods, string $library): void
    {
        foreach ($methods as $method) {
            if (!method_exists($object, $method)) {
                throw new InvalidArgumentException(\sprintf('%s does not look like a %s pager: method %s() is missing.', get_debug_type($object), $library, $method));
            }
        }
    }

    /**
     * 1.x `_pagination` hash key (`pagination['per_page']` / `pagination.per_page` in Twig): see {@see self::toArray()}.
     */
    public function offsetExists(mixed $offset): bool
    {
        return null !== $this->offsetGet($offset);
    }

    /**
     * Value of a 1.x key ({@see self::toArray()}), `offset` / `limit` (2.4, the `PagePagination` array keys for the
     * query), `variant` / `allowed_per_page` (2.6), `mode` / `mode_narrow` / `mode_wide` / `breakpoint` / `auto_loads`
     * (2.23), `next_query` / `cursor_params` / `total_known` / `has_next` (2.77), null when unknown.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'offset' => $this->offset(),
            'limit' => $this->limit(),
            'variant' => $this->variant,
            'allowed_per_page' => $this->allowedPerPage,
            'mode' => $this->mode,
            'mode_narrow' => $this->modeNarrow,
            'mode_wide' => $this->modeWide,
            'breakpoint' => $this->breakpoint,
            'auto_loads' => $this->autoLoads,
            'next_query' => $this->nextQuery,
            'cursor_params' => $this->cursorParams,
            'total_known' => $this->totalKnown,
            'has_next' => $this->hasNext(),
            default => \is_string($offset) ? ($this->toArray()[$offset] ?? null) : null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException(self::class.' is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException(self::class.' is immutable.');
    }
}
