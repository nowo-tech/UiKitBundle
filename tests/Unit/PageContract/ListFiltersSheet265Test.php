<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.65: `theme.pages.list.filters.sheet` wraps the list_filters form in the filter_bar phone sheet (shared macros in
 * macros/filter_sheet.html.twig, runtime filter-sheet). Off by default (ListFilters230BcTest keeps the default output
 * byte-identical). The dialog is a plain native <dialog>: no role, aria-modal, inert or open attribute.
 */
final class ListFiltersSheet265Test extends TestCase
{
    public function testSheetWrapsTheSameFormWithTheFilterBarContract(): void
    {
        $html = $this->render(['sheet' => true]);
        $c = new Crawler($html);

        $root = $c->filter('#nowo-ui-list-filters-filters');
        self::assertCount(1, $root);
        self::assertSame('nowo-ui-filter-sheet nowo-ui-filter-sheet--list', $root->attr('class'));
        self::assertNotNull($root->attr('data-nowo-ui-filter-sheet'));
        // the region stays on the form (no second "filters" region)
        self::assertNull($root->attr('data-nowo-ui-region'));
        self::assertCount(1, $c->filter('[data-nowo-ui-region="filters"]'));

        // the "Filters (n)" button: q and status filled, the hidden page field ignored
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--outline nowo-ui-filter-sheet__toggle" aria-haspopup="dialog" aria-controls="nowo-ui-list-filters-filters-dialog" data-nowo-ui-filter-sheet-open>Filters <span class="nowo-ui-filter-sheet__count"><span aria-hidden="true">2</span><span class="visually-hidden">2 active</span></span></button>', $html);

        // the panel holds the unchanged form, the dialog follows the panel
        $form = $c->filter('#nowo-ui-list-filters-filters > [data-nowo-ui-filter-sheet-panel] > form[data-nowo-ui-filters]');
        self::assertCount(1, $form);
        self::assertSame('search', $form->attr('role'));
        $dialog = $c->filter('#nowo-ui-list-filters-filters > dialog[data-nowo-ui-filter-sheet-dialog]');
        self::assertCount(1, $dialog);
        self::assertSame('nowo-ui-list-filters-filters-dialog', $dialog->attr('id'));
        self::assertSame('nowo-ui-list-filters-filters-title', $dialog->attr('aria-labelledby'));
        foreach (['role', 'aria-modal', 'open', 'inert'] as $attr) {
            self::assertNull($dialog->attr($attr), $attr.' is left to showModal()');
        }
        self::assertSame('Filters', $dialog->filter('h2#nowo-ui-list-filters-filters-title')->text());
        self::assertSame('Close', $dialog->filter('button[data-nowo-ui-filter-sheet-close]')->text());
        self::assertCount(1, $dialog->filter('[data-nowo-ui-filter-sheet-slot]'));
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $html);
    }

    public function testSheetWithFoldKeepsTheFoldToggleInsideTheForm(): void
    {
        $html = $this->render(['sheet' => true, 'fold' => ['enabled' => true]]);
        $c = new Crawler($html);
        self::assertCount(1, $c->filter('#nowo-ui-list-filters-filters [data-nowo-ui-filter-sheet-panel] form#nowo-ui-filters-filters > .nowo-ui-filters-collapse'));
        self::assertSame('nowo-ui-filters-filters', $c->filter('.nowo-ui-filters-collapse__toggle')->attr('aria-controls'));
    }

    public function testNoActiveFilterPrintsNoCountAndTranslates(): void
    {
        $html = $this->render(['sheet' => true], ['q' => '', 'status' => null], 'es');
        self::assertMatchesRegularExpression('#data-nowo-ui-filter-sheet-open>[^<]+</button>#', $html);
        self::assertStringNotContainsString('nowo-ui-filter-sheet__count', $html);
        $c = new Crawler($html);
        self::assertSame($c->filter('[data-nowo-ui-filter-sheet-open]')->text(), $c->filter('h2.nowo-ui-filter-sheet__dialog-title')->text());
        self::assertNotSame('filter_bar.title', $c->filter('h2.nowo-ui-filter-sheet__dialog-title')->text());
    }

    public function testDefaultPrintsNoSheet(): void
    {
        foreach ([[], ['sheet' => false]] as $options) {
            $html = $this->render($options);
            self::assertStringNotContainsString('nowo-ui-filter-sheet', $html);
            self::assertStringNotContainsString('<dialog', $html);
        }
    }

    public function testListPageRendersTheSheet(): void
    {
        $twig = PageContractTwig::create([], ['pages' => ['list' => ['filters' => ['sheet' => true]]]], request: $this->request(), breadcrumbKit: false);
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], $this->form())]);
        self::assertCount(1, (new Crawler($html))->filter('.nowo-ui-list-page > .nowo-ui-filter-sheet--list > dialog.nowo-ui-filter-sheet__dialog'));
    }

    public function testScriptsPartialAppendsTheRuntimeWithTheSheet(): void
    {
        $tpl = "{{ include(nowo_ui_partial('scripts'), names is null ? {} : {script_names: names}) }}";
        $runtime = '/bundles/nowouikit/js/nowo-ui-filter-sheet.js';
        $render = static fn (?array $names, array $theme): string => PageContractTwig::create(['t' => $tpl], $theme)->render('t', ['names' => $names]);

        self::assertNotContains('filter-sheet', ThemeSlots::SCRIPTS, 'opt-in runtime');
        self::assertStringNotContainsString($runtime, $render(null, []));
        self::assertStringNotContainsString($runtime, $render(null, ['pages' => ['list' => ['filters' => ['sheet' => false]]]]));

        $on = $render(null, ['pages' => ['list' => ['filters' => ['sheet' => true]]]]);
        self::assertSame(1, substr_count($on, $runtime));
        self::assertSame(\count(ThemeSlots::SCRIPTS) + 1, substr_count($on, '<script '));
        self::assertSame(1, substr_count($render(['filter-sheet'], ['pages' => ['list' => ['filters' => ['sheet' => true]]]]), $runtime));
    }

    public function testStylesheetFollowsTheModalDialogGuidance(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
            self::assertMatchesRegularExpression('/\.nowo-ui-filter-sheet__dialog:modal \{\s*overscroll-behavior: contain;\s*\}/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-filter-sheet__dialog::backdrop \{\s*overflow: hidden;\s*overscroll-behavior: contain;/', $css, $file);
            // the list form keeps its grid in the panel; inside the sheet the fold toggle hides and a folded card unfolds
            self::assertStringContainsString('.nowo-ui-filter-sheet__panel form:not([data-nowo-ui-filters]),', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-filter-sheet__dialog-body \.nowo-ui-filters-collapse \{\s*display: none !important;/', $css, $file);
            self::assertStringContainsString('form:has(> .nowo-ui-filters-collapse.is-collapsed):not(.nowo-ui-filter-sheet__dialog-body form) {', $css, $file);
            // never a scroll lock on the root
            self::assertDoesNotMatchRegularExpression('/(^|[\s,}])(html|body)\[[^\]]*filter-sheet[^{]*\{[^}]*overflow: hidden/', $css, $file);
        }
    }

    /**
     * @param array<string, mixed> $filters theme.pages.list.filters
     * @param array<string, mixed> $data
     */
    private function render(array $filters, array $data = ['q' => 'abc', 'status' => 'open'], string $locale = 'en'): string
    {
        $twig = PageContractTwig::create([], ['pages' => ['list' => ['filters' => $filters]]], request: $this->request(), locale: $locale);

        return $twig->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: $this->form($data))]);
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
    private function form(array $data = ['q' => 'abc', 'status' => 'open']): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, $data + ['page' => '2'], ['method' => 'GET'])
            ->add('q', SearchType::class, ['required' => false])
            ->add('status', ChoiceType::class, ['required' => false, 'choices' => ['Open' => 'open']])
            ->add('page', HiddenType::class)
            ->getForm()->createView();
    }
}
