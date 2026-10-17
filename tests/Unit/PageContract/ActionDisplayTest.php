<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormView;

/**
 * Action display modes in toolbar / header / filter context (REQ-UI-003 round 3): `Action::display`, the
 * `display` context option, `ListPage::filtersDisplay` and the `btn_icon` slot of icon-only buttons.
 */
final class ActionDisplayTest extends TestCase
{
    public function testHeaderActionsHonourDisplay(): void
    {
        $page = new ListPage(new PageHeader('Orders', actions: [
            new Action('create', 'New order', '/new', icon: 'create', variant: 'primary', display: 'icon'),
            new Action('export', 'Export', '/export', icon: 'export', display: 'text'),
            new Action('import', 'Import', '/import', display: 'icon_text'),
            new Action('config', 'Settings', '/settings', icon: 'config'),
            new Action('copy', 'Copy', '/copy', variant: 'link', display: 'icon'),
        ]));
        $html = PageContractTwig::create([], ['classes' => ['btn_icon' => 'app-icon-btn']])->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertMatchesRegularExpression('#<a class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-ui-action--create nowo-ui-btn--icon app-icon-btn" href="/new" title="New order" aria-label="New order">\s*<i [^>]*></i>\s*<span class="visually-hidden">New order</span>\s*</a>#', $html);
        self::assertMatchesRegularExpression('#<a class="[^"]*nowo-ui-action--export" href="/export"><span class="nowo-ui-action__label">Export</span></a>#', $html);
        self::assertMatchesRegularExpression('#nowo-ui-action--import" href="/import">\s*<i [^>]*></i>\s*<span class="nowo-ui-action__label">Import</span></a>#', $html);
        // no display: icon + text when an icon is set (1.x / round 2 output)
        self::assertMatchesRegularExpression('#nowo-ui-action--config" href="/settings">\s*<i [^>]*></i>\s*<span class="nowo-ui-action__label">Settings</span></a>#', $html);
        self::assertMatchesRegularExpression('#<a class="[^"]*nowo-ui-action--copy nowo-ui-btn--icon app-icon-btn" href="/copy" title="Copy" aria-label="Copy">#', $html);
    }

    public function testFiltersDisplayAndRowOverride(): void
    {
        $page = new ListPage(
            new PageHeader('Orders'),
            [new Column('name')],
            [['name' => 'A']],
            $this->filters(),
            rowActions: [Action::edit('/e', 'Edit'), new Action('view', 'Open', '/v', display: 'icon_text')],
            filtersSubmit: new Action('filter', 'Apply filters', attributes: ['data-testid' => 'apply']),
            filtersClear: new Action('close', 'Reset', '/orders'),
            filterActions: [new Action('export', 'CSV', '/orders.csv', display: 'text')],
            filtersDisplay: 'icon',
        );
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertMatchesRegularExpression('#<button type="submit" class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-ui-filters__submit nowo-ui-btn--icon" data-testid="apply" title="Apply filters" aria-label="Apply filters"><i class="bi bi-funnel[^"]*"[^>]*></i>\s*<span class="visually-hidden">Apply filters</span></button>#', $html);
        self::assertMatchesRegularExpression('#nowo-ui-action--close nowo-ui-btn--icon" href="/orders" title="Reset" aria-label="Reset">#', $html);
        self::assertMatchesRegularExpression('#nowo-ui-action--export" href="/orders.csv"><span class="nowo-ui-action__label">CSV</span></a>#', $html);
        // row context: the cluster display (icon) unless the Action has its own
        self::assertMatchesRegularExpression('#href="/e" title="Edit" aria-label="Edit">\s*<i [^>]*></i>\s*<span class="visually-hidden">Edit</span>#', $html);
        self::assertMatchesRegularExpression('#href="/v" title="Open" aria-label="Open">\s*<i [^>]*></i>\s*<span class="nowo-ui-action__label">Open</span>#', $html);

        // icon_text submit without an icon uses the filter icon; text keeps the bare label
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->filters(), filtersDisplay: 'icon_text')]);
        self::assertMatchesRegularExpression('#nowo-ui-filters__submit"><i class="bi bi-funnel[^"]*"[^>]*></i>\s*<span class="nowo-ui-action__label">Apply</span></button>#', $html);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->filters(), filtersSubmit: new Action('filter', icon: 'filter', display: 'text'))]);
        self::assertStringContainsString('nowo-ui-filters__submit">Apply</button>', $html);
    }

    public function testInvalidDisplaysAreRejected(): void
    {
        try {
            new ListPage(new PageHeader('x'), filtersDisplay: 'big');
            self::fail('filtersDisplay');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid ListPage filtersDisplay "big"', $e->getMessage());
        }
        self::assertSame('icon', (new Action(display: 'icon'))->withConfirmScope(1)->display);

        $this->expectExceptionMessage('Invalid Action display "big" (icon|text|icon_text).');
        new Action(display: 'big');
    }

    private function filters(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, null, ['method' => 'GET'])->add('q', SearchType::class, ['required' => false])->getForm()->createView();
    }
}
