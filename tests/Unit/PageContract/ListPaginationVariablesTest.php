<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The list scaffold passes the 1.x top-level pagination variables (`route`, `route_params`, `query`) next to the
 * array, so an untouched 1.x `_pagination` override keeps filters and sort across pages (REQ-UI-003 round 3).
 */
final class ListPaginationVariablesTest extends TestCase
{
    public function testUntouched1xOverrideKeepsTheFilters(): void
    {
        $legacy = (string) file_get_contents(PageContractTwig::root().'/tests/Fixtures/legacy-1.17/_pagination.html.twig');
        $request = new Request(['filters' => ['q' => 'abc'], 'sort' => 'name', 'page' => '2', 'per_page' => '10'], [], ['_route' => 'orders_index', '_route_params' => ['org' => 'acme']]);
        $twig = PageContractTwig::create(['host/_pagination.html.twig' => $legacy], ['partials' => ['pagination' => 'host/_pagination.html.twig']], request: $request);
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page(new Pagination(2, 10, 35))]);

        $next = '/orders_index?'.http_build_query(['org' => 'acme', 'filters' => ['q' => 'abc'], 'sort' => 'name', 'per_page' => 10, 'page' => 3]);
        self::assertStringContainsString('href="'.htmlspecialchars($next).'"', $html);
        self::assertStringNotContainsString('data-nowo-ui-region="pagination"', $html);

        // the default partial builds the same links
        $html = PageContractTwig::create(request: $request)->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page(new Pagination(2, 10, 35))]);
        self::assertStringContainsString('href="'.htmlspecialchars($next).'"', $html);
    }

    public function testPaginationRouteQueryAndParameterNames(): void
    {
        $legacy = (string) file_get_contents(PageContractTwig::root().'/tests/Fixtures/legacy-1.17/_pagination.html.twig');
        $request = new Request(['status' => 'open', 'p' => '1'], [], ['_route' => 'ignored', '_route_params' => ['org' => 'x']]);
        $pager = new Pagination(1, 10, 35, route: 'orders', query: ['status' => 'closed', 'tab' => 'all'], routeParams: ['org' => 'acme'], pageParam: 'p', perPageParam: 'n');

        $html = PageContractTwig::create(request: $request)->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page($pager)]);
        self::assertStringContainsString('href="'.htmlspecialchars('/orders?'.http_build_query(['org' => 'acme', 'status' => 'closed', 'tab' => 'all', 'n' => 10, 'p' => 2])).'"', $html);

        // a 1.x override gets route / route_params / query (it knows no page_param: it writes page / per_page)
        $twig = PageContractTwig::create(['host/_pagination.html.twig' => $legacy], ['partials' => ['pagination' => 'host/_pagination.html.twig']], request: $request);
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page($pager)]);
        self::assertStringContainsString('href="'.htmlspecialchars('/orders?'.http_build_query(['org' => 'acme', 'status' => 'closed', 'tab' => 'all', 'per_page' => 10, 'page' => 2])).'"', $html);
    }

    private function page(Pagination $pagination): ListPage
    {
        return new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], pagination: $pagination);
    }
}
