<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;

/**
 * `theme.pages.<type>` (REQ-UI-003): scaffolds pass their page type to slot resolution, down to the partials they
 * include and the data blocks bundles override.
 */
final class PageTypeThemeRenderTest extends TestCase
{
    private const THEME = [
        'classes' => ['table' => 'host-table', 'page_header' => 'host-header', 'btn_primary' => 'host-btn', 'pagination' => 'host-pager', 'empty' => 'host-empty', 'card' => 'host-card'],
        'attributes' => ['table' => ['data-host' => '1']],
        'pages' => [
            'list' => [
                'classes' => ['table' => 'list-table', 'page_header' => 'list-header', 'pagination' => 'list-pager', 'empty' => 'list-empty', 'table_cell' => 'list-cell'],
                'attributes' => ['table' => ['data-page' => 'list']],
            ],
            'dashboard' => ['framework_defaults' => false, 'classes' => ['page_header' => 'dash-header']],
        ],
        'bundles' => ['NowoXBundle' => ['classes' => ['table' => 'x-table']]],
    ];

    public function testListScaffoldResolvesSlotsWithItsPageType(): void
    {
        $page = new ListPage(new PageHeader('Items', actions: [Action::create('/new')]), [new Column('name')], [['name' => 'A']], pagination: new Pagination(1, 1, 3));
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<table class="nowo-ui-table nowo-ui-table--striped table table-striped table-hover align-middle list-table nowo-ui-table--cards" data-host="1" data-page="list">', $html);
        self::assertStringContainsString('nowo-ui-page-header d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3 list-header', $html);
        self::assertStringContainsString('nowo-ui-pagination d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 list-pager', $html);
        self::assertStringContainsString('nowo-ui-table__cell list-cell', $html);
        // slots without a page-type entry keep the host classes
        self::assertStringContainsString('btn btn-primary host-btn', $html);

        $empty = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Items'), empty: new EmptyState(action: Action::create('/new')))]);
        self::assertStringContainsString('nowo-ui-empty nowo-ui-muted text-muted list-empty', $empty);
        self::assertStringContainsString('btn btn-primary host-btn', $empty);
    }

    public function testBundleExceptionBeatsThePageType(): void
    {
        $page = new ListPage(new PageHeader('Items'), [new Column('name')], [['name' => 'A']], bundle: 'NowoXBundle');
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('table table-striped table-hover align-middle x-table', $html);
    }

    public function testDataBlocksInheritThePageType(): void
    {
        $child = <<<'TWIG'
            {% extends '@NowoUiKit/page/list.html.twig' %}
            {% block nowo_ui_list_row %}<td class="{{ nowo_ui_class('table_cell') }}">{{ row.name }}</td>{% endblock %}
            TWIG;
        $html = PageContractTwig::create(['child' => $child], self::THEME)->render('child', ['page' => new ListPage(new PageHeader('Items'), [new Column('name')], [['name' => 'A']])]);

        self::assertStringContainsString('<td class="nowo-ui-table__cell list-cell">A</td>', $html);
    }

    public function testPageTypeFrameworkDefaultsOnDashboard(): void
    {
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/dashboard.html.twig', ['page' => new DashboardPage(new PageHeader('Overview'))]);

        self::assertStringContainsString('class="nowo-ui-page-header dash-header"', $html);
    }
}
