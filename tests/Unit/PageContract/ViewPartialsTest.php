<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * 2.0 inputs of the upgraded partials (view models + theme slot hooks).
 */
final class ViewPartialsTest extends TestCase
{
    public function testPageHeaderWithViewModel(): void
    {
        $header = new PageHeader(new Label('pagination.nav', 'NowoUiKitBundle'), new Label('empty.default', 'NowoUiKitBundle'), [Action::create('/new'), Action::route('export', 'items_export', ['f' => 'csv'])], [new Crumb('Home', '/'), new Crumb(new Label('tabs.nav', 'NowoUiKitBundle'), '/s'), new Crumb('Here', '/here')]);
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_page_header.html.twig' with {header: header, toolbar: '<i>t</i>', bundle: 'NowoXBundle'} only %}", ['header' => $header], [
            'classes' => ['page_title' => 'app-title', 'page_intro' => 'app-intro', 'toolbar' => 'app-toolbar'],
            'attributes' => ['page_header' => ['data-x' => 'h']],
        ]);

        self::assertStringContainsString('class="nowo-ui-page-header d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3" data-x="h"', $html);
        self::assertStringContainsString('<h1 class="nowo-ui-page-header__title h3 mb-1 app-title">Pagination</h1>', $html);
        self::assertStringContainsString('<p class="nowo-ui-page-header__intro app-intro">No items found.</p>', $html);
        self::assertStringContainsString('<a class="nowo-ui-breadcrumbs__link" href="/s">Sections</a>', $html);
        self::assertStringContainsString('<span class="nowo-ui-breadcrumbs__current" aria-current="page">Here</span>', $html);
        self::assertStringContainsString('class="nowo-ui-toolbar d-flex flex-wrap align-items-center gap-1 mb-2 app-toolbar"', $html);
        self::assertStringContainsString('href="/items_export?f=csv"><span class="nowo-ui-action__label">Export</span></a>', $html);
        self::assertStringContainsString('<i>t</i>', $html);

        $plain = $this->render("{% include '@NowoUiKitBundle/partials/_page_header.html.twig' with {header: header} only %}", ['header' => new PageHeader('Plain')]);
        self::assertStringNotContainsString('nowo-ui-breadcrumbs', $plain);
        self::assertStringNotContainsString('nowo-ui-toolbar', $plain);
        self::assertStringNotContainsString('__intro', $plain);
    }

    public function testBreadcrumbKitIsUsedWhenInstalled(): void
    {
        $twig = PageContractTwig::create(['t' => "{% include '@NowoUiKitBundle/partials/_page_header.html.twig' with {header: header} only %}"]);
        $twig->addFunction(new TwigFunction('breadcrumb_render', static fn (Environment $env, ?string $c = null): string => '<ol data-kit="'.$c.'"></ol>', ['is_safe' => ['html'], 'needs_environment' => true]));
        $html = $twig->render('t', ['header' => new PageHeader('T', breadcrumbs: [new Crumb('Ignored')], breadcrumbCollection: 'admin')]);

        self::assertStringContainsString('<div class="nowo-ui-breadcrumbs" data-nowo-ui-breadcrumbs="kit"><ol data-kit="admin"></ol></div>', $html);
        self::assertStringNotContainsString('Ignored', $html);

        $off = PageContractTwig::create(['t' => "{% include '@NowoUiKitBundle/partials/_page_header.html.twig' with {header: header} only %}"], breadcrumbKit: false);
        $off->addFunction(new TwigFunction('breadcrumb_render', static fn (): string => 'KIT'));
        self::assertStringContainsString('Ignored', $off->render('t', ['header' => new PageHeader('T', breadcrumbs: [new Crumb('Ignored')])]));
    }

    public function testPaginationWithViewModelAndSlotHooks(): void
    {
        $pagination = new Pagination(2, 10, 35, route: 'logs', query: ['level' => 'error'], routeParams: ['org' => 1], pageParam: 'p', perPageParam: 'size');
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_pagination.html.twig' with {pagination: pagination, bundle: 'NowoXBundle'} only %}", ['pagination' => $pagination], [
            'classes' => ['pagination' => 'app-pager', 'pagination_link' => 'app-link', 'pagination_active' => 'app-active', 'pagination_info' => 'app-info'],
            'attributes' => ['pagination' => ['data-controller' => 'pager']],
        ]);

        self::assertStringContainsString('class="nowo-ui-pagination d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 app-pager" data-controller="pager"', $html);
        self::assertStringContainsString('<p class="nowo-ui-pagination__info app-info">', $html);
        self::assertStringContainsString('Showing 11–20 of 35', $html);
        self::assertStringContainsString('<a class="page-link nowo-ui-pagination__link app-link" href="/logs?org=1&amp;level=error&amp;size=10&amp;p=1"', $html);
        self::assertStringContainsString('<span class="page-link nowo-ui-pagination__link is-active app-active">2</span>', $html);

        $last = $this->render("{% include '@NowoUiKitBundle/partials/_pagination.html.twig' with {pagination: pagination} only %}", ['pagination' => new Pagination(4, 10, 35)]);
        self::assertStringContainsString('Showing 31–35 of 35', $last);
        self::assertStringContainsString('href="/items_index?per_page=10&amp;page=3"', $last);
    }

    public function testToastsAndFlashesWithThemeAndTranslation(): void
    {
        $theme = [
            'classes' => ['toasts' => 'app-toasts', 'flash_success' => 'app-ok', 'flashes' => 'app-flashes'],
            'attributes' => ['toasts' => ['data-controller' => 'toast-stack'], 'flashes' => ['data-x' => '1']],
        ];
        $flashes = ['success' => ['pagination.nav'], 'info' => [['title' => 'tabs.nav', 'message' => 'empty.default']], 'custom' => ['Raw']];
        $toasts = $this->render("{% include '@NowoUiKitBundle/partials/_toasts.html.twig' with {flashes: flashes, trans_domain: 'NowoUiKitBundle'} only %}", ['flashes' => $flashes], $theme);

        self::assertStringContainsString('class="nowo-ui-toast-stack app-toasts"', $toasts);
        self::assertMatchesRegularExpression('/aria-relevant="additions" data-controller="toast-stack"\s*>/', $toasts);
        self::assertStringContainsString('nowo-ui-flash--success alert alert-success app-ok"', $toasts);
        self::assertStringContainsString('<p class="nowo-ui-toast__message">Pagination</p>', $toasts);
        self::assertStringContainsString('<p class="nowo-ui-toast__title">Sections</p>', $toasts);
        self::assertStringContainsString('<p class="nowo-ui-toast__message">No items found.</p>', $toasts);

        $flashHtml = $this->render("{% include '@NowoUiKitBundle/partials/_flashes.html.twig' with {flashes: flashes, trans_domain: 'NowoUiKitBundle'} only %}", ['flashes' => $flashes], $theme);
        self::assertStringContainsString('<div class="nowo-ui-flashes app-flashes" data-x="1">', $flashHtml);
        self::assertStringContainsString('alert alert-success app-ok" role="alert">Pagination</div>', $flashHtml);
        self::assertStringContainsString('role="alert">Sections — No items found.</div>', $flashHtml);
        self::assertStringContainsString('nowo-ui-flash--custom alert alert-custom" role="alert">Raw</div>', $flashHtml);

        $untranslated = $this->render("{% include '@NowoUiKitBundle/partials/_flashes.html.twig' with {flashes: {info: [{title: 'T', message: 'M'}]}} only %}");
        self::assertStringContainsString('role="alert">T — M</div>', $untranslated);
        self::assertStringNotContainsString('nowo-ui-flashes', $untranslated);
    }

    public function testRowActionsWithViewModels(): void
    {
        $csrf = PageContractTwig::forms()->createNamedBuilder('del', FormType::class)->getForm()->createView();
        $actions = [
            Action::edit('/e'),
            Action::route('view', 'items_show', ['id' => 3]),
            new Action('config', 'Settings', attributes: ['data-x' => '1', 'data-flag' => true, 'data-off' => false], modalId: 'm1'),
            new Action('restore', href: '/r', method: 'PATCH', csrfToken: 't'),
            new Action('delete', href: '/d', method: 'POST', form: $csrf),
            new Action('copy', href: '/c', icon: 'clipboard', disabled: true),
            new Action('share', href: '/s', visible: false),
            ['kind' => 'export', 'href' => '/x'],
        ];
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_row_actions.html.twig' with {actions: actions, display: 'icon_text', bundle: 'NowoXBundle'} only %}", ['actions' => $actions], [
            'classes' => ['row_actions' => 'app-row', 'action' => 'app-action'],
            'attributes' => ['row_actions' => ['data-row' => '1']],
        ]);

        self::assertStringContainsString('class="nowo-ui-row-actions d-flex gap-1 nowo-ui-row-actions--icon-text app-row" data-row="1"', $html);
        self::assertStringContainsString('class="nowo-ui-action nowo-ui-action--edit nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--sm btn btn-outline-secondary btn-sm app-action" href="/e"', $html);
        self::assertStringContainsString('href="/items_show?id=3"', $html);
        self::assertStringContainsString('data-x="1" data-flag title="Settings" aria-label="Settings" data-bs-toggle="modal" data-bs-target="#m1"', $html);
        self::assertStringNotContainsString('data-off', $html);
        self::assertStringContainsString('<input type="hidden" name="_method" value="PATCH">', $html);
        self::assertStringContainsString('<input type="hidden" name="_token" value="t">', $html);
        self::assertMatchesRegularExpression('#<form name="del" method="post" action="/d" class="nowo-ui-action__form">\s*<button type="submit" class="nowo-ui-action nowo-ui-action--delete#', $html);
        self::assertStringContainsString('is-disabled" href="/c" title="Copy" aria-label="Copy" aria-disabled="true" tabindex="-1">', $html);
        self::assertStringContainsString('bi bi-clipboard', $html);
        self::assertStringNotContainsString('/s"', $html);
        self::assertStringContainsString('nowo-ui-action--export', $html);
        self::assertStringContainsString('<span class="nowo-ui-action__label">Edit</span>', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/', $html));
    }

    public function testToolbarActionVariantsAndDisabledStates(): void
    {
        $html = $this->render("{% for a in actions %}{% include '@NowoUiKitBundle/partials/_action.html.twig' with {action: a} only %}{% endfor %}", ['actions' => [
            new Action('custom', 'Modal', variant: 'outline', modalId: 'm', disabled: true),
            new Action('delete', 'Gone', '/d', method: 'POST', csrfToken: 't', attributes: ['data-y' => '1', 'data-z' => true], disabled: true),
            new Action('delete', 'Ask', '/a', method: 'POST', confirm: new Confirm('Sure?', id: 'ask'), attributes: ['data-q' => true], disabled: true),
            new Action('edit', 'Off', '/o', attributes: ['data-b' => true], disabled: true),
        ]], ['classes' => ['btn' => 'app-btn']]);

        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--outline btn btn-outline-primary app-btn nowo-ui-action--custom" disabled data-bs-toggle="modal" data-bs-target="#m">', $html);
        self::assertStringContainsString('<button type="submit" class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger nowo-ui-action--delete" data-y="1" data-z disabled>', $html);
        self::assertStringContainsString('data-q disabled data-nowo-confirm-open data-nowo-confirm-target="ask"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--edit is-disabled" href="/o" data-b aria-disabled="true"', $html);
    }

    public function testConfirmAndEmptyWithViewModels(): void
    {
        $confirm = $this->render("{% include '@NowoUiKitBundle/partials/_confirm.html.twig' with {confirm: confirm, action: action, bundle: 'NowoXBundle'} only %}", [
            'confirm' => new Confirm(new Label('confirm.delete_title', 'NowoUiKitBundle'), null, null, 'primary', new Label('action.back', 'NowoUiKitBundle'), 'c9'),
            'action' => new Action('custom', 'Run', '/run', method: 'POST'),
        ], ['classes' => ['confirm' => 'app-dialog'], 'attributes' => ['confirm' => ['data-d' => '1']]]);

        self::assertStringContainsString('id="c9"', $confirm);
        self::assertMatchesRegularExpression('/class="nowo-ui-confirm app-dialog"\s+data-nowo-ui-confirm\s+aria-labelledby="c9-title" data-d="1"\s*>/', $confirm);
        self::assertStringContainsString('>Delete this item?</h2>', $confirm);
        self::assertStringNotContainsString('nowo-ui-confirm__body', $confirm);
        self::assertMatchesRegularExpression('/data-nowo-confirm-close>\s*Back\s*</', $confirm);
        self::assertStringContainsString('<form method="post" action="/run" class="nowo-ui-action__form">', $confirm);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-ui-confirm__submit">Confirm</button>', $confirm);

        $plain = $this->render("{% include '@NowoUiKitBundle/partials/_confirm.html.twig' with {confirm: confirm, id: 'x'} only %}", ['confirm' => new Confirm('Plain?', 'Msg', 'Do it')]);
        self::assertStringContainsString('<p class="nowo-ui-confirm__message">Msg</p>', $plain);
        self::assertStringNotContainsString('<form', $plain);

        $empty = $this->render("{% include '@NowoUiKitBundle/partials/_empty.html.twig' with {empty: empty} only %}", ['empty' => new EmptyState(message: 'Nothing yet')], ['classes' => ['empty' => 'app-empty'], 'attributes' => ['empty' => ['data-e' => '1']]]);
        self::assertStringContainsString('<div class="nowo-ui-empty nowo-ui-muted text-muted app-empty" data-e="1">', $empty);
        self::assertStringNotContainsString('__title', $empty);
        self::assertStringContainsString('<p class="nowo-ui-empty__message">Nothing yet</p>', $empty);
        self::assertStringNotContainsString('__action', $empty);

        $legacyWithTheme = $this->render("{% include '@NowoUiKitBundle/partials/_empty.html.twig' only %}", [], ['classes' => ['empty' => 'app-empty']]);
        self::assertStringContainsString('<p class="nowo-ui-empty nowo-ui-muted text-muted app-empty">No items found.</p>', $legacyWithTheme);
    }

    public function testShellAndBurgerHooks(): void
    {
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_shell.html.twig' with opts only %}", ['opts' => [
            'brand' => 'Acme',
            'root_class' => 'kit-admin',
            'header_class' => 'app-header',
            'main_class' => 'app-main',
            'attr' => ['data-controller' => 'shell'],
            'sidebar_rail' => true,
            'burger_root_class' => 'app-burger',
            'burger_label_attr_names' => ['expand' => 'data-expand-label'],
            'burger_attr' => ['data-b' => '1'],
            'show_user_menu' => false,
        ]], ['classes' => ['shell' => 'app-shell', 'burger' => 'theme-burger'], 'attributes' => ['shell' => ['data-s' => '1']]]);

        self::assertStringContainsString('class="nowo-ui-shell app-shell kit-admin"', $html);
        self::assertMatchesRegularExpression('/data-nowo-ui-sidebar-rail\s+data-s="1" data-controller="shell"\s*>/', $html);
        self::assertStringContainsString('class="nowo-ui-header d-flex align-items-center justify-content-between gap-2 app-header"', $html);
        self::assertStringContainsString('class="nowo-ui-main app-main"', $html);
        self::assertStringContainsString('btn-sm theme-burger app-burger"', $html);
        self::assertStringContainsString('data-expand-label="Expand navigation"', $html);
        self::assertStringContainsString('data-label-rail="Show icons only"', $html);
        self::assertStringContainsString(' data-b="1"', $html);

        $override = PageContractTwig::create(['t' => "{% include '@NowoUiKitBundle/partials/_shell.html.twig' with {show_user_menu: false} only %}", 'kit/_burger.html.twig' => '<button data-kit-burger></button>'], ['partials' => ['burger' => 'kit/_burger.html.twig']]);
        self::assertStringContainsString('<button data-kit-burger></button>', $override->render('t'));

        $bare = $this->bare("{% include '@NowoUiKitBundle/partials/_burger.html.twig' with {attr: {'data-b': 'x'}, root_class: 'r'} only %}");
        self::assertStringContainsString('btn-sm r"', $bare);
        self::assertStringContainsString(' data-b="x"', $bare);
        $bareShell = $this->bare("{% include '@NowoUiKitBundle/partials/_shell.html.twig' with {attr: {'data-s': 'y'}, show_user_menu: false} only %}");
        self::assertStringContainsString(' data-s="y"', $bareShell);
    }

    public function testCardReadsThemeSlotsAndViewModels(): void
    {
        $theme = [
            'classes' => ['card' => 'app-card', 'card_header' => 'app-card__header', 'card_body' => 'app-card__body', 'card_footer' => 'app-card__footer', 'cards' => 'app-grid'],
            'attributes' => ['card' => ['data-card' => 'x'], 'cards' => ['data-grid' => true]],
        ];
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_card.html.twig' with {title: title, intro: intro, actions: actions, toolbar: '<i>t</i>', body: '<p>b</p>', footer: '<a>f</a>', id: 'stats', attr: {'data-extra': '1'}, bundle: 'NowoXBundle'} only %}", [
            'title' => new Label('pagination.nav', 'NowoUiKitBundle'),
            'intro' => ['key' => 'empty.default', 'domain' => 'NowoUiKitBundle'],
            'actions' => [Action::create('/new')],
        ], $theme);

        self::assertStringContainsString('<section class="nowo-ui-card card app-card" id="stats" data-nowo-ui-card data-card="x" data-extra="1">', $html);
        self::assertStringContainsString('<header class="nowo-ui-card__header card-header d-flex flex-wrap align-items-start justify-content-between gap-2 app-card__header">', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-card__title">Pagination</h2>', $html);
        self::assertStringContainsString('<p class="nowo-ui-card__intro">No items found.</p>', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-card__toolbar">\s*<a class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-ui-action--create" href="/new">.*</a>\s*<i>t</i></div>#s', $html);
        self::assertStringContainsString('<div class="nowo-ui-card__body card-body app-card__body"><p>b</p></div>', $html);
        self::assertStringContainsString('<footer class="nowo-ui-card__footer card-footer app-card__footer"><a>f</a></footer>', $html);

        // the per-slot switch drops Bootstrap's card classes, the semantic ones stay
        $off = $this->render("{% include '@NowoUiKitBundle/partials/_card.html.twig' with {title: 'T', body: 'b', footer: 'f'} only %}", [], [
            'classes' => ['card' => ['value' => 'acme-card', 'framework_defaults' => false], 'card_header' => ['framework_defaults' => false]],
        ]);
        self::assertStringContainsString('<section class="nowo-ui-card acme-card" data-nowo-ui-card>', $off);
        self::assertStringContainsString('<header class="nowo-ui-card__header">', $off);
        self::assertStringContainsString('<div class="nowo-ui-card__body card-body">b</div>', $off);

        // cards grid: every card through the card partial, inheriting the bundle
        $grid = $this->render("{% include '@NowoUiKitBundle/partials/_card.html.twig' with {cards: [{title: 'A', body: 'a'}, {title: 'B', body: 'b', framework: 'tailwind'}], bundle: 'NowoXBundle'} only %}", [], $theme);
        self::assertStringContainsString('<div class="nowo-ui-cards app-grid" data-grid>', $grid);
        self::assertSame(2, substr_count($grid, 'data-nowo-ui-card'));
        self::assertStringContainsString('<div class="nowo-ui-card__body px-4 py-3 app-card__body">b</div>', $grid);

        $bare = $this->bare("{% include '@NowoUiKitBundle/partials/_card.html.twig' with {cards: [{body: 'x', attr: {'data-a': '1'}}]} only %}");
        self::assertStringContainsString('<div class="nowo-ui-cards">', $bare);
        self::assertStringContainsString('data-nowo-ui-card data-a="1">', $bare);
    }

    public function testLegacyPartialsDropFrameworkDefaultsWhenTheThemeSaysSo(): void
    {
        $theme = ['framework_defaults' => false, 'classes' => ['flash_success' => 'app-flash']];
        $flashes = $this->render("{% include '@NowoUiKitBundle/partials/_flashes.html.twig' with {flashes: {success: ['Saved'], notice: ['n'], custom: ['c']}} only %}", [], $theme);
        self::assertStringContainsString('<div class="nowo-ui-flash nowo-ui-flash--success app-flash" role="alert">Saved</div>', $flashes);
        self::assertStringContainsString('<div class="nowo-ui-flash nowo-ui-flash--info" role="alert">n</div>', $flashes);
        // flash types without a slot keep the macro classes
        self::assertStringContainsString('alert alert-custom', $flashes);

        $toasts = $this->render("{% include '@NowoUiKitBundle/partials/_toasts.html.twig' with {flashes: {success: ['Saved']}} only %}", [], $theme);
        self::assertStringContainsString('class="nowo-ui-toast nowo-ui-flash nowo-ui-flash--success app-flash"', $toasts);

        $empty = $this->render("{% include '@NowoUiKitBundle/partials/_empty.html.twig' only %}", [], $theme);
        self::assertStringContainsString('<p class="nowo-ui-empty nowo-ui-muted">', $empty);

        $header = $this->render("{% include '@NowoUiKitBundle/partials/_page_header.html.twig' with {header: header} only %}", ['header' => new PageHeader('T', actions: [Action::create('/n'), new Action('custom', 'Go', '/go', variant: 'outline')])], $theme);
        self::assertStringContainsString('<div class="nowo-ui-page-header">', $header);
        self::assertStringContainsString('<div class="nowo-ui-toolbar">', $header);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary nowo-ui-action--create"', $header);
        self::assertStringNotContainsString('btn-link', $header);
        self::assertStringNotContainsString('btn-outline', $header);

        $rows = $this->render("{% include '@NowoUiKitBundle/partials/_row_actions.html.twig' with {actions: actions} only %}", ['actions' => [Action::edit('/e'), ['kind' => 'view', 'href' => '/v']]], $theme);
        self::assertStringContainsString('<div class="nowo-ui-row-actions nowo-ui-row-actions--icon">', $rows);
        self::assertStringNotContainsString('btn-sm', $rows);

        $pager = $this->render("{% include '@NowoUiKitBundle/partials/_pagination.html.twig' with {pagination: pagination} only %}", ['pagination' => new Pagination(1, 10, 35)], $theme);
        self::assertStringContainsString('<div class="nowo-ui-pagination">', $pager);
        self::assertStringContainsString('<span class="nowo-ui-pagination__link is-disabled"', $pager);
        self::assertStringContainsString('<span class="nowo-ui-pagination__link is-active">1</span>', $pager);
        self::assertStringContainsString('<a class="nowo-ui-pagination__link" href=', $pager);

        $burger = $this->render("{% include '@NowoUiKitBundle/partials/_burger.html.twig' with {root_class: 'app-burger'} only %}", [], $theme);
        self::assertStringContainsString('class="nowo-ui-burger nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--sm app-burger"', $burger);
    }

    /**
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $theme
     */
    private function render(string $template, array $vars = [], array $theme = []): string
    {
        return PageContractTwig::create(['t' => $template], $theme)->render('t', $vars);
    }

    private function bare(string $template): string
    {
        $fs = new \Twig\Loader\FilesystemLoader();
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new \Twig\Loader\ChainLoader([new \Twig\Loader\ArrayLoader(['t' => $template]), $fs]));
        $twig->addExtension(new \Symfony\Bridge\Twig\Extension\TranslationExtension(new \Symfony\Component\Translation\IdentityTranslator()));
        $twig->addGlobal('nowo_ui_kit_css_framework', 'bootstrap5');

        return $twig->render('t');
    }
}
