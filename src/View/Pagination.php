<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

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
 * @implements \ArrayAccess<array-key, mixed>
 */
final readonly class Pagination implements \ArrayAccess
{
    /** Per-page values {@see self::fromTotal()} accepts by default. */
    public const ALLOWED_PER_PAGE = [10, 25, 50, 100];

    /** Per-page value {@see self::fromTotal()} falls back to (when allowed, else the first allowed value). */
    public const DEFAULT_PER_PAGE = 25;

    public int $page;

    public int $perPage;

    public int $total;

    public int $totalPages;

    /**
     * @param array<string, mixed>      $query          extra query parameters kept across pages (filters, sort)
     * @param array<string, mixed>|null $routeParams    route parameters (null: the current request's)
     * @param list<int>                 $allowedPerPage per-page choices (2.4; `_pagination` with `per_page_selector: true` links them)
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
    ) {
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
        $this->totalPages = max(1, $totalPages ?? (int) ceil($this->total / $this->perPage));
        $this->page = max(1, $page);
    }

    /**
     * Accepts the 1.x `_pagination` shape and the bundle variants: page|current_page|currentPage,
     * per_page|perPage|page_size|limit, total|total_items|totalItems|count, total_pages|totalPages|pages|last_page,
     * plus route, query, route_params.
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
    ): self {
        $allowed = array_values(array_unique(array_filter($allowedPerPage, static fn (int $value): bool => $value > 0)));
        if ([] === $allowed) {
            throw new \InvalidArgumentException('Pagination::fromTotal() needs at least one positive allowed per-page value.');
        }
        if (!\in_array($perPage, $allowed, true)) {
            $default = $defaultPerPage ?? self::DEFAULT_PER_PAGE;
            $perPage = \in_array($default, $allowed, true) ? $default : $allowed[0];
        }
        $total = max(0, $total);
        $totalPages = max(1, (int) ceil($total / $perPage));

        return new self(max(1, min($page, $totalPages)), $perPage, $total, $totalPages, $route, $query, $routeParams, $pageParam, $perPageParam, $allowed);
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
        return 0 === $this->total ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    /**
     * Index of the last item on this page.
     *
     * @param int|null $itemCount rows actually rendered (default: computed from total)
     */
    public function to(?int $itemCount = null): int
    {
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
     * @param list<string> $methods
     */
    private static function assertMethods(object $object, array $methods, string $library): void
    {
        foreach ($methods as $method) {
            if (!method_exists($object, $method)) {
                throw new \InvalidArgumentException(\sprintf('%s does not look like a %s pager: method %s() is missing.', get_debug_type($object), $library, $method));
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
     * query), null when unknown.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'offset' => $this->offset(),
            'limit' => $this->limit(),
            default => \is_string($offset) ? ($this->toArray()[$offset] ?? null) : null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException(self::class.' is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException(self::class.' is immutable.');
    }
}
