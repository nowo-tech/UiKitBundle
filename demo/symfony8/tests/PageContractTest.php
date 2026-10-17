<?php

declare(strict_types=1);

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\ShowPage;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Twig\Loader\FilesystemLoader;

/**
 * The demo's page templates (templates/page_contract) honour the UiKit page contract (REQ-UI-003), exactly as a UI
 * bundle's pages would: scaffold + data blocks, theme slots only, no raw keys.
 */
final class PageContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        $forms = Forms::createFormFactoryBuilder()->getFormFactory();
        $order = ['id' => 1042, 'customer' => 'Lucía Fernández', 'email' => 'lucia@example.com', 'total' => '249.90', 'status' => 'paid', 'placed' => '2026-10-16 09:12'];

        yield 'overview' => new PageFixture('page_contract/index.html.twig', [
            'page' => new DashboardPage(new PageHeader('Page contract'), [new KpiTile('Scaffolds', '8')]),
            'cards' => [['title' => 'List', 'scaffold' => 'page/list', 'text' => 'Tables.', 'href' => '/list']],
        ]);
        yield 'dashboard' => new PageFixture('page_contract/dashboard.html.twig', [
            'page' => new DashboardPage(new PageHeader('Dashboard'), [new KpiTile('Revenue', '48,210 €')]),
            'recent' => [$order],
            'stock' => [['product' => 'Linen tote bag', 'left' => 3], ['product' => 'Mug', 'left' => 8]],
        ]);
        yield 'orders' => new PageFixture('page_contract/orders/index.html.twig', ['page' => new ListPage(
            new PageHeader('Orders', null, [Action::create('/orders/new', 'New order')]),
            [new Column('id', '#'), new Column('customer', 'Customer'), new Column('placed', 'Placed'), new Column('status', 'Status'), new Column('total', 'Total')],
            [$order],
            $forms->createNamedBuilder('filters', options: ['method' => 'GET'])->add('q', SearchType::class, ['required' => false])->getForm()->createView(),
            [Action::view('/orders/1042')],
            new Pagination(1, 5, 1),
        )]);
        yield 'order' => new PageFixture('page_contract/orders/show.html.twig', ['order' => $order, 'page' => new ShowPage(
            new PageHeader('Order #1042', null, [new Action('restore', 'Reopen', '/orders/1042/reopen', method: 'POST', form: $forms->createNamedBuilder('reopen_order')->add('_token', HiddenType::class, ['data' => 'demo-token'])->getForm()->createView())]),
            [new DetailSection('Customer', [new DetailItem('Name', $order['customer'])])],
        )]);
        yield 'order_edit' => new PageFixture('page_contract/orders/edit.html.twig', ['page' => new FormPage(
            new PageHeader('Edit order #1042'),
            $forms->createNamedBuilder('order')->add('customer', TextType::class)->getForm()->createView(),
        )]);
    }

    protected static function templatePaths(): array
    {
        return [FilesystemLoader::MAIN_NAMESPACE => \dirname(__DIR__).'/templates'];
    }
}
