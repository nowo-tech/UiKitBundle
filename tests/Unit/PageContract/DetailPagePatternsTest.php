<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\HeaderActions;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\Stat;
use PHPUnit\Framework\TestCase;

/**
 * 2.21 detail page patterns (Beacon UI standardisation): status badge next to the title, split header actions (icon
 * buttons, one primary, the square "more" menu with destructive last and the confirm dialogs outside the popover),
 * key figures row and the compact details grid.
 */
final class DetailPagePatternsTest extends TestCase
{
    public function testActionCopiesWithAnotherDisplay(): void
    {
        $edit = Action::edit('/m/1/edit', 'Edit');
        $icon = $edit->asIcon();
        self::assertSame('icon', $icon->display);
        self::assertNull($icon->icon);
        self::assertNull($edit->display, 'the original is unchanged');

        $pause = (new Action('custom', 'Pause', '/m/1/pause', method: 'POST', csrfToken: 't', attributes: ['data-testid' => 'pause']))->withDisplay('icon', 'pause');
        self::assertSame(['icon', 'pause', 'POST', 't', ['data-testid' => 'pause']], [$pause->display, $pause->icon, $pause->method, $pause->csrfToken, $pause->attributes]);
        self::assertSame('pause', $pause->withDisplay(null)->icon, 'a null icon keeps the current one');
        self::assertNull($pause->withDisplay(null)->display);
        self::assertSame('7', $pause->withConfirmScope(7)->withDisplay('text')->confirmScope, 'the confirm scope survives');

        $this->expectException(\InvalidArgumentException::class);
        $edit->withDisplay('huge');
    }

    public function testHeaderActionsSplit(): void
    {
        $edit = Action::edit('/e')->asIcon();
        $share = (new Action('share', 'Share', dialogId: 'share'))->asIcon('share');
        $create = Action::create('/new', 'New');
        $save = new Action('save', 'Save', '/save', method: 'POST', csrfToken: 't');
        $delete = Action::delete('/d', 't');
        $export = new Action('export', 'Export', '/x.csv');
        $hidden = new Action('view', 'Hidden', '/h', visible: false);
        $archive = new Action('custom', 'Archive', '/a', variant: 'danger');

        $split = HeaderActions::split([$delete, $edit, $create, $export, $hidden, $share, $save, $archive]);
        self::assertSame([$edit, $share], $split->icons);
        self::assertSame($create, $split->primary);
        self::assertSame([$export, $save, $delete, $archive], $split->more, 'a second primary goes to the menu, destructive actions last');
        self::assertSame([$delete], $split->confirmActions());
        self::assertFalse($split->isEmpty());

        $empty = HeaderActions::split([$hidden]);
        self::assertTrue($empty->isEmpty());
        self::assertSame([], $empty->confirmActions());
        self::assertFalse((new HeaderActions(icons: [$edit]))->isEmpty());

        $this->expectException(\InvalidArgumentException::class);
        HeaderActions::split(['nope']);
    }

    public function testViewModelValidationAndCopies(): void
    {
        $status = new PageStatus(new Label('monitor.status.up'), 'success', 'Checked 1 min ago');
        $header = new PageHeader('Monitor', null, [Action::edit('/e')->asIcon(), Action::delete('/d', 't')]);
        self::assertSame('toolbar', $header->actionsLayout);
        self::assertNull($header->status);

        $split = $header->withStatus($status)->withSplitActions();
        self::assertSame($status, $split->status);
        self::assertSame('split', $split->actionsLayout);
        self::assertSame('toolbar', $split->withSplitActions(false)->actionsLayout);
        self::assertSame($status, $split->withActions([])->status, 'withActions keeps the 2.21 options');
        self::assertSame('split', $split->withTabs(null)->actionsLayout);
        self::assertCount(1, $split->splitActions()->icons);
        self::assertCount(1, $split->splitActions()->more);
        self::assertNull($split->withStatus(null)->status);

        $page = new ShowPage($header, [new DetailSection('Details', [], layout: 'facts')], stats: [new Stat('Uptime', '99.9 %', tone: 'success')]);
        self::assertSame('facts', $page->sections[0]->layout);
        self::assertSame('success', $page->stats[0]->tone);
        self::assertSame('list', (new DetailSection())->layout);

        foreach ([
            static fn (): PageStatus => new PageStatus('Up', 'green'),
            static fn (): Stat => new Stat('Uptime', 1, tone: 'loud'),
            static fn (): DetailSection => new DetailSection(layout: 'table'),
            static fn (): PageHeader => new PageHeader('T', actionsLayout: 'kebab'),
            static fn (): ShowPage => new ShowPage($header, stats: ['nope']),
        ] as $i => $invalid) {
            try {
                $invalid();
                self::fail('case '.$i.' must throw');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testShowScaffoldWithStatusSplitActionsStatsAndFacts(): void
    {
        $header = new PageHeader('Monitor', 'https://example.com', [
            Action::edit('/m/1/edit')->asIcon(),
            new Action('custom', 'Pause', '/m/1/pause', method: 'POST', csrfToken: 't', icon: 'pause', display: 'icon'),
            new Action('export', 'Export', '/m/1.csv'),
            new Action('restore', 'Reset', '/m/1/reset', method: 'POST', csrfToken: 't'),
            Action::delete('/m/1', 't', Confirm::delete('All checks will be deleted.', 'Delete monitor?')),
            Action::create('/m/new', 'New monitor'),
        ], status: new PageStatus('Up', 'success', 'Checked 1 min ago'), actionsLayout: 'split');
        $page = new ShowPage(
            $header,
            [new DetailSection('Details', [new DetailItem('Interval', '1 min'), new DetailItem('Type', 'HTTP')], layout: 'facts'), new DetailSection('More', [new DetailItem('Owner', 'Ann')])],
            stats: [new Stat('Uptime', '99.9 %', tone: 'success'), new Stat(new Label('stat.response'), 120, 'p95', href: '/r')],
        );
        $html = PageContractTwig::create(breadcrumbKit: false, messages: ['messages' => ['stat.response' => 'Response (ms)']])->render('@NowoUiKit/page/show.html.twig', ['page' => $page]);

        // status badge next to the title
        self::assertStringContainsString('<div class="nowo-ui-page-header__title-row"><h1 class="nowo-ui-page-header__title h3 mb-1">Monitor</h1><span class="nowo-ui-page-status nowo-ui-badge nowo-ui-badge--success badge bg-success-subtle text-success-emphasis border border-success-subtle" data-nowo-ui-page-status="success" title="Checked 1 min ago">Up</span></div>', $html);
        // icon buttons (aria-label + title), then the primary, then the square menu
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--edit nowo-ui-btn--icon" href="/m/1/edit" title="Edit" aria-label="Edit">', $html);
        self::assertStringContainsString('<button type="submit" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--custom nowo-ui-btn--icon" title="Pause" aria-label="Pause">', $html);
        self::assertMatchesRegularExpression('#nowo-ui-action--edit.*nowo-ui-action--custom.*nowo-ui-action--create.*data-nowo-ui-header-more#s', $html);
        self::assertSame(1, substr_count($html, 'btn btn-primary nowo-ui-action--create'));
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-btn--icon nowo-ui-menu__toggle--icon nowo-ui-menu__toggle--square" popovertarget="nowo-ui-header-more-panel" aria-haspopup="menu" aria-expanded="false" data-nowo-ui-popover-trigger aria-label="More actions" title="More actions"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i><span class="visually-hidden">More actions</span></button>', $html);
        // the menu: GET link, POST item, destructive confirm toggle last; the dialog after the menu, outside the panel
        self::assertStringContainsString('<li><a class="nowo-ui-menu__item" href="/m/1.csv">Export</a></li>', $html);
        self::assertStringContainsString('<button type="submit" class="nowo-ui-menu__item nowo-ui-action--restore"><span class="nowo-ui-action__label">Reset</span></button>', $html);
        self::assertMatchesRegularExpression('#<button type="button" class="nowo-ui-menu__item nowo-ui-menu__item--danger nowo-ui-action--delete" data-nowo-confirm-open data-nowo-confirm-target="(nowo-ui-confirm-[0-9a-f]+)"><span class="nowo-ui-action__label">Delete</span></button>\s*</li>\s*</ul>\s*</div>\s*<dialog\s+id="\1"#', $html);
        self::assertMatchesRegularExpression('#Reset</span>.*Delete</span>#s', $html);
        self::assertSame(1, substr_count($html, '<dialog'), 'one confirm dialog, outside the popover panel');
        // stats row before the sections, facts grid, list section unchanged
        self::assertStringContainsString('<dl class="nowo-ui-stat-row" data-nowo-ui-region="stats">', $html);
        self::assertStringContainsString('<div class="nowo-ui-stat nowo-ui-stat--success">', $html);
        self::assertStringContainsString('<dt class="nowo-ui-stat__label">Response (ms)</dt>', $html);
        self::assertStringContainsString('<dd class="nowo-ui-stat__value"><a href="/r">120</a></dd>', $html);
        self::assertStringContainsString('<dd class="nowo-ui-stat__hint">p95</dd>', $html);
        self::assertStringContainsString('<dl class="nowo-ui-detail-list nowo-ui-detail-list--facts">', $html);
        self::assertStringContainsString('<dl class="nowo-ui-detail-list">', $html);
        self::assertLessThan(strpos($html, 'nowo-ui-detail-list--facts'), strpos($html, 'nowo-ui-stat-row'));
        self::assertSame(0, preg_match('/\son[a-z]+=|style=/i', $html), 'no inline handlers or styles');
    }

    public function testPageHeaderPartialSplitsWithoutTheScaffoldAndTheHostThemeReachesTheNewSlots(): void
    {
        $header = new PageHeader('Order', null, [new Action('export', 'Export', '/x.csv', display: 'icon'), Action::delete('/d', 't')], status: new PageStatus('Paid', 'neutral'), actionsLayout: 'split');
        $theme = ['classes' => [
            'page_status' => 'acme-status', 'page_title_row' => 'acme-title-row', 'header_actions' => 'acme-actions',
            'menu_toggle_square' => 'acme-square', 'stat_row' => 'acme-stats', 'detail_list_facts' => 'acme-facts',
        ], 'attributes' => ['header_actions' => ['data-acme' => 'actions'], 'detail_list_facts' => ['data-acme' => 'facts']]];
        $twig = PageContractTwig::create(theme: $theme, framework: 'custom', breadcrumbKit: false);

        $html = $twig->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => $header]);
        self::assertStringContainsString('<div class="nowo-ui-page-header__title-row acme-title-row"><h1 class="nowo-ui-page-header__title">Order</h1><span class="nowo-ui-page-status acme-status nowo-ui-badge nowo-ui-badge--neutral" data-nowo-ui-page-status="neutral">Paid</span></div>', $html);
        self::assertStringContainsString('<div class="nowo-ui-header-actions acme-actions" data-nowo-ui-header-actions data-acme="actions">', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--icon nowo-ui-menu__toggle--icon nowo-ui-menu__toggle--square acme-square"', $html);
        self::assertSame(1, substr_count($html, 'nowo-ui-action--export'), 'the actions render once, split');
        self::assertSame(1, substr_count($html, '<dialog'));

        $facts = $twig->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => [new DetailItem('Plan', 'Pro')], 'modifier' => 'facts']);
        self::assertStringContainsString('<dl class="nowo-ui-detail-list nowo-ui-detail-list--facts acme-facts" data-acme="facts">', $facts);

        $stats = $twig->render('@NowoUiKitBundle/partials/_stat_row.html.twig', ['items' => [new Stat('Uptime', 99.5)], 'label' => 'Key figures', 'attr' => ['data-testid' => 'stats']]);
        self::assertStringContainsString('<dl class="nowo-ui-stat-row acme-stats" data-nowo-ui-region="stats" aria-label="Key figures" data-testid="stats">', $stats);
        self::assertStringNotContainsString('nowo-ui-stat__hint', $stats);
    }

    public function testContractHeaderPassesTheSplitToolbarAndTheStatusBadgeToHostOverrides(): void
    {
        $override = '{{ status_badge|raw }}|{{ toolbar|raw }}';
        $twig = PageContractTwig::create(['host_header.html.twig' => $override], ['partials' => ['page_header' => 'host_header.html.twig']], breadcrumbKit: false);
        $header = new PageHeader('Order', null, [Action::edit('/e')->asIcon(), Action::create('/n', 'New')], status: new PageStatus('Draft', 'warning'), actionsLayout: 'split');

        $html = $twig->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'bundle' => null, 'extra_actions' => [new Action('cancel', 'Back', '/back')]]);
        self::assertStringStartsWith('<span class="nowo-ui-page-status nowo-ui-badge nowo-ui-badge--warning', $html);
        self::assertStringContainsString('|<div class="nowo-ui-header-actions" data-nowo-ui-header-actions>', $html);
        self::assertStringContainsString('<li><a class="nowo-ui-menu__item" href="/back">Back</a></li>', $html, 'extra actions join the split');

        $plain = $twig->render('@NowoUiKit/page/_page_header.html.twig', ['header' => new PageHeader('Order'), 'bundle' => null]);
        self::assertSame('|', trim($plain));
    }

    public function testHeaderActionsPartialWithExtraMarkupAndAPrebuiltSplit(): void
    {
        $twig = PageContractTwig::create(['extra.html.twig' => "{% set extra %}<button type=\"button\" class=\"watch\">Watch</button>{% endset %}{{ include('@NowoUiKitBundle/partials/_header_actions.html.twig', {split: split, extra: extra, more_label: 'Order actions', more_id: 'order-more', more_attr: {'data-testid': 'more'}, attr: {'data-testid': 'order-actions'}}, false) }}"]);
        $split = new HeaderActions(null, [Action::edit('/e')->asIcon()], [new Action('export', 'Export', '/x.csv')]);

        $html = $twig->render('extra.html.twig', ['split' => $split]);
        self::assertStringContainsString('data-nowo-ui-header-actions data-testid="order-actions">', $html);
        self::assertMatchesRegularExpression('#nowo-ui-action--edit.*<button type="button" class="watch">Watch</button>.*id="order-more"#s', $html);
        self::assertStringContainsString('popovertarget="order-more-panel"', $html);
        self::assertStringContainsString('id="order-more" data-nowo-ui-header-more data-testid="more">', $html);
        self::assertStringContainsString('aria-label="Order actions" title="Order actions"', $html);
        self::assertStringNotContainsString('<dialog', $html);

        $none = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_header_actions.html.twig', ['actions' => [new Action('view', 'Hidden', '/h', visible: false)]]);
        self::assertStringNotContainsString('data-nowo-ui-header-more', $none);
    }

    public function testMenuSquareToggleAndItemsInTheDetailsVariant(): void
    {
        $twig = PageContractTwig::create(framework: 'tailwind');
        $html = $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', [
            'items' => [new Action('restore', 'Reopen', '/r', method: 'POST', csrfToken: 't', display: 'icon'), new Action('share', 'Share', variant: 'danger', dialogId: 'share-dialog')],
            'icon' => 'more', 'display' => 'icon', 'square' => true, 'menu_items' => true,
        ]);
        self::assertStringContainsString('<summary class="nowo-ui-btn nowo-ui-btn--secondary inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border nowo-ui-btn--icon nowo-ui-menu__toggle--icon nowo-ui-menu__toggle--square" aria-label="More actions" title="More actions">', $html);
        self::assertStringContainsString('<button type="submit" class="nowo-ui-menu__item nowo-ui-action--restore"><span class="nowo-ui-action__label">Reopen</span></button>', $html, 'an icon-only action is drawn as a text item');
        self::assertStringContainsString('<button type="button" class="nowo-ui-menu__item nowo-ui-menu__item--danger nowo-ui-action--share" data-nowo-ui-dialog-open="share-dialog"', $html);

        $text = $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => [], 'icon' => 'more', 'square' => true]);
        self::assertStringNotContainsString('nowo-ui-menu__toggle--square', $text, 'square applies to icon-only toggles');
        self::assertStringContainsString('<i class="bi bi-three-dots-vertical" aria-hidden="true"></i>', $text);
    }
}
