<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * The only pagination shape of the page contract (REQ-UI-003). `route` null = current request route.
 * Factories normalise the shapes found in bundles (`fromArray`), Pagerfanta (`fromPagerfanta`) and
 * KnpPaginator (`fromKnp`) by duck typing — neither library is a dependency.
 */
final readonly class Pagination
{
    public int $page;

    public int $perPage;

    public int $total;

    public int $totalPages;

    /**
     * @param array<string, mixed>      $query       extra query parameters kept across pages (filters, sort)
     * @param array<string, mixed>|null $routeParams route parameters (null: the current request's)
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
}
