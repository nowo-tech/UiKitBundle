<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.30 list-filter host options (`theme.pages.list.filters`) and their per-call counterparts on ListPage: fold
 * toggle, default display, button classes / icons, grid mode. The defaults are covered by ListFilters230BcTest.
 */
final class ListFiltersHostOptionsTest extends TestCase
{
    private const BEACON = [
        'display' => 'icon',
        'submit_icon' => 'filter',
        'clear_icon' => 'filter_off',
        'submit_class' => 'btn-primary btn-icon issue-filters__submit',
        'clear_class' => 'btn-ghost btn-icon issue-filters__clear',
        'grid' => 'repeat(auto-fill, minmax(min(100%, 13rem), 1fr))',
        'fold' => ['enabled' => true, 'cookie' => 'beacon_filters_collapsed', 'cookie_path' => '/app', 'icons' => ['expanded' => 'chevron_up', 'collapsed' => 'chevron_down'], 'testids' => true, 'morph' => false],
    ];

    public function testBeaconLikeHostOptions(): void
    {
        $html = $this->render(new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], $this->form(), filtersClearHref: '/orders'), self::BEACON);

        // the form gets an id for aria-controls; no grid class (the track list is the token)
        self::assertStringContainsString('<form name="filters" method="get" class="nowo-ui-filters nowo-ui-search d-grid gap-3 mb-3" role="search" data-nowo-ui-filters="" data-nowo-ui-region="filters" id="nowo-ui-filters-filters">', $html);
        self::assertStringContainsString('--nowo-ui-filters-grid-columns: repeat(auto-fill, minmax(min(100%, 13rem), 1fr));', $html);
        // fold toggle: first child of the form, cookie + path on the peer, accessible button
        self::assertMatchesRegularExpression('#id="nowo-ui-filters-filters">\s*<div class="nowo-ui-filters-collapse" data-controller="filters-collapse"\s+data-filters-collapse-key-value="orders" data-filters-collapse-cookie-value="beacon_filters_collapsed" data-filters-collapse-cookie-path-value="/app"#', $html);
        self::assertMatchesRegularExpression('#<button type="button" class="nowo-ui-filters-collapse__toggle [^"]*"[^>]* aria-expanded="true" aria-controls="nowo-ui-filters-filters" aria-label="Hide filters" title="Hide filters" data-testid="filters-collapse-toggle">#', $html);
        self::assertStringContainsString('data-testid="filters-collapse-summary" hidden>', $html);
        // icon-only apply / clear with the host classes and translated accessible names
        self::assertMatchesRegularExpression('#<button type="submit" class="btn-primary btn-icon issue-filters__submit nowo-ui-filters__submit nowo-ui-btn--icon" title="Apply" aria-label="Apply"><i class="bi bi-funnel[^"]*"[^>]*></i>\s*<span class="visually-hidden">Apply</span></button>#', $html);
        self::assertMatchesRegularExpression('#<a class="btn-ghost btn-icon issue-filters__clear nowo-ui-btn--icon" href="/orders" title="Clear" aria-label="Clear"><i class="bi bi-x-circle[^"]*"[^>]*></i>\s*<span class="visually-hidden">Clear</span></a>#', $html);
    }

    public function testFoldedStateComesFromTheCookieAndThePageKey(): void
    {
        $request = $this->request(['beacon_filters_collapsed' => 'other,orders.list']);
        $page = new ListPage(new PageHeader('Orders'), filters: $this->form(), filtersFoldKey: 'orders.list');
        $html = PageContractTwig::create([], ['pages' => ['list' => ['filters' => self::BEACON]]], request: $request)->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => $page]);

        self::assertStringContainsString('<div class="nowo-ui-filters-collapse is-collapsed" data-controller="filters-collapse"', $html);
        self::assertStringContainsString('data-filters-collapse-key-value="orders.list"', $html);
        self::assertStringContainsString('aria-expanded="false" aria-controls="nowo-ui-filters-filters" aria-label="Show filters"', $html);
        self::assertStringContainsString('data-testid="filters-collapse-summary">1 active filter</p>', $html);
    }

    public function testPerCallOptionsWinOverTheHost(): void
    {
        // filtersFold false, filtersGrid auto-fit over a host track list, filtersDisplay text over the host icon
        $page = new ListPage(new PageHeader('Orders'), filters: $this->form(), filtersClearHref: '/orders', filtersDisplay: 'text', filtersFold: false, filtersGrid: 'auto-fit');
        $html = $this->render($page, self::BEACON, partial: true);
        self::assertStringNotContainsString('nowo-ui-filters-collapse', $html);
        self::assertStringNotContainsString(' id="nowo-ui-filters-', $html);
        self::assertStringContainsString('class="nowo-ui-filters nowo-ui-search d-grid gap-3 mb-3 nowo-ui-filters--grid-auto-fit"', $html);
        self::assertStringContainsString('<button type="submit" class="btn-primary btn-icon issue-filters__submit nowo-ui-filters__submit">Apply</button>', $html);
        self::assertStringContainsString('<a class="btn-ghost btn-icon issue-filters__clear" href="/orders">Clear</a>', $html);

        // fold per call without host options, the form keeps a host id
        $page = new ListPage(new PageHeader('Orders'), filters: $this->form(), filtersFold: true, filtersGrid: 'auto-fill');
        $html = PageContractTwig::create([], ['attributes' => ['filters' => ['id' => 'app-filters']]], request: $this->request())->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => $page]);
        self::assertStringContainsString('class="nowo-ui-filters nowo-ui-search d-grid gap-3 mb-3 nowo-ui-filters--grid-auto-fill" role="search" data-nowo-ui-filters="" data-nowo-ui-region="filters" id="app-filters">', $html);
        self::assertStringContainsString('<div class="nowo-ui-filters-collapse" data-controller="filters-collapse"', $html);
        self::assertStringContainsString('data-filters-collapse-key-value="orders"', $html);
        self::assertStringNotContainsString('data-filters-collapse-cookie-value', $html);
        self::assertStringNotContainsString('data-filters-collapse-cookie-path-value', $html);
        self::assertStringContainsString('aria-controls="app-filters"', $html);
        self::assertStringNotContainsString('data-testid="filters-collapse-toggle"', $html);
    }

    public function testDisplayDefaultIconTextAndActionClear(): void
    {
        $theme = ['display' => 'icon_text', 'clear_class' => 'app-clear', 'submit_icon' => 'save'];
        $page = new ListPage(
            new PageHeader('Orders'),
            filters: $this->form(),
            filtersClear: new Action('close', 'Reset', '/orders'),
            filterActions: [new Action('export', 'CSV', '/orders.csv', icon: 'export')],
        );
        $html = $this->render($page, $theme, partial: true);
        self::assertMatchesRegularExpression('#<button type="submit" class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-ui-filters__submit"><i class="bi bi-check-lg[^"]*"[^>]*></i>\s*<span class="nowo-ui-action__label">Apply</span></button>#', $html);
        self::assertMatchesRegularExpression('#<a class="app-clear nowo-ui-action--close" href="/orders">\s*<i class="bi bi-x-lg[^"]*"[^>]*></i>\s*<span class="nowo-ui-action__label">Reset</span></a>#', $html);
        self::assertMatchesRegularExpression('#nowo-ui-action--export" href="/orders.csv">\s*<i class="bi bi-download#', $html);

        // the plain clear link with icon_text: the icon + a visible label
        $html = $this->render(new ListPage(new PageHeader('Orders'), filters: $this->form(), filtersClearHref: '/orders'), ['display' => 'icon_text'], partial: true);
        self::assertMatchesRegularExpression('#<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" href="/orders"><i class="bi bi-x-circle[^"]*"[^>]*></i>\s*<span class="nowo-ui-action__label">Clear</span></a>#', $html);

        // icon set none: text only
        $twig = PageContractTwig::create([], ['pages' => ['list' => ['filters' => ['display' => 'icon']]]], request: $this->request());
        $twig->addGlobal('nowo_ui_kit_icon_set', 'none');
        $html = $twig->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form(), filtersClearHref: '/orders')]);
        self::assertStringContainsString('nowo-ui-filters__submit">Apply</button>', $html);
        self::assertStringContainsString('href="/orders">Clear</a>', $html);
    }

    /**
     * @param array<string, mixed> $filters theme.pages.list.filters
     */
    private function render(ListPage $page, array $filters, bool $partial = false): string
    {
        $twig = PageContractTwig::create([], ['pages' => ['list' => ['filters' => $filters]]], request: $this->request(), breadcrumbKit: false);

        return $partial
            ? $twig->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => $page])
            : $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
    }

    /**
     * @param array<string, string> $cookies
     */
    private function request(array $cookies = []): Request
    {
        $request = Request::create('https://app.example/orders', 'GET', [], $cookies);
        $request->attributes->set('_route', 'orders');
        $request->attributes->set('_route_params', []);

        return $request;
    }

    private function form(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, ['q' => 'abc'], ['method' => 'GET'])
            ->add('q', SearchType::class, ['required' => false])
            ->add('status', ChoiceType::class, ['required' => false, 'choices' => ['Open' => 'open']])
            ->getForm()->createView();
    }
}
