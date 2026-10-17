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
 * List primary-action placement (REQ-UI-003 round 3): `ListPage::headerActionsInFilters` and
 * `theme.pages.list.header_actions_in_filters` move the page-header actions to the end of the filter row.
 */
final class ListHeaderActionsPlacementTest extends TestCase
{
    public function testHeaderActionsMoveToTheFilterRow(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page(true)]);
        $header = $this->region($html, 'header');
        $filters = $this->region($html, 'filters');

        self::assertStringNotContainsString('nowo-ui-action--create', $header);
        self::assertStringNotContainsString('nowo-ui-toolbar', $header);
        self::assertMatchesRegularExpression('#nowo-ui-filters__submit">Apply</button>\s*<div class="nowo-ui-filters__header-actions" data-nowo-ui-region="header-actions">\s*<a class="[^"]*nowo-ui-action--create" href="/orders/new">#', $filters);
        self::assertSame(1, substr_count($html, 'nowo-ui-action--create'));

        // a host page_header override that falls back to header.actions (actions|default(header.actions)) does not
        // print them twice: the header it receives has no actions
        $override = ['host/_header.html.twig' => '<header data-host>{% for a in actions|default(header.actions) %}[{{ a.kind }}]{% endfor %}</header>'];
        $html = PageContractTwig::create($override, ['partials' => ['page_header' => 'host/_header.html.twig']])->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page(true)]);
        self::assertStringContainsString('<header data-host></header>', $html);
        self::assertSame(1, substr_count($html, 'nowo-ui-action--create'));
    }

    public function testThemeOptionAndPrecedence(): void
    {
        $theme = ['pages' => ['list' => ['header_actions_in_filters' => true]]];
        $html = PageContractTwig::create([], $theme)->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page(null)]);
        self::assertStringContainsString('nowo-ui-action--create', $this->region($html, 'filters'));
        self::assertStringNotContainsString('nowo-ui-action--create', $this->region($html, 'header'));

        // the page model wins over the theme option
        $html = PageContractTwig::create([], $theme)->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page(false)]);
        self::assertStringContainsString('nowo-ui-action--create', $this->region($html, 'header'));
        self::assertStringNotContainsString('nowo-ui-filters__header-actions', $html);

        // without filters the actions stay in the header
        $page = new ListPage(new PageHeader('Orders', actions: [Action::create('/orders/new')]), headerActionsInFilters: true);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertStringContainsString('nowo-ui-action--create', $this->region($html, 'header'));

        // default: header
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $this->page(null)]);
        self::assertStringContainsString('nowo-ui-action--create', $this->region($html, 'header'));
        self::assertStringNotContainsString('nowo-ui-filters__header-actions', $html);
    }

    private function page(?bool $inFilters): ListPage
    {
        return new ListPage(
            new PageHeader('Orders', actions: [Action::create('/orders/new', 'New order')]),
            [new Column('name')],
            [['name' => 'A']],
            $this->filters(),
            headerActionsInFilters: $inFilters,
        );
    }

    private function region(string $html, string $region): string
    {
        $start = (int) strpos($html, 'data-nowo-ui-region="'.$region.'"');

        return substr($html, $start, 'filters' === $region ? (int) strpos($html, '</form>', $start) - $start : 1200);
    }

    private function filters(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, null, ['method' => 'GET'])->add('q', SearchType::class, ['required' => false])->getForm()->createView();
    }
}
