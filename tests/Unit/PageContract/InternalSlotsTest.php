<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AuthPage;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\Link;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\TestCase;

/**
 * Slots for the internals of cards, detail lists, confirm dialog / page, auth footer and KPI link tiles
 * (REQ-UI-003 round 3).
 */
final class InternalSlotsTest extends TestCase
{
    private const THEME = ['classes' => [
        'card_title' => 'app-card-title',
        'card_intro' => 'app-card-intro',
        'detail_list' => 'nowo-ui-detail-list--stacked',
        'detail_item' => 'app-dl-item',
        'detail_term' => 'app-dt',
        'detail_value' => 'app-dd',
        'confirm_panel' => 'app-confirm-panel',
        'confirm_header' => 'app-confirm-header',
        'confirm_title' => 'app-confirm-title',
        'confirm_body' => 'app-confirm-body',
        'confirm_message' => 'app-confirm-message',
        'confirm_expect_label' => 'app-expect-label',
        'confirm_footer' => 'app-confirm-footer',
        'auth_footer' => 'app-auth-footer',
        'auth_links' => 'app-auth-links',
        'kpi_link' => 'app-kpi-link',
    ]];

    public function testCardAndDetailListSlots(): void
    {
        $twig = PageContractTwig::create([], self::THEME);
        $html = $twig->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage(new PageHeader('Order'), [new DetailSection('General', [new DetailItem('Name', 'Ann')])])]);
        self::assertStringContainsString('<h2 class="nowo-ui-card__title app-card-title">General</h2>', $html);
        self::assertMatchesRegularExpression('#<dl class="nowo-ui-detail-list nowo-ui-detail-list--stacked">\s*<div class="nowo-ui-detail-list__item app-dl-item">\s*<dt class="nowo-ui-detail-list__term app-dt">Name</dt>\s*<dd class="nowo-ui-detail-list__value app-dd">Ann</dd>#', $html);

        $html = $twig->render('@NowoUiKit/page/settings.html.twig', ['page' => new SettingsPage(new PageHeader('Settings'), [new SettingsSection('a', 'Mail', intro: 'Outgoing')])]);
        self::assertStringContainsString('<h2 class="nowo-ui-card__title app-card-title">Mail</h2>', $html);
        self::assertStringContainsString('<p class="nowo-ui-card__intro app-card-intro">Outgoing</p>', $html);

        $html = $twig->render('@NowoUiKitBundle/partials/_card.html.twig', ['title' => 'Card', 'intro' => 'Intro', 'body' => 'x']);
        self::assertStringContainsString('<h2 class="nowo-ui-card__title app-card-title">Card</h2>', $html);
        self::assertStringContainsString('<p class="nowo-ui-card__intro app-card-intro">Intro</p>', $html);
    }

    public function testConfirmDialogAndPageSlots(): void
    {
        $twig = PageContractTwig::create([], self::THEME);
        $html = $twig->render('@NowoUiKitBundle/partials/_confirm.html.twig', [
            'confirm' => new Confirm('Delete?', 'Gone for good.', expectedValue: 'acme'),
            'id' => 'c1',
            'action' => Action::delete('/x', 'tok'),
        ]);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-confirm__panel app-confirm-panel">\s*<header class="nowo-ui-confirm__header app-confirm-header">\s*<h2 class="nowo-ui-confirm__title app-confirm-title" id="c1-title">Delete\?</h2>#', $html);
        self::assertStringContainsString('<div class="nowo-ui-confirm__body app-confirm-body"><p class="nowo-ui-confirm__message app-confirm-message">Gone for good.</p></div>', $html);
        self::assertStringContainsString('<label class="nowo-ui-confirm__expect-label app-expect-label" for="c1-expected">', $html);
        self::assertStringContainsString('<div class="nowo-ui-confirm__footer app-confirm-footer">', $html);
        $html = $twig->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['id' => 'c2', 'title' => 'T', 'body' => '<b>x</b>']);
        self::assertStringContainsString('<div class="nowo-ui-confirm__body app-confirm-body"><b>x</b></div>', $html);

        $page = new ConfirmPage(new PageHeader('Delete'), new Confirm('Delete Ann?', 'Really.', expectedValue: 'ann'), Action::delete('/people/1', 'tok'), '/people');
        $html = $twig->render('@NowoUiKit/page/confirm.html.twig', ['page' => $page]);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-card__body card-body nowo-ui-confirm-page__panel app-confirm-panel">\s*<header class="nowo-ui-confirm-page__header app-confirm-header">\s*<h2 class="nowo-ui-confirm__title app-confirm-title">Delete Ann\?</h2>\s*</header>\s*<div class="nowo-ui-confirm-page__body app-confirm-body"><p class="nowo-ui-confirm__message app-confirm-message">Really.</p></div>#', $html);
        self::assertStringContainsString('<label class="nowo-ui-confirm__expect-label app-expect-label" for="nowo-ui-confirm-page-expected">', $html);
        self::assertStringContainsString('<div class="nowo-ui-form__actions d-flex flex-wrap gap-2 mt-3 app-confirm-footer">', $html);
        // the dialog-box classes stay off the page
        self::assertStringNotContainsString('nowo-ui-confirm__panel', $html);

        // empty theme: no stray spaces
        $html = PageContractTwig::create()->render('@NowoUiKit/page/confirm.html.twig', ['page' => new ConfirmPage(new PageHeader('Delete'), new Confirm('Sure?'), Action::delete('/p/1', 'tok'))]);
        self::assertStringContainsString('<header class="nowo-ui-confirm-page__header">', $html);
        self::assertStringNotContainsString('nowo-ui-confirm-page__body', $html);
    }

    public function testAuthFooterAndKpiLinkSlots(): void
    {
        $twig = PageContractTwig::create([], self::THEME);
        $html = $twig->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Sign in', links: [new Link('Forgot?', '/reset')])]);
        self::assertMatchesRegularExpression('#<footer class="nowo-ui-auth-panel__footer app-auth-footer">\s*<ul class="nowo-ui-auth-panel__links app-auth-links">#', $html);

        $html = $twig->render('@NowoUiKit/page/dashboard.html.twig', ['page' => new DashboardPage(new PageHeader('Overview'), [new KpiTile('Orders', 4, href: '/orders', linkTile: true), new KpiTile('Plain', 1)])]);
        self::assertStringContainsString('<a class="nowo-ui-kpi card nowo-ui-kpi--neutral nowo-ui-kpi--link app-kpi-link" href="/orders">', $html);
        self::assertSame(1, substr_count($html, 'app-kpi-link'));
    }
}
