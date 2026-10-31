<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\TestCase;

/**
 * Minor slots that absorb the class maps of UI kits (final Beacon validation): btn_link, muted, modal_*,
 * breadcrumb_link / breadcrumb_separator, table_cell_muted, row_action_disabled, sortable_header / sort_link.
 */
final class MinorSlotsTest extends TestCase
{
    private const THEME = [
        'classes' => [
            'btn_link' => 'app-link-btn',
            'muted' => 'app-muted',
            'modal' => 'app-modal',
            'modal_panel' => 'app-modal-panel',
            'modal_header' => 'app-modal-header',
            'modal_body' => 'app-modal-body',
            'modal_footer' => 'app-modal-footer',
            'breadcrumb_link' => 'app-crumb-link',
            'breadcrumb_separator' => 'app-crumb-sep',
            'table_cell_muted' => 'app-cell-muted',
            'row_action_disabled' => 'app-disabled',
            'sortable_header' => 'app-sortable',
            'sort_link' => 'app-sort',
        ],
        'attributes' => [
            'modal' => ['data-modal' => 'x'],
            'modal_body' => ['data-body' => 'x'],
            'sort_link' => ['data-sort' => 'x'],
        ],
    ];

    public function testLinkButtonAndMutedPlaceholder(): void
    {
        $twig = PageContractTwig::create([], self::THEME);
        $html = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('copy', 'Duplicate', '/copy', variant: 'link')]);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--link btn btn-link app-link-btn nowo-ui-action--copy" href="/copy">', $html);

        // empty theme: same classes as the 1.x macro
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('copy', 'Duplicate', '/copy', variant: 'link')]);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--link btn btn-link nowo-ui-action--copy" href="/copy">', $html);

        $html = $twig->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage(new PageHeader('Order'), [new DetailSection('General', [new DetailItem('Notes', null), new DetailItem('Archived', false), new DetailItem('Site', '', '/site')])])]);
        self::assertSame(3, substr_count($html, '<span class="nowo-ui-muted text-muted app-muted">—</span>'));
        self::assertStringContainsString('<a href="/site"><span class="nowo-ui-muted text-muted app-muted">—</span></a>', $html);
    }

    public function testModalShellSlots(): void
    {
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKitBundle/partials/_modal_shell.html.twig', ['id' => 'm1', 'title' => 'Import', 'body' => '<p>b</p>', 'footer' => '<p>f</p>']);
        self::assertStringContainsString('class="nowo-ui-modal modal fade app-modal"', $html);
        self::assertStringContainsString('data-modal="x"', $html);
        self::assertStringContainsString('<div class="modal-content app-modal-panel">', $html);
        self::assertStringContainsString('<div class="modal-header app-modal-header">', $html);
        self::assertStringContainsString('<div class="modal-body app-modal-body" data-body="x">', $html);
        self::assertStringContainsString('<div class="modal-footer app-modal-footer"><p>f</p></div>', $html);

        $html = PageContractTwig::create([], ['bundles' => ['NowoXBundle' => ['classes' => ['modal_body' => 'x-body']]]])->render('@NowoUiKitBundle/partials/_modal_shell.html.twig', ['id' => 'm2', 'title' => 'T', 'bundle' => 'NowoXBundle']);
        self::assertStringContainsString('<div class="modal-body x-body">', $html);
        self::assertStringContainsString('<div class="modal-content">', $html);
    }

    public function testBreadcrumbSlots(): void
    {
        $header = new PageHeader('Order', breadcrumbs: [new Crumb('Home', '/'), new Crumb('Orders', '/orders'), new Crumb('Order')]);
        $html = PageContractTwig::create([], self::THEME, breadcrumbKit: false)->render('@NowoUiKitBundle/partials/_breadcrumbs.html.twig', ['header' => $header]);
        self::assertSame(2, substr_count($html, '<a class="nowo-ui-breadcrumbs__link app-crumb-link" href="'));
        self::assertSame(2, substr_count($html, '<span class="nowo-ui-breadcrumbs__separator app-crumb-sep" aria-hidden="true">/</span>'));
        self::assertMatchesRegularExpression('#<li class="nowo-ui-breadcrumbs__item">\s*<a #', $html, 'no separator before the first crumb');

        $html = PageContractTwig::create([], [], breadcrumbKit: false)->render('@NowoUiKitBundle/partials/_breadcrumbs.html.twig', ['header' => $header]);
        self::assertStringContainsString('<a class="nowo-ui-breadcrumbs__link" href="/">Home</a>', $html);
        self::assertSame(2, substr_count($html, '<span class="nowo-ui-breadcrumbs__separator" aria-hidden="true">/</span>'));
    }

    public function testListCellSortAndDisabledRowActionSlots(): void
    {
        $page = new ListPage(
            new PageHeader('Orders'),
            [new Column('name', 'Name', true), new Column('note', 'Note')],
            [['name' => 'Alpha', 'note' => null], ['name' => 'Beta', 'note' => 'ok'], ['name' => 'Gamma', 'note' => false], ['name' => 'Delta', 'note' => 0]],
            rowActions: [Action::edit('/e'), new Action('view', null, '/v', disabled: true), new Action('copy', null, null, modalId: 'm', disabled: true)],
            sort: 'name',
        );
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__head-cell--sortable app-sortable nowo-ui-cell--start" aria-sort="ascending">', $html);
        self::assertStringContainsString('<a class="nowo-ui-sort app-sort nowo-ui-sort--asc" href="', $html);
        self::assertStringContainsString('data-sort="x"><span class="nowo-ui-sort__label">Name</span>', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-cell--start">Note</th>', $html);

        self::assertSame(2, substr_count($html, '<td class="nowo-ui-table__cell nowo-ui-table__cell--muted text-muted app-cell-muted nowo-ui-cell--start" data-label="Note">—</td>'));
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-cell--start" data-label="Note">ok</td>', $html);
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-cell--start" data-label="Note">0</td>', $html);

        // 4 rows × 2 disabled actions; the enabled edit action never gets the slot
        self::assertSame(8, substr_count($html, 'nowo-ui-action--disabled app-disabled'));
        self::assertDoesNotMatchRegularExpression('/nowo-ui-action--edit[^"]*app-disabled/', $html);

        // 1.x hashes: host classes only
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [['kind' => 'view', 'href' => '/v', 'disabled' => true], ['kind' => 'edit', 'href' => '/e']]]);
        self::assertSame(1, substr_count($html, 'app-disabled'));
        self::assertStringNotContainsString('nowo-ui-action--disabled', $html);
    }
}
