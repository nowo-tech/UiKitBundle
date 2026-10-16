<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\WizardPage;
use Nowo\UiKitBundle\View\WizardStep;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;

/**
 * "Template contract" of the scaffolds (REQ-UI-003 validation): every include goes through nowo_ui_partial(),
 * host overrides written for the 1.x partial inputs keep working (1.x shapes are passed next to the view models),
 * page `attributes` land on the scaffold root and the standard regions carry data-nowo-ui-region.
 */
final class TemplateContractTest extends TestCase
{
    /** Host overrides that only know the 1.x inputs. */
    private const LEGACY_OVERRIDES = [
        'host/_page_header.html.twig' => '<header data-host-header>{{ title }}|{{ intro }}|{{ toolbar|raw }}</header>',
        'host/_pagination.html.twig' => '<nav data-host-pager>{{ pagination.page }}/{{ pagination.total_pages }} of {{ pagination.total }} ({{ pagination.per_page }}, {{ item_count }})</nav>',
        'host/_row_actions.html.twig' => '{% for a in actions %}<a data-host-action="{{ a.kind }}" data-method="{{ a.method }}" data-token="{{ a.csrf_token }}" data-confirm="{{ a.confirm_id }}" href="{{ a.href }}">{{ a.label }}</a>{% endfor %}',
        'host/_empty.html.twig' => '<p data-host-empty>{{ message|default("default") }}</p>',
    ];

    public function testLegacyHostOverridesReceiveThe1xShapes(): void
    {
        $twig = PageContractTwig::create(self::LEGACY_OVERRIDES, ['partials' => [
            'page_header' => 'host/_page_header.html.twig',
            'pagination' => 'host/_pagination.html.twig',
            'row_actions' => 'host/_row_actions.html.twig',
            'empty' => 'host/_empty.html.twig',
        ]]);
        $page = new ListPage(
            new PageHeader(new Label('Orders'), 'All orders', [Action::create('/orders/new', 'New order')]),
            [new Column('name')],
            [['id' => 7, 'name' => 'A']],
            rowActions: static fn (array $row): array => [
                Action::route('edit', 'orders_edit', ['id' => $row['id']]),
                Action::delete('/orders/'.$row['id'], 'tok', new Confirm('Delete?', id: 'del-'.$row['id'])),
                new Action('restore', href: '/orders/7/restore', method: 'PATCH', form: self::csrfForm()),
            ],
            pagination: new Pagination(2, 10, 35),
        );
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertMatchesRegularExpression('#<header data-host-header>Orders\|All orders\|<a class="[^"]*nowo-ui-action--create" href="/orders/new">\s*<span class="nowo-ui-action__label">New order</span></a></header>#', $html);
        self::assertStringContainsString('<nav data-host-pager>2/4 of 35 (10, 1)</nav>', $html);
        self::assertStringContainsString('<a data-host-action="edit" data-method="GET" data-token="" data-confirm="" href="/orders_edit?id=7">Edit</a>', $html);
        self::assertStringContainsString('<a data-host-action="delete" data-method="POST" data-token="tok" data-confirm="del-7" href="/orders/7">Delete</a>', $html);
        self::assertMatchesRegularExpression('#<a data-host-action="restore" data-method="POST" data-token="[^"]+" data-confirm="" href="/orders/7/restore">Restore</a>#', $html);
        // the confirm dialog of a 1.x override's confirm_id is rendered once after the table
        self::assertStringContainsString('<div class="nowo-ui-list-page__confirms" data-nowo-ui-region="confirms">', $html);
        self::assertSame(1, substr_count($html, 'id="del-7"'));

        $empty = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), empty: new EmptyState(message: new Label('Nothing yet', 'admin')))]);
        self::assertStringContainsString('<p data-host-empty>Nothing yet</p>', $empty);
        $empty = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'))]);
        self::assertStringContainsString('<p data-host-empty>default</p>', $empty);
    }

    public function testDefaultPartialsRenderTheObjectsAndConfirmDialogsOnce(): void
    {
        $page = new ListPage(
            new PageHeader('Orders', actions: [Action::create('/new')], breadcrumbs: [new Crumb('Home', '/'), new Crumb('Orders')]),
            [new Column('name')],
            [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']],
            rowActions: static fn (array $row): array => [Action::delete('/o/'.$row['id'], 'tok'), Action::edit('/o/'.$row['id'], 'Edit')],
            pagination: new Pagination(1, 2, 3),
            attributes: ['data-testid' => 'orders-list', 'hidden' => false],
        );
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<section class="nowo-ui-list-page" data-nowo-ui-page="list" data-testid="orders-list">', $html);
        foreach (['header', 'table', 'confirms', 'pagination'] as $region) {
            self::assertStringContainsString('data-nowo-ui-region="'.$region.'"', $html, $region);
        }
        $confirmId = Action::delete('/o/1', 'tok')->confirmId();
        self::assertSame(1, substr_count($html, '<dialog'."\n".'    id="'.$confirmId.'"'));
        self::assertSame(2, substr_count($html, 'data-nowo-confirm-target="nowo-ui-confirm-'));
        self::assertSame(1, substr_count($html, 'nowo-ui-action--create'));
        self::assertStringContainsString('<a class="nowo-ui-breadcrumbs__link" href="/">Home</a>', $html);
        self::assertStringContainsString('Showing 1–2 of 3', $html);
    }

    public function testEveryIncludeGoesThroughTheThemePartials(): void
    {
        $overrides = [
            'host/_breadcrumbs.html.twig' => '<nav data-host-crumbs>{{ header.breadcrumbs|length }}</nav>',
            'host/_action.html.twig' => '<i data-host-action="{{ action.kind }}"></i>',
            'host/_list_filters.html.twig' => '<form data-host-filters data-region="{{ region }}"></form>',
            'host/_detail_list.html.twig' => '<dl data-host-dl>{{ items|length }}</dl>',
            'host/_section_form.html.twig' => '<form data-host-section-form></form>',
            'host/_wizard_form.html.twig' => '<form data-host-wizard-form></form>',
            'host/_tabs.html.twig' => '<nav data-host-tabs>{% for i in items %}{{ i.label }}{{ i.current ? "*" }};{% endfor %}</nav>',
            'host/_card.html.twig' => '<div data-host-card></div>',
        ];
        $twig = PageContractTwig::create($overrides, ['partials' => [
            'breadcrumbs' => 'host/_breadcrumbs.html.twig',
            'action' => 'host/_action.html.twig',
            'list_filters' => 'host/_list_filters.html.twig',
            'detail_list' => 'host/_detail_list.html.twig',
            'section_form' => 'host/_section_form.html.twig',
            'wizard_form' => 'host/_wizard_form.html.twig',
            'tabs' => 'host/_tabs.html.twig',
        ]], breadcrumbKit: false);
        $header = new PageHeader('T', actions: [Action::create('/new')], breadcrumbs: [new Crumb('Home', '/')]);

        $list = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage($header, [new Column('n')], [['n' => 1]], $this->filtersForm(), [Action::edit('/e')], empty: new EmptyState(action: Action::create('/x')))]);
        self::assertStringContainsString('<nav data-host-crumbs>1</nav>', $list);
        self::assertStringContainsString('<i data-host-action="create"></i>', $list);
        self::assertStringContainsString('<i data-host-action="edit"></i>', $list);
        self::assertStringContainsString('<form data-host-filters data-region="filters"></form>', $list);
        $empty = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('T'), empty: new EmptyState(action: Action::create('/x')))]);
        self::assertStringContainsString('<i data-host-action="create"></i>', $empty);

        $show = $twig->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage($header, [new DetailSection('S', [new DetailItem('a', 'b')])], [new DetailItem('c', 'd')])]);
        self::assertSame(2, substr_count($show, '<dl data-host-dl>1</dl>'));

        $settings = $twig->render('@NowoUiKit/page/settings.html.twig', ['page' => new SettingsPage($header, [new SettingsSection('a', 'A', '/a', $this->entityForm()), new SettingsSection('b', new Label('B', 'admin'), '/b')], 'a')]);
        self::assertStringContainsString('<nav data-host-tabs>A*;B;</nav>', $settings);
        self::assertStringContainsString('<form data-host-section-form></form>', $settings);

        $wizard = $twig->render('@NowoUiKit/page/wizard.html.twig', ['page' => new WizardPage($header, [new WizardStep('one', 'One')], 'one', $this->entityForm())]);
        self::assertStringContainsString('<form data-host-wizard-form></form>', $wizard);

        // _card actions through the action partial
        $card = $twig->render('@NowoUiKitBundle/partials/_card.html.twig', ['title' => 'C', 'actions' => [Action::view('/v')]]);
        self::assertStringContainsString('<i data-host-action="view"></i>', $card);
    }

    public function testRegionsAndPageAttributesOnEveryScaffold(): void
    {
        $twig = PageContractTwig::create();
        $header = new PageHeader('T');
        $attrs = ['data-testid' => 'page'];
        $pages = [
            'show' => [new ShowPage($header, [new DetailSection('S', [new DetailItem('a', 'b')])], [new DetailItem('c', 'd')], attributes: $attrs), ['header', 'section', 'meta']],
            'form' => [new FormPage($header, $this->entityForm(), secondaryActions: [Action::view('/v')], deleteAction: Action::delete('/d', 't'), attributes: $attrs), ['header', 'form', 'danger-zone']],
            'settings' => [new SettingsPage($header, [new SettingsSection('a', 'A', '/a', $this->entityForm()), new SettingsSection('b', 'B', '/b')], attributes: $attrs), ['header', 'tabs', 'section', 'form']],
            'dashboard' => [new DashboardPage($header, [new \Nowo\UiKitBundle\View\KpiTile('K', 1)], attributes: $attrs), ['header', 'kpis', 'cards']],
            'confirm' => [new ConfirmPage($header, Confirm::delete(), Action::delete('/d', 't'), attributes: $attrs), ['header', 'confirm']],
            'wizard' => [new WizardPage($header, [new WizardStep('one', 'One')], 'one', $this->entityForm(), attributes: $attrs), ['header', 'steps', 'step', 'form']],
            'auth' => [new \Nowo\UiKitBundle\View\AuthPage('Sign in', $this->entityForm(), attributes: $attrs), ['panel', 'form']],
        ];
        foreach ($pages as $type => [$page, $regions]) {
            $html = $twig->render('@NowoUiKit/page/'.$type.'.html.twig', ['page' => $page]);
            self::assertMatchesRegularExpression('#data-nowo-ui-page="'.$type.'"[^>]*data-testid="page"#', $html, $type);
            foreach ($regions as $region) {
                self::assertStringContainsString('data-nowo-ui-region="'.$region.'"', $html, $type.': '.$region);
            }
        }
    }

    public function testViewModelsExposeThe1xKeys(): void
    {
        $pagination = new Pagination(3, 20, 95, route: 'items');
        self::assertTrue(isset($pagination['per_page']));
        self::assertSame(20, $pagination['per_page']);
        self::assertSame(5, $pagination['total_pages']);
        self::assertFalse(isset($pagination['route_params']));
        self::assertFalse(isset($pagination[0]));
        self::assertNull($pagination['nope']);

        $action = Action::delete('/x', 'tok', new Confirm('Sure?', id: 'c1'));
        self::assertSame('tok', $action['csrf_token']);
        self::assertSame('c1', $action['confirm_id']);
        self::assertSame('POST', $action['method']);
        self::assertFalse(isset($action['modal_id']));

        $html = PageContractTwig::create(['t' => '{{ p.per_page }}/{{ p.total_pages }}|{{ a.csrf_token }}|{{ a.confirm_id }}|{{ a.kind }}'])->render('t', ['p' => $pagination, 'a' => $action]);
        self::assertSame('20/5|tok|c1|delete', $html);

        $writes = [
            static fn () => $pagination['page'] = 1,
            static fn () => $action['kind'] = 'edit',
            static function () use ($pagination): void {
                unset($pagination['page']);
            },
            static function () use ($action): void {
                unset($action['kind']);
            },
        ];
        foreach ($writes as $write) {
            try {
                $write();
                self::fail('Expected LogicException');
            } catch (\LogicException $e) {
                self::assertStringContainsString('is immutable', $e->getMessage());
            }
        }
    }

    public function testPageAttributesAreValidated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no on* handlers');
        new ListPage(new PageHeader('T'), attributes: ['onclick' => 'x']);
    }

    private function filtersForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, null, ['method' => 'GET'])->add('q', SearchType::class, ['required' => false])->getForm()->createView();
    }

    private function entityForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('item', FormType::class)->add('name', TextType::class)->getForm()->createView();
    }

    private static function csrfForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('csrf', FormType::class)->add('_token', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, ['data' => 'form-tok'])->getForm()->createView();
    }
}
