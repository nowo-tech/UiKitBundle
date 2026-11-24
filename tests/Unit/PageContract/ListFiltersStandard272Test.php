<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\View\FilterField;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.72 Beacon filter standard (UI-PATTERNS §8) in page/_list_filters.html.twig: "More filters" fold (FilterField more /
 * ListPage filtersMore), compact pager field in the actions group (FilterField pager), icon-only actions at field
 * height, default labels "Filter" / "Clear filters", host select classes kept, and the phone sheet's icon close button
 * with light dismiss.
 */
final class ListFiltersStandard272Test extends TestCase
{
    public function testFilterFieldCannotBeMoreAndPager(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be both "more" and "pager"');
        new FilterField('per_page', more: true, pager: true);
    }

    public function testListPageMergesFiltersMoreWithFieldOptions(): void
    {
        $page = new ListPage(new PageHeader('Orders'), filterFields: [new FilterField('role', more: true), new FilterField('per_page', pager: true)], filtersMore: ['created', 'role']);
        self::assertSame(['created', 'role'], $page->filtersMore);
        self::assertTrue($page->isFilterMore('role'));
        self::assertTrue($page->isFilterMore('created'));
        self::assertFalse($page->isFilterMore('q'));
        self::assertTrue($page->isFilterPager('per_page'));
        self::assertFalse($page->isFilterPager('role'));
        self::assertFalse($page->isFilterPager('unknown'));
        self::assertNull($page->filtersMoreOpen);
        self::assertSame([], (new ListPage(new PageHeader('Orders')))->filtersMore);
    }

    public function testListPageRejectsInvalidFiltersMore(): void
    {
        foreach ([['a b'], [''], ['x,y']] as $more) {
            try {
                new ListPage(new PageHeader('Orders'), filtersMore: $more);
                self::fail('accepted '.$more[0]);
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('Invalid ListPage filtersMore entry', $e->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is a pager field');
        new ListPage(new PageHeader('Orders'), filterFields: [new FilterField('per_page', pager: true)], filtersMore: ['per_page']);
    }

    public function testMoreFoldRendersAfterTheActionsOpenWhileAHiddenFilterIsSet(): void
    {
        $html = $this->render(['role' => 'admin', 'mine' => true], fields: [new FilterField('role', more: true), new FilterField('mine', more: true)]);
        $c = new Crawler($html);
        $more = $c->filter('form[data-nowo-ui-filters] > .nowo-ui-filters__actions + details.nowo-ui-filters-more.filters-more[data-nowo-ui-filters-more]');
        self::assertCount(1, $more);
        self::assertNotNull($more->attr('open'));
        self::assertSame('More filters', $more->filter('.nowo-ui-filters-more__label')->text());
        self::assertSame('2', $more->filter('[data-nowo-ui-filters-more-count] [aria-hidden="true"]')->text());
        self::assertSame('2 active', $more->filter('[data-nowo-ui-filters-more-count] .visually-hidden')->text());
        self::assertCount(1, $more->filter('summary.nowo-ui-filters-more__summary.filters-more__summary .nowo-ui-filters-more__chevron.filters-more__chevron[aria-hidden="true"]'));
        // the folded rows live in the disclosure only, the others stay on the grid
        self::assertCount(1, $more->filter('.nowo-ui-filters-more__fields.filters-more__fields #filters_role'));
        self::assertCount(1, $more->filter('.nowo-ui-filters-more__fields #filters_mine'));
        self::assertCount(1, $c->filter('form[data-nowo-ui-filters] > .nowo-ui-filters__field #filters_q'));
        self::assertCount(1, $c->filter('#filters_role'));
    }

    public function testMoreFoldStaysClosedWithoutAHiddenFilterAndCanBeForced(): void
    {
        $page = fn (?bool $open): string => $this->render(['q' => 'x'], more: ['role'], moreOpen: $open);
        $closed = new Crawler($page(null));
        self::assertNull($closed->filter('details.nowo-ui-filters-more')->attr('open'));
        self::assertCount(0, $closed->filter('[data-nowo-ui-filters-more-count]'));
        self::assertNotNull((new Crawler($page(true)))->filter('details.nowo-ui-filters-more')->attr('open'));
        self::assertNull((new Crawler($this->render(['role' => 'admin'], more: ['role'], moreOpen: false)))->filter('details.nowo-ui-filters-more')->attr('open'));
    }

    public function testNoMoreFieldsPrintsNoDisclosureAndIconSetNoneDropsTheIcons(): void
    {
        self::assertStringNotContainsString('nowo-ui-filters-more', $this->render(['q' => 'x']));
        $twig = PageContractTwig::create([], [], request: $this->request());
        $twig->addGlobal('nowo_ui_kit_icon_set', 'none');
        $html = $twig->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form(['role' => 'admin']), filtersMore: ['role'])]);
        $summary = (new Crawler($html))->filter('summary.nowo-ui-filters-more__summary');
        self::assertCount(0, $summary->filter('.nowo-ui-filters-more__icon, .nowo-ui-filters-more__chevron'));
        self::assertStringStartsWith('More filters', trim($summary->text()));
    }

    public function testPagerRendersCompactFirstInTheActionsGroup(): void
    {
        $html = $this->render(['per_page' => '25'], fields: [new FilterField('per_page', rowClass: 'issue-filters__pager', pager: true)]);
        $c = new Crawler($html);
        $actions = $c->filter('form[data-nowo-ui-filters] > .nowo-ui-filters__actions.nowo-ui-filter-bar__actions.nowo-ui-filter-bar__actions--pager');
        self::assertCount(1, $actions);
        $first = $actions->children()->first();
        self::assertSame('nowo-ui-filters__field issue-filters__pager nowo-ui-filters__pager', $first->attr('class'));
        self::assertCount(1, $first->filter('select#filters_per_page'));
        self::assertSame('submit', $actions->children()->eq(1)->attr('type'));
        // no pager: the 2.71 actions classes
        self::assertCount(0, (new Crawler($this->render(['q' => 'x'])))->filter('.nowo-ui-filter-bar__actions'));
    }

    public function testHostileRowClassOnMoreAndPagerRowsIsEscaped(): void
    {
        $hostile = 'x" onmouseover="alert(1)" data-y=\'<b>';
        $html = $this->render(['role' => 'admin'], fields: [new FilterField('role', rowClass: $hostile, more: true), new FilterField('per_page', rowClass: $hostile, pager: true)]);
        self::assertStringNotContainsString('onmouseover="', $html);
        self::assertStringNotContainsString("data-y='<b>", $html);
        $c = new Crawler($html);
        self::assertCount(0, $c->filter('[onmouseover], [data-y]'));
        foreach (['.nowo-ui-filters-more__fields', '.nowo-ui-filters__actions'] as $scope) {
            $row = $c->filter($scope.' > div')->first();
            self::assertStringContainsString('onmouseover=&quot;alert(1)&quot;', (string) $row->outerHtml(), $scope);
            self::assertNull($row->attr('onmouseover'), $scope);
        }
    }

    public function testIconOnlyActionsUseTheFilterBarClassAndTheNewLabels(): void
    {
        $twig = PageContractTwig::create([], ['classes' => ['btn_icon' => 'btn-icon'], 'pages' => ['list' => ['filters' => ['display' => 'icon']]]], request: $this->request());
        $html = $twig->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form([]), filtersClearHref: '/orders')]);
        $c = new Crawler($html);
        $submit = $c->filter('button.nowo-ui-filters__submit');
        self::assertStringContainsString('nowo-ui-filter-bar__action--icon-only', (string) $submit->attr('class'));
        self::assertStringNotContainsString('btn-icon', (string) $submit->attr('class'));
        self::assertSame('Filter', $submit->attr('aria-label'));
        $clear = $c->filter('a[href="/orders"]');
        self::assertStringContainsString('nowo-ui-filter-bar__action--icon-only', (string) $clear->attr('class'));
        self::assertStringNotContainsString('btn-icon', (string) $clear->attr('class'));
        self::assertSame('Clear filters', $clear->attr('aria-label'));

        $es = PageContractTwig::create([], [], request: $this->request(), locale: 'es')->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form([]), filtersClearHref: '/orders', filtersMore: ['role'])]);
        self::assertStringContainsString('>Filtrar</button>', $es);
        self::assertStringContainsString('>Limpiar filtros</a>', $es);
        self::assertStringContainsString('Más filtros', $es);
    }

    public function testSelectsKeepTheHostInputClassesOnceAndNoKitPadding(): void
    {
        $twig = PageContractTwig::create([], ['classes' => ['input' => 'input']], 'custom', request: $this->request());
        $html = $twig->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form([]), filterFields: [new FilterField('q', class: 'input app-q'), new FilterField('role', rowClass: 'nowo-ui-filters__field x')])]);
        $c = new Crawler($html);
        self::assertSame('input', $c->filter('#filters_role')->attr('class'));
        self::assertSame('nowo-ui-input input app-q', $c->filter('#filters_q')->attr('class'));
        self::assertSame('nowo-ui-filters__field x', $c->filter('#filters_role')->closest('div')?->attr('class'));
        // without host input classes a select keeps the kit-painted class
        self::assertSame('nowo-ui-input', (new Crawler($this->render([])))->filter('#filters_role')->attr('class'));
        // a checkbox gets no empty class attribute
        self::assertNull((new Crawler($this->render([])))->filter('#filters_mine')->attr('class'));
    }

    public function testSheetHasAnIconCloseButtonAndLightDismiss(): void
    {
        $html = PageContractTwig::create([], ['pages' => ['list' => ['filters' => ['sheet' => true]]]], request: $this->request())
            ->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form([]))]);
        $c = new Crawler($html);
        self::assertSame('any', $c->filter('dialog[data-nowo-ui-filter-sheet-dialog]')->attr('closedby'));
        $close = $c->filter('button[data-nowo-ui-filter-sheet-close]');
        self::assertSame('Close', $close->attr('aria-label'));
        self::assertSame('Close', $close->attr('title'));
        self::assertSame('nowo-ui-btn nowo-ui-btn--icon nowo-ui-filter-sheet__close', $close->attr('class'));
        self::assertCount(1, $close->filter('i.bi'));

        $twig = PageContractTwig::create([], ['pages' => ['list' => ['filters' => ['sheet' => true]]]], request: $this->request());
        $twig->addGlobal('nowo_ui_kit_icon_set', 'none');
        $text = (new Crawler($twig->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form([]))])))->filter('button[data-nowo-ui-filter-sheet-close]');
        self::assertSame('Close', $text->text());
        self::assertNull($text->attr('aria-label'));
    }

    public function testStylesheetCarriesTheStandardRules(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
            self::assertMatchesRegularExpression('/\.nowo-ui-filters__actions \.nowo-ui-filter-bar__action--icon-only \{[^}]*block-size: var\(--nowo-ui-filter-control-height, var\(--nowo-ui-control-height, 2\.375rem\)\);/', $css, $file);
            self::assertMatchesRegularExpression('/form\.nowo-ui-filters\[data-nowo-ui-region\] \.nowo-ui-filters__pager \{\s*flex: 0 0 auto;/', $css, $file);
            self::assertMatchesRegularExpression('/form\.nowo-ui-filters\[data-nowo-ui-region\] > \.nowo-ui-filters-more \{\s*grid-column: 1 \/ -1;/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-filter-sheet__dialog-body form\[data-nowo-ui-filters\] > \.nowo-ui-filters__actions,\s*\.nowo-ui-filter-sheet__dialog-body \.nowo-ui-filter-sheet__actions \{\s*position: sticky;\s*inset-block-end: 0;/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-filter-sheet__dialog-body form\[data-nowo-ui-filters\] \{\s*margin: 0 !important;\s*padding: 0 !important;\s*border: 0 !important;/', $css, $file);
            self::assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s*\.nowo-ui-filters-more__chevron \{\s*transition:/', $css, $file);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<FilterField>    $fields
     * @param list<string>         $more
     */
    private function render(array $data, array $fields = [], array $more = [], ?bool $moreOpen = null): string
    {
        return PageContractTwig::create([], [], request: $this->request())->render('@NowoUiKitBundle/page/_list_filters.html.twig', [
            'page' => new ListPage(new PageHeader('Orders'), filters: $this->form($data), filterFields: $fields, filtersMore: $more, filtersMoreOpen: $moreOpen),
        ]);
    }

    private function request(): Request
    {
        $request = Request::create('https://app.example/orders');
        $request->attributes->set('_route', 'orders');
        $request->attributes->set('_route_params', []);

        return $request;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function form(array $data): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, $data + ['page' => '2'], ['method' => 'GET'])
            ->add('q', SearchType::class, ['required' => false])
            ->add('role', ChoiceType::class, ['required' => false, 'choices' => ['Admin' => 'admin']])
            ->add('mine', CheckboxType::class, ['required' => false])
            ->add('per_page', ChoiceType::class, ['required' => false, 'placeholder' => false, 'choices' => ['25' => '25', '50' => '50']])
            ->add('page', HiddenType::class)
            ->getForm()->createView();
    }
}
