<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;

/**
 * Row-actions column alignment and slot (`ListPage::actionsAlign`, `table_actions_cell`) and the neutral variant in
 * toolbar context (REQ-UI-003 round 3).
 */
final class ListActionsColumnTest extends TestCase
{
    public function testActionsAlignmentAndCellSlot(): void
    {
        $page = new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], rowActions: [Action::view('/v')], actionsAlign: 'start');
        $html = PageContractTwig::create([], ['classes' => ['table_actions_cell' => 'app-actions-cell'], 'attributes' => ['table_actions_cell' => ['data-col' => 'actions']]])->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__actions nowo-ui-cell--start">', $html);
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-table__actions app-actions-cell nowo-ui-cell--start" data-col="actions">', $html);

        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], rowActions: [Action::view('/v')])]);
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-table__actions nowo-ui-cell--end">', $html);

        $this->expectExceptionMessage('Invalid ListPage actionsAlign "right" (start|center|end).');
        new ListPage(new PageHeader('x'), actionsAlign: 'right');
    }

    public function testNeutralToolbarActionsPaintAsSecondary(): void
    {
        $page = new ListPage(new PageHeader('Orders', actions: [Action::view('/v', 'Open'), new Action('export', 'CSV', '/csv', variant: 'neutral')]));
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--view" href="/v">', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--export" href="/csv">', $html);
    }
}
