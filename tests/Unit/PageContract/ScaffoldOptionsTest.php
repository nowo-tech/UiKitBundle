<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\FilterField;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;

/**
 * Scaffold options added after the Beacon validation: list filters (data block, per-field options, Action-based
 * apply / clear), table / KPI / form / danger-zone slots, the visible actions header, responsive card tables and
 * form secondary actions in the page header.
 */
final class ScaffoldOptionsTest extends TestCase
{
    private const THEME = ['classes' => [
        'filters_field' => 'app-field',
        'filters_actions' => 'app-filter-actions',
        'table_head_row' => 'app-head-row',
        'table_head_cell' => 'app-th',
        'table_actions_head' => 'app-th-actions',
        'kpi_label' => 'app-kpi-label',
        'kpi_value' => 'app-kpi-value',
        'kpi_hint' => 'app-kpi-hint',
        'form_panel' => 'app-form-panel',
        'danger_zone_title' => 'app-danger-title',
        'danger_zone_intro' => 'app-danger-intro',
    ]];

    public function testListFiltersFieldOptionsAndActions(): void
    {
        $page = new ListPage(
            new PageHeader('Orders'),
            [new Column('name')],
            [['name' => 'A']],
            $this->filtersForm(),
            filterFields: [
                new FilterField('q', new Label('Search orders', 'admin'), 'lg', 'app-search', 'app-row', ['data-x' => '1'], ['data-row' => 'q']),
                new FilterField('status', width: 'sm'),
            ],
            filtersSubmit: new Action('filter', 'Go', icon: 'filter', attributes: ['data-testid' => 'apply']),
            filtersClear: new Action('close', 'Reset', '/orders', icon: 'close'),
            filterActions: [new Action('export', 'Export', '/orders.csv', icon: 'export')],
        );
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<div data-row="q" class="nowo-ui-filters__field app-field nowo-ui-filters__field--lg app-row">', $html);
        self::assertStringContainsString('placeholder="Search orders"', $html);
        self::assertStringContainsString('<input type="search" id="filters_q" name="filters[q]" data-x="1" placeholder="Search orders" class="app-search" />', $html);
        self::assertStringContainsString('nowo-ui-filters__field app-field nowo-ui-filters__field--sm"', $html);
        self::assertStringContainsString('<div class="nowo-ui-filters__actions d-flex flex-wrap align-items-end gap-2 app-filter-actions">', $html);
        self::assertMatchesRegularExpression('#<button type="submit" class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-ui-filters__submit" data-testid="apply"><i class="bi bi-funnel[^"]*"[^>]*></i>\s*<span class="nowo-ui-action__label">Go</span></button>#', $html);
        self::assertMatchesRegularExpression('#<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--close" href="/orders">\s*<i [^>]*></i>\s*<span class="nowo-ui-action__label">Reset</span></a>#', $html);
        self::assertStringContainsString('href="/orders.csv"', $html);

        // the data block replaces the whole filters region
        $child = "{% extends '@NowoUiKit/page/list.html.twig' %}{% block nowo_ui_list_filters %}<div data-own-filters></div>{% endblock %}";
        $html = PageContractTwig::create(['child' => $child])->render('child', ['page' => $page]);
        self::assertStringContainsString('<div data-own-filters></div>', $html);
        self::assertStringNotContainsString('data-nowo-ui-filters', $html);

        // defaults: plain apply button, no field options
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->filtersForm(), filtersSubmit: new Action('filter', variant: 'secondary'))]);
        self::assertStringContainsString('<button type="submit" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-filters__submit">Apply</button>', $html);
        self::assertStringContainsString('<div class="nowo-ui-filters__field">', $html);
    }

    public function testTableSlotsActionsHeaderAndResponsiveCards(): void
    {
        $page = new ListPage(
            new PageHeader('Orders'),
            [new Column('name', new Label('Name', 'admin'), required: true, priority: 3), new Column('email', 'E-mail', priority: 2), new Column('note', priority: 3), new Column('total', priority: 1)],
            [['name' => 'A', 'email' => 'a@x', 'note' => 'n', 'total' => 1]],
            rowActions: [Action::view('/v')],
            actionsLabel: new Label('Manage', 'admin'),
            actionsLabelVisible: true,
        );
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('data-nowo-ui-region="table" data-nowo-ui-responsive="cards"', $html);
        self::assertStringContainsString('nowo-ui-table--cards', $html);
        self::assertStringContainsString('<tr class="nowo-ui-table__head-row app-head-row">', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell app-th nowo-ui-cell--start" data-required="1">', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell app-th nowo-ui-cell--start" data-nowo-ui-priority="2">', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell app-th nowo-ui-table__actions app-th-actions nowo-ui-cell--end">Manage</th>', $html);
        self::assertStringContainsString('data-label="Name">A</td>', $html);
        self::assertStringContainsString('data-label="E-mail" data-nowo-ui-priority="2">a@x</td>', $html);
        self::assertStringContainsString('data-label="note" data-nowo-ui-priority="3">n</td>', $html);
        self::assertStringContainsString('data-label="total">1</td>', $html);

        $scroll = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], rowActions: [Action::view('/v')], responsive: 'scroll')]);
        self::assertStringNotContainsString('data-nowo-ui-responsive', $scroll);
        self::assertStringNotContainsString('nowo-ui-table--cards', $scroll);
        self::assertStringContainsString('<span class="visually-hidden">Actions</span>', $scroll);
    }

    public function testKpiSlotsAndLinkTile(): void
    {
        $page = new DashboardPage(new PageHeader('Overview'), [
            new KpiTile('Orders', 42, 'today', '/orders'),
            new KpiTile(new Label('Revenue', 'admin'), '1.2k', href: '/revenue', variant: 'success', linkTile: true),
            new KpiTile('Plain', 3, linkTile: true),
        ]);
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/dashboard.html.twig', ['page' => $page]);

        self::assertStringContainsString('<p class="nowo-ui-kpi__label app-kpi-label">Orders</p>', $html);
        self::assertStringContainsString('<p class="nowo-ui-kpi__value app-kpi-value"><a href="/orders">42</a></p>', $html);
        self::assertStringContainsString('<p class="nowo-ui-kpi__hint app-kpi-hint">today</p>', $html);
        self::assertMatchesRegularExpression('#<a class="nowo-ui-kpi card nowo-ui-kpi--success nowo-ui-kpi--link" href="/revenue">\s*<p class="nowo-ui-kpi__label app-kpi-label">Revenue</p>\s*<p class="nowo-ui-kpi__value app-kpi-value">1.2k</p>\s*</a>#', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-kpi card nowo-ui-kpi--neutral">\s*<p class="nowo-ui-kpi__label app-kpi-label">Plain</p>#', $html);
    }

    public function testFormPanelDangerZoneAndSecondaryActionsPlacement(): void
    {
        $header = new PageHeader('Edit order', actions: [Action::view('/orders/1')]);
        $page = new FormPage($header, $this->entityForm(), secondaryActions: [new Action('copy', 'Duplicate', '/orders/1/copy')], deleteAction: Action::delete('/orders/1', 'tok'), dangerZoneTitle: new Label('Delete order', 'admin'), dangerZoneIntro: 'This cannot be undone.');
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/form.html.twig', ['page' => $page]);

        self::assertMatchesRegularExpression('#<div class="nowo-ui-form-panel app-form-panel">\s*<form name="item"#', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-danger-zone__title app-danger-title">Delete order</h2>', $html);
        self::assertStringContainsString('<p class="nowo-ui-danger-zone__intro app-danger-intro">This cannot be undone.</p>', $html);
        // secondary actions in the header toolbar (default), after the header actions; nothing below the form
        $header_html = substr($html, (int) strpos($html, 'data-nowo-ui-region="header"'), 2000);
        self::assertMatchesRegularExpression('#nowo-ui-action--view" href="/orders/1">.*nowo-ui-action--copy" href="/orders/1/copy">#s', $header_html);
        self::assertStringNotContainsString('data-nowo-ui-region="secondary-actions"', $html);

        $below = new FormPage($header, $this->entityForm(), secondaryActions: [new Action('copy', 'Duplicate', '/orders/1/copy')], secondaryActionsInHeader: false);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/form.html.twig', ['page' => $below]);
        self::assertStringContainsString('data-nowo-ui-region="secondary-actions"', $html);
        self::assertSame(1, substr_count($html, '/orders/1/copy'));
        self::assertStringContainsString('<h2 class="nowo-ui-danger-zone__title">', PageContractTwig::create()->render('@NowoUiKit/page/form.html.twig', ['page' => new FormPage($header, $this->entityForm(), deleteAction: Action::delete('/d', 't'))]));
    }

    public function testModelValidation(): void
    {
        foreach ([
            static fn () => new FilterField('a b'),
            static fn () => new FilterField('q', width: 'huge'),
            static fn () => new FilterField('q', attributes: ['onclick' => 'x']),
            static fn () => new Column('a', priority: 4),
            static fn () => new ListPage(new PageHeader('T'), responsive: 'stack'),
            static fn () => new ListPage(new PageHeader('T'), filterFields: ['q']),
        ] as $factory) {
            try {
                $factory();
                self::fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
        $page = new ListPage(new PageHeader('T'), filterFields: [new FilterField('q'), new FilterField('status')], filterActions: [Action::view('/v')]);
        self::assertSame(['q', 'status'], array_keys($page->filterFields));
        self::assertCount(1, $page->filterActions);
    }

    private function filtersForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, null, ['method' => 'GET'])
            ->add('q', SearchType::class, ['required' => false])
            ->add('status', ChoiceType::class, ['required' => false, 'choices' => ['Open' => 'open']])
            ->getForm()->createView();
    }

    private function entityForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('item', FormType::class)->add('name', TextType::class)->getForm()->createView();
    }
}
