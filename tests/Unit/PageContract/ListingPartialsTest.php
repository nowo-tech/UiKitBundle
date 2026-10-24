<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingCustomizerView;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingRegistry;
use Nowo\UiKitBundle\Test\FrameworkClasses;
use Nowo\UiKitBundle\Tests\Unit\Listing\InvoiceListing;
use Nowo\UiKitBundle\Tests\Unit\Listing\ServerListingStore;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Listing customizer partials: `listing_customizer` (edge tab + side panel dialog, Stimulus peer contract),
 * `listing_head`, `listing_cells`, `listing_filter_fields`; registry, slots, translations, CSS.
 */
final class ListingPartialsTest extends TestCase
{
    private const STACKED = '{"columns":[{"key":"total","visible":true},{"key":"customer","visible":true,"merged":true},{"key":"number","visible":true},{"key":"notes","visible":false}],"filters":[{"key":"status","visible":false}]}';

    private const CELLS = <<<'TWIG'
        {% block cell_number %}#{{ row.number }}{% endblock %}
        {% block cell_customer %}{{ row.customer }}{% endblock %}
        {% block cell_total %}{{ row.total }} €{% endblock %}
        {% block cell_notes %}{{ row.notes }}{% endblock %}
        TWIG;

    private static function view(?string $cookie = null, array $query = []): ListingCustomizerView
    {
        $builder = new ListingCustomizerViewBuilder(new ListingRegistry([new InvoiceListing()]), new CookieListingPreferenceStore());
        $request = new Request($query, [], [], null === $cookie ? [] : [CookieListingPreferenceStore::name(InvoiceListing::KEY) => $cookie]);

        return $builder->forRequest(InvoiceListing::KEY, $request)['view'];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $theme
     */
    private static function render(string $template, array $context, array $theme = [], string $framework = 'bootstrap5'): string
    {
        return PageContractTwig::create(['page.html.twig' => $template, 'cells.html.twig' => self::CELLS, 'custom.html.twig' => 'custom {{ listing.listingKey }}', 'heads.html.twig' => '{% block head_total %}<a href="/sort/total">{{ column.label }}</a>{% endblock %}'], $theme, $framework)->render('page.html.twig', $context);
    }

    public function testPartialsAreRegistered(): void
    {
        foreach (['listing_cells', 'listing_customizer', 'listing_filter_fields', 'listing_head'] as $name) {
            self::assertSame('@NowoUiKitBundle/partials/_'.$name.'.html.twig', ThemeSlots::PARTIALS[$name]);
        }
        $html = self::render("{% include nowo_ui_partial('listing_customizer') with {listing: listing} only %}", ['listing' => self::view()], ['partials' => ['listing_customizer' => 'custom.html.twig']]);
        self::assertSame('custom invoices.index', $html, 'a host can replace the partial');
    }

    public function testCustomizerDefaultMarkup(): void
    {
        $html = self::render("{% include nowo_ui_partial('listing_customizer') with {listing: listing} only %}", ['listing' => self::view()]);

        self::assertStringContainsString('<div class="nowo-ui-listing-customizer" data-controller="listing-customizer" data-listing-customizer-state-value="{&quot;listingKey&quot;:&quot;invoices.index&quot;,&quot;stored&quot;:false,&quot;mode&quot;:null,&quot;columns&quot;:[{&quot;key&quot;:&quot;number&quot;', $html);
        self::assertStringContainsString(' data-listing-customizer-cookie-value="nowo_ui_listing_invoices_index"', $html);
        self::assertStringNotContainsString('data-listing-customizer-url-value', $html);
        self::assertStringContainsString('data-listing-customizer-messages-value="{&quot;required&quot;:&quot;Always shown&quot;', $html);
        self::assertStringContainsString('&quot;moved&quot;:&quot;%label% moved to position %position% of %count%.&quot;', $html);
        self::assertStringContainsString('<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0">', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-edge-tab__link nowo-ui-listing-customizer__toggle" data-nowo-ui-dialog-open="nowo-ui-listing-invoices-index" data-listing-customizer-target="toggle" data-action="listing-customizer#open" aria-haspopup="dialog" aria-controls="nowo-ui-listing-invoices-index" title="Customize listing" aria-label="Customize listing">', $html);
        self::assertStringContainsString('<i class="bi bi-sliders" aria-hidden="true"></i>', $html);
        self::assertMatchesRegularExpression('#<dialog id="nowo-ui-listing-invoices-index" class="nowo-ui-dialog nowo-ui-dialog--sm nowo-ui-dialog--end" data-nowo-ui-dialog aria-labelledby="nowo-ui-listing-invoices-index-title" data-nowo-ui-dialog-backdrop-close data-listing-customizer-target="dialog">#', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-dialog__title" id="nowo-ui-listing-invoices-index-title">Customize listing</h2>', $html);
        self::assertStringContainsString('<div class="nowo-ui-listing-customizer__tabs" role="tablist" aria-label="Customize listing">', $html);
        self::assertStringContainsString('<button type="button" role="tab" id="nowo-ui-listing-invoices-index-tab-columns" class="nowo-ui-listing-customizer__tab" aria-selected="true" aria-controls="nowo-ui-listing-invoices-index-panel-columns" data-panel="columns" data-listing-customizer-target="tabButton" data-action="listing-customizer#selectTab keydown->listing-customizer#tabKeydown">Columns</button>', $html);
        self::assertStringContainsString('aria-selected="false" tabindex="-1" aria-controls="nowo-ui-listing-invoices-index-panel-filters"', $html);
        self::assertStringContainsString('<section id="nowo-ui-listing-invoices-index-panel-columns" class="nowo-ui-listing-customizer__panel" role="tabpanel" aria-labelledby="nowo-ui-listing-invoices-index-tab-columns" data-panel="columns" data-listing-customizer-target="panel">', $html);
        self::assertStringContainsString('aria-labelledby="nowo-ui-listing-invoices-index-tab-filters" hidden data-panel="filters"', $html);
        self::assertStringContainsString('<ul class="nowo-ui-listing-customizer__list" data-kind="columns" data-listing-customizer-target="list" aria-label="Columns"></ul>', $html);
        self::assertStringContainsString('<p class="nowo-ui-muted text-muted">Stack a column under the previous one to show both in one cell.</p>', $html);
        foreach (['grip' => 'bi bi-grip-vertical', 'up' => 'bi bi-chevron-up', 'down' => 'bi bi-chevron-down', 'stack' => 'bi bi-arrow-90deg-up'] as $name => $icon) {
            self::assertStringContainsString('<template data-listing-customizer-icon="'.$name.'"><i class="'.$icon.'" aria-hidden="true"></i></template>', $html);
        }
        self::assertStringContainsString('<p class="nowo-ui-listing-customizer__status" role="status" aria-live="polite" data-listing-customizer-target="status"></p>', $html);
        self::assertMatchesRegularExpression('#data-listing-customizer-target="resetButton" data-action="listing-customizer\#reset" disabled>Reset to default</button>#', $html);
        self::assertMatchesRegularExpression('#data-listing-customizer-target="applyButton" data-action="listing-customizer\#apply" disabled>Apply</button>#', $html);
        self::assertStringNotContainsString('nowo-ui-listing-notice', $html);
        self::assertStringNotContainsString('data-testid', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/', $html));
    }

    public function testCustomizerHasNoFrameworkClasses(): void
    {
        $view = self::view(self::STACKED, ['status' => 'paid']);
        foreach (['bootstrap5', 'tailwind', 'foundation', 'none'] as $framework) {
            $html = self::render("{% include nowo_ui_partial('listing_customizer') with {listing: listing, toggle: 'button'} only %}", ['listing' => $view], [], $framework);
            preg_match_all('/class="([^"]*)"/', $html, $m);
            self::assertSame([], FrameworkClasses::violations(preg_split('/\s+/', implode(' ', $m[1])) ?: [], $framework), $framework);
        }
    }

    public function testCustomizerOptionsThemeAndNotice(): void
    {
        $theme = ['classes' => ['listing_customizer' => 'app-lc', 'listing_notice' => 'app-notice', 'listing_customizer_toggle' => 'app-toggle'], 'attributes' => ['listing_customizer' => ['data-host' => '1']]];
        $html = self::render(
            "{% include nowo_ui_partial('listing_customizer') with {listing: listing, toggle: 'button', save_url: '/prefs/invoices', save_token: 'tok', save_token_url: '/prefs/token', save_method: 'POST', reset_method: 'POST', testids: true, size: 'md', dialog_id: 'lc', labels: {open: 'Columns…', apply: 'Save'}, icons: {open: 'config'}, attr: {'data-turbo': 'false'}} only %}",
            ['listing' => self::view(self::STACKED, ['status' => 'paid', 'tag' => ''])],
            $theme,
        );

        self::assertStringContainsString('<div class="nowo-ui-listing-customizer app-lc" data-controller="listing-customizer"', $html);
        self::assertStringContainsString('&quot;stored&quot;:true', $html);
        self::assertStringContainsString(' data-listing-customizer-url-value="/prefs/invoices" data-listing-customizer-token-value="tok" data-listing-customizer-token-url-value="/prefs/token" data-listing-customizer-method-value="POST" data-listing-customizer-reset-method-value="POST"', $html);
        self::assertStringNotContainsString('cookie-value', $html);
        self::assertStringContainsString(' data-testid="listing-customizer" data-host="1" data-turbo="false">', $html);
        self::assertStringContainsString('<div class="nowo-ui-listing-notice app-notice" role="status" data-testid="listing-ignored-filters">', $html);
        self::assertStringContainsString('<span>Hidden filters are not applied: Status.</span>', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--link btn btn-link" data-action="listing-customizer#openFilters">Filter settings</button>', $html);
        self::assertStringNotContainsString('nowo-ui-edge-tab', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-listing-customizer__toggle app-toggle" data-nowo-ui-dialog-open="lc"', $html);
        self::assertStringContainsString('<i class="bi bi-gear" aria-hidden="true"></i></span>', $html);
        self::assertStringContainsString('<span>Columns…</span>', $html);
        self::assertStringContainsString('<dialog id="lc" class="nowo-ui-dialog nowo-ui-dialog--end"', $html);
        self::assertStringContainsString('data-action="listing-customizer#reset" data-testid="listing-customizer-reset">Reset to default</button>', $html);
        self::assertStringContainsString('data-action="listing-customizer#apply" disabled data-testid="listing-customizer-apply">Save</button>', $html);

        $none = self::render("{% include nowo_ui_partial('listing_customizer') with {listing: listing, toggle: 'none', slot: 2} only %}", ['listing' => self::view()]);
        self::assertStringNotContainsString('data-listing-customizer-target="toggle"', $none);

        $slot = self::render("{% include nowo_ui_partial('listing_customizer') with {listing: listing, slot: 2, testids: true} only %}", ['listing' => self::view()]);
        self::assertStringContainsString('data-nowo-ui-edge-tab-slot="2"', $slot);
        self::assertStringContainsString('data-testid="listing-customizer-toggle"', $slot);
    }

    public function testServerStoreAndSinglePanel(): void
    {
        $definition = new class implements ListingDefinitionInterface {
            public function key(): string
            {
                return 'audit';
            }

            public function columns(): array
            {
                return [new ListingField('when', 'When', required: true)];
            }

            public function filters(): array
            {
                return [];
            }
        };
        $view = (new ListingCustomizerViewBuilder(new ListingRegistry([$definition]), new ServerListingStore()))->build('audit', new Request());
        $html = self::render("{% include nowo_ui_partial('listing_customizer') with {listing: listing} only %}", ['listing' => $view]);

        self::assertStringNotContainsString('cookie-value', $html);
        self::assertStringNotContainsString('url-value', $html);
        self::assertStringNotContainsString('role="tablist"', $html);
        self::assertStringNotContainsString('data-panel="filters"', $html);
        self::assertStringContainsString('<section id="nowo-ui-listing-audit-panel-columns" class="nowo-ui-listing-customizer__panel" aria-label="Columns" data-panel="columns" data-listing-customizer-target="panel">', $html);

        $filtersOnly = new class implements ListingDefinitionInterface {
            public function key(): string
            {
                return 'search';
            }

            public function columns(): array
            {
                return [];
            }

            public function filters(): array
            {
                return [new ListingField('q', 'Search', defaultVisible: false)];
            }
        };
        $builder = new ListingCustomizerViewBuilder(new ListingRegistry([$filtersOnly]), new CookieListingPreferenceStore());
        $view = $builder->forRequest('search', new Request(['q' => 'x']))['view'];
        $html = self::render("{% include nowo_ui_partial('listing_customizer') with {listing: listing} only %}", ['listing' => $view]);
        self::assertStringContainsString('<section id="nowo-ui-listing-search-panel-filters" class="nowo-ui-listing-customizer__panel" aria-label="Filters" data-panel="filters"', $html);
        self::assertStringNotContainsString('hint_stack', $html);
        self::assertStringContainsString('Hidden filters are not applied: Search.', $html);
    }

    public function testHead(): void
    {
        $html = self::render("<tr>{% include nowo_ui_partial('listing_head') with {listing: listing, th_class: 'px-2'} only %}</tr>", ['listing' => self::view(self::STACKED)], ['classes' => ['table_head_cell' => 'app-th']]);

        self::assertStringContainsString('<th class="nowo-ui-table__head-cell app-th px-2 h-total nowo-ui-listing-cell--stacked" scope="col" data-column="total" data-columns="total customer" data-priority="3"><span class="nowo-ui-listing-cell__part" data-cell-part>Total</span><span class="nowo-ui-listing-cell__part" data-cell-part>Customer</span></th>', $html);
        self::assertStringContainsString('<th class="nowo-ui-table__head-cell app-th px-2" scope="col" data-column="number" data-columns="number" data-required="1" data-priority="1">invoice.number</th>', $html);
        self::assertStringNotContainsString('data-column="notes"', $html);
        self::assertStringContainsString('<th class="nowo-ui-row-detail-col px-2" data-responsive-detail><span class="visually-hidden">Details</span></th>', $html);

        $sorted = self::render("{% include nowo_ui_partial('listing_head') with {listing_layout: listing.layout, heads_template: 'heads.html.twig', detail: false} only %}", ['listing' => self::view()]);
        self::assertStringContainsString('data-column="total" data-columns="total" data-priority="3"><a href="/sort/total">Total</a></th>', $sorted);
        self::assertStringContainsString('data-column="customer" data-columns="customer" data-priority="2">Customer</th>', $sorted);
        self::assertStringNotContainsString('data-responsive-detail', $sorted);

        $labelled = self::render("{% include nowo_ui_partial('listing_head') with {listing: listing, detail_label: 'More'} only %}", ['listing' => self::view()]);
        self::assertStringContainsString('<span class="visually-hidden">More</span>', $labelled);
    }

    public function testCells(): void
    {
        $row = ['number' => 'F-1', 'customer' => 'Acme <b>', 'total' => '10', 'notes' => 'n'];
        $html = self::render("{% for row in rows %}<tr>{% include nowo_ui_partial('listing_cells') with {listing: listing, cells_template: 'cells.html.twig', td_class: 'px-2', testids: true} %}</tr>{% endfor %}", ['listing' => self::view(self::STACKED), 'rows' => [$row]]);

        self::assertStringContainsString('<td class="nowo-ui-table__cell px-2 c-total nowo-ui-listing-cell--stacked" data-column="total" data-priority="3"><div class="nowo-ui-listing-cell__part" data-cell-part="total">10 €</div><div class="nowo-ui-listing-cell__part" data-cell-part="customer">Acme &lt;b&gt;</div></td>', $html);
        self::assertStringContainsString('<td class="nowo-ui-table__cell px-2 num" data-column="number" data-priority="1">#F-1</td>', $html);
        self::assertStringNotContainsString('data-column="notes"', $html);
        self::assertStringContainsString('<td class="nowo-ui-row-detail-cell px-2" data-responsive-detail>', $html);
        self::assertStringContainsString('data-testid="row-detail" hidden><i class="bi bi-eye"', $html);

        $plain = self::render("<tr>{% include nowo_ui_partial('listing_cells') with {listing_layout: listing.layout, cells_template: 'cells.html.twig', detail: false} %}</tr>", ['listing' => self::view('{"columns":[{"key":"notes","visible":true,"merged":true},{"key":"total","visible":true,"merged":true}]}'), 'row' => $row]);
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-listing-cell--stacked" data-column="notes" data-priority="0"><div class="nowo-ui-listing-cell__part" data-cell-part="notes">n</div><div class="nowo-ui-listing-cell__part c-total" data-cell-part="total">10 €</div></td>', $plain);
        self::assertStringNotContainsString('data-responsive-detail', $plain);

        $labelled = self::render("<tr>{% include nowo_ui_partial('listing_cells') with {listing: listing, cells_template: 'cells.html.twig', detail_label: 'Open', detail_icon: 'book'} %}</tr>", ['listing' => self::view(), 'row' => $row]);
        self::assertStringContainsString('aria-label="Open" title="Open" hidden><i class="bi bi-book"', $labelled);
    }

    public function testFilterFields(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('f', FormType::class)
            ->add('q', TextType::class)
            ->add('status', TextType::class)
            ->add('tag', TextType::class)
            ->add('per_page', TextType::class)
            ->getForm()
            ->createView();
        $view = self::view('{"filters":[{"key":"tag","visible":true},{"key":"q","visible":true},{"key":"status","visible":false}]}');
        $html = self::render("{% include nowo_ui_partial('listing_filter_fields') with {form: form, listing: listing, row_options: {q: {label: 'Find'}}} only %}{{ form_rest(form) }}", ['form' => $form, 'listing' => $view]);

        self::assertStringContainsString('<label for="f_q" class="required">Find</label>', $html);
        self::assertLessThan(strpos($html, 'id="f_q"'), strpos($html, 'id="f_tag"'), 'viewer order');
        self::assertStringNotContainsString('id="f_status"', $html, 'hidden filters are never painted');
        self::assertStringContainsString('id="f_per_page"', $html, 'fields outside the definition are left to the caller');

        $layoutOnly = self::render("{% include nowo_ui_partial('listing_filter_fields') with {form: form, listing_layout: listing.layout} only %}", ['form' => PageContractTwig::forms()->createNamedBuilder('g', FormType::class)->add('q', TextType::class)->getForm()->createView(), 'listing' => self::view()]);
        self::assertStringContainsString('id="g_q"', $layoutOnly);
    }

    public function testResponsiveTableCookieOption(): void
    {
        $html = self::render("{% embed '@NowoUiKitBundle/partials/_responsive_table.html.twig' with listing.tableOptions %}{% block table %}<table></table>{% endblock %}{% endembed %}", ['listing' => self::view('{"mode":"responsive"}')]);

        self::assertStringContainsString('data-responsive-table-mode-value="responsive"', $html);
        self::assertStringContainsString(' data-responsive-table-cookie-value="nowo_ui_listing_invoices_index"', $html);
        self::assertStringNotContainsString('data-responsive-table-url-value', $html);
    }

    public function testTranslationsAndCss(): void
    {
        $root = PageContractTwig::root();
        $en = Yaml::parseFile($root.'/src/Resources/translations/NowoUiKitBundle.en.yaml');
        $keys = array_values(array_filter(array_keys($en), static fn (string $k): bool => str_starts_with($k, 'listing.')));
        self::assertCount(23, $keys);
        foreach (glob($root.'/src/Resources/translations/NowoUiKitBundle.*.yaml') ?: [] as $file) {
            $catalogue = Yaml::parseFile($file);
            foreach ($keys as $key) {
                self::assertIsString($catalogue[$key] ?? null, basename($file).': '.$key);
                self::assertNotSame('', $catalogue[$key]);
            }
            self::assertStringContainsString('%filters%', $catalogue['listing.ignored_filters'], basename($file));
            foreach (['%label%', '%position%', '%count%'] as $placeholder) {
                self::assertStringContainsString($placeholder, $catalogue['listing.moved'], basename($file));
            }
        }

        $css = (string) file_get_contents($root.'/src/Resources/public/css/nowo-ui.unlayered.css');
        foreach (['.nowo-ui-listing-customizer__item', '.nowo-ui-listing-customizer__move', '.nowo-ui-listing-cell__part', '.nowo-ui-listing-notice', 'button.nowo-ui-edge-tab__link'] as $selector) {
            self::assertStringContainsString($selector, $css, $selector);
        }
    }
}
