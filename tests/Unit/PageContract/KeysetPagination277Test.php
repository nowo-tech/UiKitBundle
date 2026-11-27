<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.77: keyset cursors for the infinite peer — `next_query` reaches only the next URL (peer attribute + Next link),
 * `cursor_params` is printed for the peer, and a pagination without a total prints no "of N".
 */
final class KeysetPagination277Test extends TestCase
{
    private const PARTIAL = '@NowoUiKitBundle/partials/_pagination.html.twig';

    public function testNextQueryReachesOnlyTheNextUrlAndTheNextLink(): void
    {
        $pager = Pagination::fromTotal(95, 3, 10, route: 'orders', query: ['q' => 'x'])
            ->withMode(Pagination::MODE_INFINITE)
            ->withNextQuery(['after' => 'eyJpZCI6NDJ9&x="<>']);
        $html = $this->twig()->render(self::PARTIAL, ['pagination' => $pager]);

        $next = htmlspecialchars('/orders?'.http_build_query(['q' => 'x', 'per_page' => 10, 'page' => 4, 'after' => 'eyJpZCI6NDJ9&x="<>']));
        self::assertStringContainsString('data-nowo-ui-pagination-next="'.$next.'"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-error-text="Could not load more rows. Try again." data-nowo-ui-pagination-cursor-params="after">', $html);
        self::assertStringContainsString('href="'.$next.'" aria-label="Next"', $html);
        // the cursor is escaped by the router: never a raw quote or angle bracket in the markup
        self::assertStringNotContainsString('x="<>', $html);
        // numbers, Previous and the info line are unchanged
        self::assertSame(2, substr_count($html, 'after='));
        self::assertStringContainsString('href="'.htmlspecialchars('/orders?q=x&per_page=10&page=2').'" aria-label="Previous"', $html);
        self::assertStringContainsString('Showing 21–30 of 95', $html);
    }

    public function testPartialVariablesAndExplicitCursorParams(): void
    {
        $twig = $this->twig();
        $pager = Pagination::fromTotal(95, 1, 10, route: 'orders')->withMode(Pagination::MODE_INFINITE);
        $html = $twig->render(self::PARTIAL, ['pagination' => $pager, 'next_query' => ['after' => 'c1', 'dir' => 'desc']]);
        self::assertStringContainsString('data-nowo-ui-pagination-next="/orders?per_page=10&amp;page=2&amp;after=c1&amp;dir=desc"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-cursor-params="after dir"', $html);

        $html = $twig->render(self::PARTIAL, ['pagination' => $pager, 'next_query' => ['after' => 'c1', 'dir' => 'desc'], 'cursor_params' => ['after']]);
        self::assertStringContainsString('data-nowo-ui-pagination-cursor-params="after"', $html);

        $html = $twig->render(self::PARTIAL, ['pagination' => $pager->withNextQuery(['after' => 'c1'], ['after', 'since'])]);
        self::assertStringContainsString('data-nowo-ui-pagination-cursor-params="after since"', $html);

        // 1.x array input with a next_query variable
        $html = $twig->render(self::PARTIAL, ['pagination' => ['page' => 1, 'per_page' => 2, 'total' => 9, 'total_pages' => 5], 'item_count' => 2, 'route' => 'monitors', 'pagination_mode' => 'infinite', 'next_query' => ['after' => 'z']]);
        self::assertStringContainsString('data-nowo-ui-pagination-next="/monitors?per_page=2&amp;page=2&amp;after=z"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-cursor-params="after"', $html);

        // pager mode: only the Next link carries the cursor, no peer contract
        $html = $twig->render(self::PARTIAL, ['pagination' => Pagination::fromTotal(95, 1, 10, route: 'orders')->withNextQuery(['after' => 'c1'])]);
        self::assertStringNotContainsString('list-pagination', $html);
        self::assertStringContainsString('href="/orders?per_page=10&amp;page=2&amp;after=c1" aria-label="Next"', $html);
        self::assertStringContainsString('href="/orders?per_page=10&amp;page=2">2</a>', $html);
    }

    public function testLastPageHasNoNextUrl(): void
    {
        $pager = Pagination::fromTotal(95, 10, 10, route: 'orders')->withMode(Pagination::MODE_INFINITE)->withNextQuery(['after' => 'c']);
        $html = $this->twig()->render(self::PARTIAL, ['pagination' => $pager]);
        self::assertStringNotContainsString('data-nowo-ui-pagination-next', $html);
        self::assertStringNotContainsString('after=', $html);
    }

    public function testWithoutTotalPrintsNoCountAndKeepsTheNextUrlWhileANextPageExists(): void
    {
        $twig = $this->twig();
        $pager = Pagination::progressiveFetch(4, 10, true, 'orders', ['q' => 'x'])->withNextQuery(['after' => 'c4']);
        $html = $twig->render(self::PARTIAL, ['pagination' => $pager, 'item_count' => 10]);
        self::assertStringContainsString('Showing 31–40', $html);
        self::assertStringNotContainsString(' of ', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-next="/orders?q=x&amp;per_page=10&amp;page=5&amp;after=c4"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-more-region', $html);
        // Previous / Next only: no page numbers without a total
        self::assertStringNotContainsString('aria-current="page"', $html);
        self::assertStringContainsString('aria-label="Previous"', $html);
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=10&amp;page=5&amp;after=c4" aria-label="Next"', $html);

        // last page without a total: no next URL, Next disabled
        $html = $twig->render(self::PARTIAL, ['pagination' => Pagination::progressiveFetch(5, 10, false, 'orders'), 'item_count' => 3]);
        self::assertStringContainsString('Showing 41–43', $html);
        self::assertStringNotContainsString('data-nowo-ui-pagination-next', $html);
        self::assertStringContainsString('aria-disabled="true" aria-label="Next"', $html);

        // an empty follow-up page: no info line at all, still the peer root
        $html = $twig->render(self::PARTIAL, ['pagination' => Pagination::progressiveFetch(1, 10, false, 'orders'), 'item_count' => 0]);
        self::assertStringNotContainsString('nowo-ui-pagination__info', $html);
        self::assertStringNotContainsString('<nav', $html);
        self::assertStringContainsString('data-controller="list-pagination"', $html);

        // a per-page selector needs the total
        $html = $twig->render(self::PARTIAL, ['pagination' => Pagination::fromTotal(95, 2, 10, route: 'orders')->withoutTotal(true), 'per_page_selector' => true]);
        self::assertStringNotContainsString('data-nowo-ui-region="per-page"', $html);
        self::assertStringContainsString('Showing 11–20', $html);
    }

    public function testCompactVariantWithKeysetAndWithoutTotal(): void
    {
        $twig = $this->twig();
        $pager = Pagination::fromTotal(95, 2, 10, [10, 25], route: 'orders', variant: Pagination::VARIANT_COMPACT)
            ->withMode(Pagination::MODE_INFINITE)
            ->withNextQuery(['after' => 'k2']);
        $html = $twig->render(self::PARTIAL, ['pagination' => $pager]);
        self::assertStringContainsString('data-nowo-ui-pagination-next="/orders?per_page=10&amp;page=3&amp;after=k2"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-cursor-params="after"', $html);
        self::assertStringContainsString('href="/orders?per_page=10&amp;page=3&amp;after=k2" rel="next"', $html);
        self::assertStringContainsString('href="/orders?per_page=10&amp;page=1" rel="prev"', $html);
        self::assertStringContainsString('Page 2 of 10', $html);

        $html = $twig->render(self::PARTIAL, ['pagination' => $pager->withoutTotal(true)]);
        self::assertStringNotContainsString('Page 2', $html);
        self::assertStringNotContainsString('<select', $html);
        // per_page travels because the request chose one (2.6.1)
        self::assertStringContainsString('data-nowo-ui-pagination-next="/orders?per_page=10&amp;page=3&amp;after=k2"', $html);

        $html = $twig->render('@NowoUiKitBundle/partials/_pagination_compact.html.twig', ['pagination' => Pagination::fromTotal(95, 1, 10, route: 'orders')->withMode('infinite'), 'next_query' => ['after' => 'v']]);
        self::assertStringContainsString('data-nowo-ui-pagination-next="/orders?per_page=10&amp;page=2&amp;after=v"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-cursor-params="after"', $html);

        // page 1 without a next page and without a total: nothing to page through
        self::assertSame('', trim($twig->render(self::PARTIAL, ['pagination' => Pagination::progressiveFetch(1, 10, false, 'orders')->withVariant(Pagination::VARIANT_COMPACT)])));
    }

    public function testListScaffoldPassesTheKeysetModel(): void
    {
        $pager = Pagination::fromTotal(9, 2, 2, [2, 10])->withMode(Pagination::MODE_INFINITE)->withNextQuery(['after' => 'r2']);
        $page = new ListPage(new PageHeader('Monitors'), [new Column('name')], [['name' => 'a'], ['name' => 'b']], pagination: $pager);
        $html = $this->twig()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertStringContainsString('data-nowo-ui-pagination-next="'.htmlspecialchars('/monitors?status=up&per_page=2&page=3&after=r2').'"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-cursor-params="after"', $html);

        $page = new ListPage(new PageHeader('Monitors'), [new Column('name')], [['name' => 'c'], ['name' => 'd']], pagination: Pagination::progressiveFetch(3, 2, true)->withNextQuery(['after' => 'r3']));
        $html = $this->twig()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertStringContainsString('Showing 5–6', $html);
        self::assertStringNotContainsString('of 9', $html);
        self::assertStringContainsString('page=4&amp;after=r3', $html);
    }

    public function testTranslatedRangeLine(): void
    {
        $request = Request::create('https://app.example/orders', 'GET');
        $request->attributes->set('_route', 'orders');
        $request->attributes->set('_route_params', []);
        $html = PageContractTwig::create(request: $request, locale: 'es')->render(self::PARTIAL, ['pagination' => Pagination::progressiveFetch(2, 10, true), 'item_count' => 10]);
        self::assertStringContainsString('Mostrando 11–20', $html);
        self::assertStringNotContainsString('Mostrando 11–20 de', $html);
    }

    public function testAFullPageNextNeverLeavesAStaleCursorOnTheOtherLinks(): void
    {
        // the no-JS Next link carried the cursor: ?page=2&after=X; fromRequest() keeps every other query parameter
        $request = Request::create('https://app.example/orders?q=x&page=2&per_page=10&after=X', 'GET');
        $request->attributes->set('_route', 'orders');
        $request->attributes->set('_route_params', []);
        $twig = PageContractTwig::create(request: $request, breadcrumbKit: false);
        $pager = Pagination::fromRequest($request, 95, 10)->withMode(Pagination::MODE_INFINITE)->withNextQuery(['after' => 'Y']);
        self::assertSame(['q' => 'x', 'after' => 'X'], $pager->query);

        $html = $twig->render(self::PARTIAL, ['pagination' => $pager, 'per_page_selector' => true]);
        self::assertStringNotContainsString('after=X', $html);
        self::assertSame(2, substr_count($html, 'after=Y'));
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=10&amp;page=1" aria-label="Previous"', $html);
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=10&amp;page=4">4</a>', $html);
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=25&amp;page=1">25</a>', $html);
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=10&amp;page=3&amp;after=Y" aria-label="Next"', $html);

        // last page: no next query, the cursor name still declared, so no link keeps it
        $lastRequest = Request::create('https://app.example/orders?q=x&page=10&per_page=10&after=X', 'GET');
        $last = Pagination::fromRequest($lastRequest, 95, 10)->withNextQuery([], ['after']);
        $html = $twig->render(self::PARTIAL, ['pagination' => $last]);
        self::assertStringNotContainsString('after=', $html);
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=10&amp;page=9" aria-label="Previous"', $html);

        // compact: Previous and the per-page form lose the cursor, Next gets the fresh one
        $html = $twig->render(self::PARTIAL, ['pagination' => Pagination::fromRequest($request, 95, 10, variant: Pagination::VARIANT_COMPACT)->withNextQuery(['after' => 'Y'])]);
        self::assertStringNotContainsString('after=X', $html);
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=10&amp;page=1" rel="prev"', $html);
        self::assertStringContainsString('href="/orders?q=x&amp;per_page=10&amp;page=3&amp;after=Y" rel="next"', $html);
        self::assertStringContainsString('name="q" value="x"', $html);
        self::assertStringNotContainsString('name="after"', $html);

        // 1.x variables on a direct include of the compact partial
        $html = $twig->render('@NowoUiKitBundle/partials/_pagination_compact.html.twig', ['pagination' => Pagination::fromRequest($request, 95, 10), 'next_query' => ['after' => 'Y']]);
        self::assertStringNotContainsString('after=X', $html);
        self::assertStringContainsString('page=3&amp;after=Y" rel="next"', $html);

        // list scaffold: its 1.x top-level query (the request query) is cleaned too
        $page = new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'a']], pagination: Pagination::fromRequest($request, 95, 10)->withNextQuery(['after' => 'Y']));
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertStringNotContainsString('after=X', $html);
        self::assertStringContainsString('page=3&amp;after=Y" aria-label="Next"', $html);
    }

    private function twig(): Environment
    {
        $request = Request::create('https://app.example/monitors?status=up&page=2&per_page=2', 'GET');
        $request->attributes->set('_route', 'monitors');
        $request->attributes->set('_route_params', []);

        return PageContractTwig::create(request: $request, breadcrumbKit: false);
    }
}
