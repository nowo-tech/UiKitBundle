<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\Link;
use Nowo\UiKitBundle\View\PageHeader;

/**
 * The `menu` partial (2.3) inside a page passes the contract test on every css_framework (its framework classes come
 * from the menu slots) and renders items, actions and popover content.
 */
final class MenuContractTest extends PageContractTestCase
{
    private const TEMPLATE = '@NowoContractMenu/menu.html.twig';

    public static function pageFixtures(): iterable
    {
        yield 'menu' => new PageFixture(self::TEMPLATE, self::context());
    }

    public function testMenuMarkup(): void
    {
        $html = $this->renderPage(new PageFixture(self::TEMPLATE, self::context()), 'bootstrap5')->html;

        self::assertStringContainsString('<details class="nowo-ui-menu pc-menu nowo-ui-menu--end" data-nowo-ui-menu id="export-menu" data-pc-menu="1" data-testid="export">', $html);
        self::assertMatchesRegularExpression('#<summary class="nowo-ui-menu__toggle btn btn-outline-secondary btn-sm pc-menu-toggle" data-pc-menu-toggle="1">\s*<i[^>]*></i><span class="nowo-ui-menu__label">Export</span></summary>#', $html);
        self::assertStringContainsString('<a class="nowo-ui-menu__item pc-menu-item" href="/export.csv" data-pc-menu-item="1" download>CSV</a>', $html);
        self::assertStringContainsString('<a class="nowo-ui-menu__item pc-menu-item is-current" href="/export.json" aria-current="page" data-pc-menu-item="1" data-x="y">JSON</a>', $html);
        self::assertStringContainsString('<a class="nowo-ui-menu__item pc-menu-item" href="/items/1" data-pc-menu-item="1">View</a>', $html);
        self::assertStringContainsString('<a class="nowo-ui-menu__item pc-menu-item nowo-ui-menu__item--danger" href="/drop" data-pc-menu-item="1">Drop</a>', $html);
        self::assertStringContainsString('<li class="nowo-ui-menu__entry">', $html);
        self::assertStringNotContainsString('Hidden', $html);
        self::assertStringContainsString('<details class="nowo-ui-menu pc-menu nowo-ui-menu--start" data-nowo-ui-menu open data-pc-menu="1">', $html);
        self::assertMatchesRegularExpression('#<summary class="nowo-ui-menu__toggle btn btn-outline-secondary btn-sm pc-menu-toggle nowo-ui-menu__toggle--icon" aria-label="More actions" title="More actions" data-pc-menu-toggle="1">\s*<i[^>]*></i><span class="visually-hidden">More actions</span></summary>#', $html);
        self::assertStringContainsString('<div class="nowo-ui-popover pc-popover" data-pc-popover="1"><p>Free <b>content</b></p></div>', $html);
        self::assertStringContainsString('<span data-host-only="nowo-ui-menu__toggle pc-menu-toggle"></span>', $html);
        self::assertStringContainsString('<summary class="nowo-ui-menu__toggle btn btn-outline-secondary btn-sm pc-menu-toggle" data-pc-menu-toggle="1"><span class="nowo-ui-menu__label">Text</span></summary>', $html);
    }

    protected static function inlineTemplates(): array
    {
        return [self::TEMPLATE => <<<'TWIG'
            {% extends '@NowoUiKit/page/dashboard.html.twig' %}
            {% block nowo_ui_dashboard_cards %}
                {{ include(nowo_ui_partial('menu'), {id: 'export-menu', label: label, icon: 'export', items: items, attr: {'data-testid': 'export'}}, false) }}
                {{ include(nowo_ui_partial('menu'), {icon: 'config', display: 'icon', align: 'start', open: true, content: '<p>Free <b>content</b></p>'}, false) }}
                {{ include(nowo_ui_partial('menu'), {label: 'Text', icon: 'config', display: 'text', items: []}, false) }}
                <span data-host-only="{{ nowo_ui_class('menu_toggle', null, defaults: false) }}"></span>
            {% endblock %}
            TWIG];
    }

    /**
     * @return array<string, mixed>
     */
    private static function context(): array
    {
        return [
            'page' => new DashboardPage(new PageHeader('Menus'), [new KpiTile('Items', '1')]),
            'label' => new Label('action.export', 'NowoUiKitBundle'),
            'items' => [
                new Link('CSV', '/export.csv', ['download' => true]),
                ['label' => 'JSON', 'href' => '/export.json', 'current' => true, 'attr' => ['data-x' => 'y']],
                Action::view('/items/1'),
                new Action('custom', 'Drop', '/drop', variant: 'danger'),
                Action::delete('/items/1', 'tok', Confirm::delete()),
                new Action('custom', 'Hidden', '/h', visible: false),
            ],
        ];
    }

    protected static function strictMissingTranslations(): bool
    {
        return true;
    }
}
