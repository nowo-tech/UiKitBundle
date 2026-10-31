<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingFieldState;
use Nowo\UiKitBundle\Listing\ListingLayout;
use Nowo\UiKitBundle\Listing\SortRequest;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\Table;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.20 sortable column headers: server-side links (SortRequest), client-side buttons (Stimulus peer `table-sort`),
 * the `sort_header` partial, `listing_head` and the unchanged markup of tables without sort information.
 */
final class SortableHeadersTest extends TestCase
{
    /** Header cells of a table without sort information, exactly as UiKit 2.19 printed them. */
    private const BC_HEAD = '<th scope="col" class="nowo-ui-table__head-cell nowo-ui-cell--start">Name</th>';

    private const BC_CELL = '<td class="nowo-ui-table__cell nowo-ui-cell--start" data-label="Name">Alpha</td>';

    public function testServerSideListLinksKeepTheQueryAndGoBackToPageOne(): void
    {
        $request = new Request(['q' => 'x', 'p' => 3, 'per_page' => 50, 'sort' => 'created', 'dir' => 'sideways'], [], ['_route' => 'orders', '_route_params' => ['org' => 7]]);
        $sort = SortRequest::fromRequest($request, ['customer' => 'asc', 'created' => 'desc'], 'created');
        self::assertSame('desc', $sort->direction, 'an unknown direction falls back to the first-click one');

        $page = new ListPage(
            new PageHeader('Orders'),
            [new Column('customer', 'Customer', true), new Column('placed', 'Placed', sortKey: 'created', sortFirst: 'desc'), new Column('total', 'Total')],
            [['customer' => 'Ann', 'placed' => 'today', 'total' => 3]],
            pagination: new Pagination(3, 10, 42, pageParam: 'p'),
            sorting: $sort,
        );
        $html = PageContractTwig::create(request: $request)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertSame('created', $page->sort);
        self::assertSame('desc', $page->direction);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__head-cell--sortable nowo-ui-cell--start"><a class="nowo-ui-sort" href="/orders?org=7&amp;q=x&amp;per_page=50&amp;sort=customer&amp;dir=asc"><span class="nowo-ui-sort__label">Customer</span><span class="nowo-ui-sort__icon" aria-hidden="true"><i class="bi bi-arrow-down-up" aria-hidden="true"></i></span><span class="visually-hidden">Sort ascending</span></a></th>', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__head-cell--sortable nowo-ui-cell--start" aria-sort="descending"><a class="nowo-ui-sort nowo-ui-sort--desc" href="/orders?org=7&amp;q=x&amp;per_page=50&amp;sort=created&amp;dir=asc"><span class="nowo-ui-sort__label">Placed</span><span class="nowo-ui-sort__icon" aria-hidden="true"><i class="bi bi-arrow-down" aria-hidden="true"></i></span><span class="visually-hidden">Sort ascending</span></a></th>', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-cell--start">Total</th>', $html);
        self::assertStringNotContainsString('data-controller="table-sort"', $html);
        self::assertStringNotContainsString('data-sortable', $html);
    }

    public function testFirstClickDirectionAndTheTablePartialWithAnExplicitRoute(): void
    {
        $table = new Table(
            [new Column('name', 'Name', true), new Column('events', 'Events', align: 'end', sortKey: 'count', sortFirst: 'desc')],
            [['name' => 'A', 'events' => 2]],
            sorting: new SortRequest('name', 'desc', ['name' => 'asc'], 'order', 'way', null),
        );
        self::assertSame('asc', $table->nextDirection('name'));
        self::assertSame('desc', $table->nextDirection($table->columns[1]), 'the column first-click direction completes the whitelist');
        self::assertSame(['name' => 'asc', 'count' => 'desc'], $table->sorting->fields);
        self::assertTrue($table->isSortedBy($table->columns[0]));
        self::assertSame('descending', $table->ariaSort($table->columns[0]));
        self::assertNull($table->ariaSort($table->columns[1]));

        $request = new Request(['page' => 2], [], ['_route' => 'here', '_route_params' => []]);
        $html = PageContractTwig::create(request: $request)->render('@NowoUiKit/partials/_table.html.twig', ['table' => $table, 'route' => 'events']);
        self::assertStringContainsString('href="/events?page=2&amp;order=name&amp;way=asc"', $html, 'pageParam null keeps the page');
        self::assertStringContainsString('href="/events?page=2&amp;order=count&amp;way=desc"><span class="nowo-ui-sort__label">Events</span>', $html);
        self::assertStringContainsString('<span class="visually-hidden">Sort descending</span>', $html);
    }

    public function testClientSideTableRendersButtonsAndSortValues(): void
    {
        $table = new Table(
            [
                new Column('name', 'Name', true),
                new Column('size', 'Size', true, 'end', sortFirst: 'desc', sortType: 'number', sortValueKey: 'bytes'),
                new Column('note', 'Note'),
            ],
            [['name' => 'A', 'size' => '2 kB', 'bytes' => 2048, 'note' => 'x'], (object) ['name' => 'B', 'size' => '1 kB', 'bytes' => 1024, 'note' => null]],
            sort: 'name',
            attributes: ['data-controller' => 'other', 'data-testid' => 'files'],
            clientSort: true,
        );
        $html = PageContractTwig::create()->render('@NowoUiKit/partials/_table.html.twig', ['table' => $table]);

        self::assertStringContainsString(' data-controller="other table-sort" data-testid="files"', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__head-cell--sortable nowo-ui-cell--start" aria-sort="ascending" data-sortable><button type="button" class="nowo-ui-sort nowo-ui-sort--button" data-action="table-sort#sort"><span class="nowo-ui-sort__label">Name</span><span class="nowo-ui-sort__icon nowo-ui-sort__icon--none" aria-hidden="true"><i class="bi bi-arrow-down-up" aria-hidden="true"></i></span><span class="nowo-ui-sort__icon nowo-ui-sort__icon--asc" aria-hidden="true"><i class="bi bi-arrow-up" aria-hidden="true"></i></span><span class="nowo-ui-sort__icon nowo-ui-sort__icon--desc" aria-hidden="true"><i class="bi bi-arrow-down" aria-hidden="true"></i></span><span class="visually-hidden nowo-ui-sort__hint nowo-ui-sort__hint--asc">Sort ascending</span><span class="visually-hidden nowo-ui-sort__hint nowo-ui-sort__hint--desc">Sort descending</span></button></th>', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__head-cell--sortable nowo-ui-cell--end" data-sortable data-sort-type="number" data-sort-first="desc">', $html);
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-cell--end" data-sort-value="2048" data-label="Size">2 kB</td>', $html);
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-cell--end" data-sort-value="1024" data-label="Size">1 kB</td>', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-cell--start">Note</th>', $html);
        self::assertStringNotContainsString('href="/', $html);

        $plain = PageContractTwig::create()->render('@NowoUiKit/partials/_table.html.twig', ['table' => new Table([new Column('name', 'Name', true)], [['name' => 'A']], clientSort: true)]);
        self::assertStringContainsString('nowo-ui-table--cards" data-controller="table-sort"', $plain);
    }

    public function testClientSortListPageAndThePaginationGuard(): void
    {
        $page = new ListPage(new PageHeader('Files'), [new Column('name', 'Name', true)], [['name' => 'A']], pagination: new Pagination(1, 10, 5), clientSort: true);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertStringContainsString('data-controller="table-sort"', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__head-cell--sortable nowo-ui-cell--start" data-sortable><button type="button"', $html);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A paginated ListPage cannot sort in the browser');
        new ListPage(new PageHeader('Files'), [new Column('name', 'Name', true)], pagination: new Pagination(1, 10, 42), clientSort: true);
    }

    public function testTablesWithoutSortInformationKeepTheirMarkup(): void
    {
        $twig = PageContractTwig::create();
        $table = $twig->render('@NowoUiKit/partials/_table.html.twig', ['table' => new Table([new Column('name', 'Name')], [['name' => 'Alpha']])]);
        $list = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Items'), [new Column('name', 'Name')], [['name' => 'Alpha']])]);

        foreach ([$table, $list] as $html) {
            self::assertStringContainsString(self::BC_HEAD, $html);
            self::assertStringContainsString(self::BC_CELL, $html);
            foreach (['nowo-ui-sort', 'aria-sort', 'data-sortable', 'data-sort-', 'table-sort', 'visually-hidden">Sort'] as $absent) {
                self::assertStringNotContainsString($absent, $html);
            }
        }
        // a sortable column on a page without a route (no request) stays a plain header
        $noRoute = PageContractTwig::create(request: new Request())->render('@NowoUiKit/partials/_table.html.twig', ['table' => new Table([new Column('name', 'Name', true)], [['name' => 'Alpha']])]);
        self::assertStringContainsString(self::BC_HEAD, $noRoute);
    }

    public function testSortHeaderPartialForHostTables(): void
    {
        self::assertSame('@NowoUiKitBundle/partials/_sort_header.html.twig', ThemeSlots::PARTIALS['sort_header']);
        $twig = PageContractTwig::create(['host.html.twig' => "{{ include(nowo_ui_partial('sort_header'), vars, false) }}"], ['classes' => ['sort_link' => 'app-sort', 'sort_button' => 'app-sort-btn']], locale: 'es');

        $link = $twig->render('host.html.twig', ['vars' => ['label' => 'Nombre', 'href' => '/x?sort=name', 'active' => true, 'direction' => 'asc', 'attr' => ['data-testid' => 'sort-name']]]);
        self::assertSame('<a class="nowo-ui-sort app-sort nowo-ui-sort--asc" href="/x?sort=name" data-testid="sort-name"><span class="nowo-ui-sort__label">Nombre</span><span class="nowo-ui-sort__icon" aria-hidden="true"><i class="bi bi-arrow-up" aria-hidden="true"></i></span><span class="visually-hidden">Ordenar de forma descendente</span></a>', $link);

        $idle = $twig->render('host.html.twig', ['vars' => ['label' => 'Nombre', 'href' => '/x', 'icons' => ['none' => 'filter'], 'labels' => ['asc' => 'A → Z']]]);
        self::assertStringContainsString('<i class="bi bi-funnel" aria-hidden="true"></i>', $idle);
        self::assertStringContainsString('<span class="visually-hidden">A → Z</span>', $idle);

        $button = $twig->render('host.html.twig', ['vars' => ['label' => 'Nombre']]);
        self::assertStringStartsWith('<button type="button" class="nowo-ui-sort nowo-ui-sort--button app-sort-btn" data-action="table-sort#sort"><span class="nowo-ui-sort__label">Nombre</span>', $button);
        self::assertStringEndsWith('<span class="visually-hidden nowo-ui-sort__hint nowo-ui-sort__hint--desc">Ordenar de forma descendente</span></button>', $button);
    }

    public function testSortIconsInEveryIconSet(): void
    {
        $sets = ['tabler-icons' => '<i class="ti ti-arrows-sort" aria-hidden="true"></i>', 'svg_inline' => 'M16 17.01V10h-2v7.01h-3L15 21l4-3.99h-3z', 'none' => '<span class="nowo-ui-icon nowo-ui-icon--text">↕</span>'];
        foreach ($sets as $set => $expected) {
            $twig = PageContractTwig::create(['host.html.twig' => "{{ include(nowo_ui_partial('sort_header'), {label: 'N', href: '/x'}, false) }}"]);
            $twig->addGlobal('nowo_ui_kit_icon_set', $set);
            self::assertStringContainsString($expected, $twig->render('host.html.twig'), $set);
        }
    }

    public function testListingHeadServerAndClientModes(): void
    {
        $layout = new ListingLayout('files', [
            new ListingFieldState(new ListingField('name', 'Name', sortKey: 'name'), true),
            new ListingFieldState(new ListingField('size', 'Size', sortKey: 'bytes', sortFirst: 'desc'), true),
            new ListingFieldState(new ListingField('owner', 'Owner', sortKey: 'owner'), true, true),
            new ListingFieldState(new ListingField('notes', 'Notes'), true),
        ], []);
        $request = new Request(['q' => 'a', 'page' => 4], [], ['_route' => 'files', '_route_params' => []]);
        $sort = SortRequest::fromRequest($request, SortRequest::fieldsOf([new ListingField('name', 'Name', sortKey: 'name'), new ListingField('size', 'Size', sortKey: 'bytes', sortFirst: 'desc'), new ListingField('owner', 'Owner', sortKey: 'owner')]), 'owner');
        $twig = PageContractTwig::create(['head.html.twig' => "{{ include('@NowoUiKit/partials/_listing_head.html.twig', vars, false) }}"], request: $request);

        $server = $twig->render('head.html.twig', ['vars' => ['listing_layout' => $layout, 'sorting' => $sort, 'detail' => false]]);
        self::assertStringContainsString('<th class="nowo-ui-table__head-cell nowo-ui-table__head-cell--sortable" scope="col" data-column="name" data-columns="name" data-priority="0"><a class="nowo-ui-sort" href="/files?q=a&amp;sort=name&amp;dir=asc">', $server);
        self::assertStringContainsString('href="/files?q=a&amp;sort=bytes&amp;dir=desc"', $server);
        self::assertStringContainsString('data-columns="size owner" data-priority="0" aria-sort="ascending">', $server, 'a stacked header is sorted when one of its columns is');
        self::assertStringContainsString('<span class="nowo-ui-listing-cell__part" data-cell-part><a class="nowo-ui-sort nowo-ui-sort--asc" href="/files?q=a&amp;sort=owner&amp;dir=desc">', $server);
        self::assertStringContainsString('<th class="nowo-ui-table__head-cell" scope="col" data-column="notes" data-columns="notes" data-priority="0">Notes</th>', $server);

        $client = $twig->render('head.html.twig', ['vars' => ['listing_layout' => $layout, 'client_sort' => true, 'detail' => false]]);
        self::assertStringContainsString('data-column="name" data-columns="name" data-priority="0" data-sortable><button type="button" class="nowo-ui-sort nowo-ui-sort--button" data-action="table-sort#sort">', $client);
        self::assertStringContainsString('data-columns="size owner" data-priority="0">', $client, 'stacked headers do not sort in the browser');
        self::assertStringNotContainsString('href=', $client);

        $plain = $twig->render('head.html.twig', ['vars' => ['listing_layout' => $layout, 'detail' => false]]);
        self::assertStringNotContainsString('nowo-ui-sort', $plain);
        self::assertStringContainsString('<th class="nowo-ui-table__head-cell" scope="col" data-column="name" data-columns="name" data-priority="0">Name</th>', $plain);
    }
}
