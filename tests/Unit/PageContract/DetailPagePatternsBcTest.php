<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.21 detail page patterns are opt-in: pages that use none of them (no PageHeader::status, the default `toolbar`
 * actions layout, no ShowPage::stats, list sections, menus without `square` / `menu_items`, no font_preload config)
 * render byte-identical to UiKit 2.20. The fixtures under tests/Fixtures/bc-2.20 were rendered by UiKit 2.20.0
 * (origin/main 2225ff5) from exactly these scenarios.
 */
final class DetailPagePatternsBcTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function scenarios(): iterable
    {
        foreach (array_keys(self::renders()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('scenarios')]
    public function testPagesWithoutTheNewOptionsRenderAsIn220(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.20/'.$name.'.html';
        self::assertFileExists($file);
        self::assertSame(file_get_contents($file), self::renders()[$name](), $name.' must render exactly as UiKit 2.20 printed it');
    }

    /**
     * Scenario name => render callback (old API only).
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        return [
            'show_bootstrap5' => static fn (): string => self::twig('bootstrap5')->render('@NowoUiKit/page/show.html.twig', ['page' => self::showPage()]),
            'show_tailwind' => static fn (): string => self::twig('tailwind')->render('@NowoUiKit/page/show.html.twig', ['page' => self::showPage()]),
            'show_custom' => static fn (): string => self::twig('custom')->render('@NowoUiKit/page/show.html.twig', ['page' => self::showPage()]),
            'page_header_partial' => static fn (): string => self::twig('bootstrap5')->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => self::header()]),
            'page_header_contract' => static fn (): string => self::twig('bootstrap5')->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header(), 'bundle' => null]),
            'menu_icon_popover' => static fn (): string => self::twig('bootstrap5')->render('@NowoUiKitBundle/partials/_menu.html.twig', [
                'items' => self::actions(), 'icon' => 'config', 'display' => 'icon', 'popover' => true, 'id' => 'more', 'confirm_external' => true,
            ]),
            'menu_icon_details' => static fn (): string => self::twig('custom')->render('@NowoUiKitBundle/partials/_menu.html.twig', [
                'items' => self::actions(), 'icon' => 'config', 'display' => 'icon', 'label' => 'Order actions',
            ]),
            'detail_list_modifiers' => static fn (): string => self::twig('bootstrap5')->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => self::items(), 'modifier' => 'facts'])
                .self::twig('bootstrap5')->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => self::items(), 'modifier' => 'stacked'])
                .self::twig('bootstrap5')->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => self::items(), 'modifier' => 'meta']),
            'actions_toolbar' => static function (): string {
                $twig = self::twig('bootstrap5');
                $html = '';
                foreach (self::actions() as $action) {
                    $html .= $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => $action, 'context' => 'toolbar']);
                    $html .= $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => $action, 'context' => 'toolbar', 'display' => 'icon']);
                    $html .= $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => $action, 'context' => 'row']);
                }

                return $html;
            },
            'error_page' => static fn (): string => self::twig('bootstrap5', ['e' => "{% extends '@NowoUiKit/error/status.html.twig' %}"])->render('e', ['status_code' => 404]),
        ];
    }

    /**
     * @param array<string, string> $templates
     */
    private static function twig(string $framework, array $templates = []): Environment
    {
        $request = Request::create('https://app.example/orders/1042', 'GET');
        $request->attributes->set('_route', 'order_show');
        $request->attributes->set('_route_params', []);
        $request->attributes->set('csp_nonce', 'n0nce');

        return PageContractTwig::create($templates, framework: $framework, request: $request, breadcrumbKit: false);
    }

    private static function header(): PageHeader
    {
        return new PageHeader('Order #1042', 'Placed by Lucía on 2026-10-16.', self::actions(), [new Crumb('Orders', '/orders'), new Crumb('Order #1042')]);
    }

    private static function showPage(): ShowPage
    {
        return new ShowPage(
            self::header(),
            [new DetailSection('Customer', self::items(), 'customer'), new DetailSection('Payment', [new DetailItem('Total', '249.90 €')])],
            [new DetailItem('Created', '2026-10-16 09:12')],
            attributes: ['data-testid' => 'order'],
        );
    }

    /**
     * @return list<Action>
     */
    private static function actions(): array
    {
        return [
            Action::edit('/orders/1042/edit'),
            new Action('restore', 'Reopen', '/orders/1042/reopen', method: 'POST', csrfToken: 'tok'),
            new Action('export', 'Export', '/orders/1042.csv', icon: 'export', display: 'icon'),
            new Action('share', 'Share', dialogId: 'share-dialog'),
            Action::delete('/orders/1042', 'tok', Confirm::delete('The order will be deleted.', 'Delete order #1042?')),
            Action::create('/orders/new', 'New order'),
        ];
    }

    /**
     * @return list<DetailItem>
     */
    private static function items(): array
    {
        return [new DetailItem('Name', 'Lucía'), new DetailItem('Email', 'lucia@example.com', 'mailto:lucia@example.com'), new DetailItem('Status', 'Paid', badge: 'success'), new DetailItem('Gift wrap', false)];
    }
}
