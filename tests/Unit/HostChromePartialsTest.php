<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * 1.17 host chrome partials: responsive table, row detail cells, foldable filters, disclosure icon, corner tab,
 * page-edge tab, new canonical icons, CSS and translations.
 */
final class HostChromePartialsTest extends TestCase
{
    public function testResponsiveTableDefaults(): void
    {
        $html = $this->render("{% embed '@NowoUiKitBundle/partials/_responsive_table.html.twig' %}{% block table %}<table data-responsive-table-target=\"table\"></table>{% endblock %}{% endembed %}");

        self::assertStringContainsString('class="nowo-ui-responsive-table nowo-ui-responsive-table--corner"', $html);
        self::assertStringContainsString('data-controller="responsive-table"', $html);
        self::assertStringContainsString('data-responsive-table-mode-value=""', $html);
        self::assertStringNotContainsString('data-responsive-table-url-value', $html);
        self::assertStringNotContainsString('data-table-mode=', $html);
        self::assertStringNotContainsString('data-testid', $html);
        self::assertStringNotContainsString('<morph-icon', $html);
        self::assertStringContainsString('data-responsive-table-messages-value="{&quot;toResponsive&quot;:&quot;table_mode.to_responsive&quot;,&quot;toScroll&quot;:&quot;table_mode.to_scroll&quot;,&quot;details&quot;:&quot;table_mode.details&quot;,&quot;saveError&quot;:&quot;table_mode.save_error&quot;}"', $html);
        self::assertStringContainsString('class="nowo-ui-responsive-table__toggle nowo-ui-corner-tab"', $html);
        self::assertStringContainsString('aria-pressed="false"', $html);
        self::assertStringContainsString('aria-label="table_mode.to_responsive"', $html);
        self::assertStringContainsString('<span class="nowo-ui-responsive-table__icon nowo-ui-responsive-table__icon--scroll" aria-hidden="true"><i class="bi bi-arrows" aria-hidden="true"></i></span>', $html);
        self::assertStringContainsString('<i class="bi bi-view-stacked" aria-hidden="true"></i>', $html);
        self::assertStringContainsString('<div class="nowo-ui-table-wrap nowo-ui-responsive-table__scroller" data-responsive-table-target="scroller">', $html);
        self::assertStringContainsString('<table data-responsive-table-target="table"></table>', $html);
        self::assertMatchesRegularExpression('/<dialog class="nowo-ui-confirm nowo-ui-row-detail" data-responsive-table-target="dialog" aria-labelledby="(nowo-ui-rt-\d+)-title">/', $html);
        self::assertStringContainsString('data-action="responsive-table#close" aria-label="table_mode.close"', $html);
        self::assertStringContainsString('data-responsive-table-target="dialogBody"', $html);
    }

    public function testResponsiveTableOptions(): void
    {
        $html = $this->render(
            "{% embed '@NowoUiKitBundle/partials/_responsive_table.html.twig' with opts %}{% block table %}<table></table>{% endblock %}{% endembed %}",
            ['opts' => [
                'table_mode' => 'responsive',
                'save_url' => '/prefs/items/mode',
                'save_token' => 'tok',
                'save_token_url' => '/prefs/token',
                'save_method' => 'POST',
                'phone_query' => '(max-width: 600px)',
                'part_selector' => '.part',
                'corner' => false,
                'morph' => true,
                'icons' => ['scroll' => 'arrows', 'close' => 'x'],
                'labels' => ['to_scroll' => 'Scroll it', 'close' => 'Shut', 'save_error' => 'Mode "not" saved'],
                'attr' => ['data-listing' => 'items', 'aria-describedby' => 'help "x"'],
                'dialog_id' => 'rt-items',
                'root_class' => 'mine',
                'scroller_class' => 'overflow-x-auto',
                'dialog_class' => 'confirm-dialog',
                'testids' => true,
            ]],
        );

        self::assertStringContainsString('class="nowo-ui-responsive-table mine"', $html);
        self::assertStringContainsString('data-responsive-table-mode-value="responsive" data-responsive-table-url-value="/prefs/items/mode" data-responsive-table-token-value="tok" data-responsive-table-token-url-value="/prefs/token" data-responsive-table-method-value="POST" data-responsive-table-phone-query-value="(max-width: 600px)" data-responsive-table-part-selector-value=".part"', $html);
        self::assertStringContainsString('data-table-mode="responsive" data-testid="responsive-table" data-listing="items" aria-describedby="help &quot;x&quot;"', $html);
        self::assertStringContainsString('&quot;saveError&quot;:&quot;Mode \\&quot;not\\&quot; saved&quot;', $html);
        self::assertStringContainsString('class="nowo-ui-responsive-table__toggle"', $html);
        self::assertStringContainsString('aria-pressed="true"', $html);
        self::assertMatchesRegularExpression('/aria-label="Scroll it"\s+title="Scroll it" data-testid="table-mode-toggle"/', $html);
        self::assertStringContainsString('<i class="bi bi-arrows" aria-hidden="true"></i>', $html);
        self::assertStringContainsString('<morph-icon class="nowo-ui-responsive-table__morph" data-table-mode-morph size="14" stroke-width="2" aria-hidden="true"></morph-icon>', $html);
        self::assertStringContainsString('nowo-ui-responsive-table__scroller overflow-x-auto"', $html);
        self::assertStringContainsString('<dialog class="nowo-ui-confirm nowo-ui-row-detail confirm-dialog" data-responsive-table-target="dialog" aria-labelledby="rt-items-title" data-testid="row-detail-dialog">', $html);
        self::assertStringContainsString('id="rt-items-title"', $html);
        self::assertStringContainsString('aria-label="Shut" title="Shut"><i class="bi bi-x" aria-hidden="true"></i></button>', $html);

        $noToggle = $this->render(
            "{% embed '@NowoUiKitBundle/partials/_responsive_table.html.twig' with {mode_toggle: false} %}{% block table %}{% endblock %}{% endembed %}",
        );
        self::assertStringNotContainsString('responsive-table#toggle', $noToggle);
    }

    public function testRowDetailHeadAndCell(): void
    {
        self::assertSame(
            '<th class="nowo-ui-row-detail-col" data-responsive-detail><span class="visually-hidden">table_mode.details_column</span></th>',
            $this->render("{% include '@NowoUiKitBundle/partials/_row_detail_head.html.twig' only %}"),
        );
        self::assertStringContainsString(
            '<th class="nowo-ui-row-detail-col px-2" data-responsive-detail><span class="visually-hidden">More</span></th>',
            $this->render("{% include '@NowoUiKitBundle/partials/_row_detail_head.html.twig' with {th_class: 'px-2', label: 'More'} only %}"),
        );

        $cell = $this->render("{% include '@NowoUiKitBundle/partials/_row_detail_cell.html.twig' only %}");
        self::assertStringContainsString('<td class="nowo-ui-row-detail-cell" data-responsive-detail>', $cell);
        self::assertStringContainsString('<button type="button" class="nowo-ui-row-detail-btn" data-action="responsive-table#open" aria-label="table_mode.details" title="table_mode.details" hidden><i class="bi bi-eye" aria-hidden="true"></i></button>', $cell);

        $custom = $this->render("{% include '@NowoUiKitBundle/partials/_row_detail_cell.html.twig' with {td_class: 'px-2', label: 'Open', icon: 'book', testids: true} only %}");
        self::assertStringContainsString('<td class="nowo-ui-row-detail-cell px-2" data-responsive-detail>', $custom);
        self::assertStringContainsString('aria-label="Open" title="Open" data-testid="row-detail" hidden><i class="bi bi-book"', $custom);
    }

    public function testFiltersCollapseFromCookieAndFormView(): void
    {
        $request = new Request([], [], ['_route' => 'items_index'], ['nowo_ui_filters_collapsed' => 'other,items_index']);
        $form = $this->formView([
            ['name' => 'q', 'value' => 'error', 'prefixes' => ['form', 'text']],
            ['name' => 'status', 'value' => '', 'prefixes' => ['form', 'choice']],
            ['name' => 'tags', 'value' => ['a'], 'prefixes' => ['form', 'choice']],
            ['name' => 'unread', 'value' => '1', 'checked' => true, 'prefixes' => ['form', 'checkbox']],
            ['name' => 'mine', 'value' => '1', 'checked' => false, 'prefixes' => ['form', 'checkbox']],
            ['name' => 'per_page', 'value' => '25', 'prefixes' => ['form', 'choice']],
            ['name' => 'secret', 'value' => 'x', 'prefixes' => ['form', 'hidden']],
        ]);

        $html = $this->render("{% include '@NowoUiKitBundle/partials/_filters_collapse.html.twig' with {filter_form: f, morph: true, testids: true} only %}", ['f' => $form], $request);
        self::assertStringContainsString('<div class="nowo-ui-filters-collapse is-collapsed" data-controller="filters-collapse"', $html);
        self::assertStringContainsString('data-filters-collapse-key-value="items_index"', $html);
        self::assertStringNotContainsString('data-filters-collapse-cookie-value', $html);
        self::assertStringContainsString('class="nowo-ui-filters-collapse__toggle nowo-ui-corner-tab nowo-ui-corner-tab--under"', $html);
        self::assertStringContainsString('aria-expanded="false" aria-label="filters_collapse.expand" title="filters_collapse.expand" data-testid="filters-collapse-toggle">', $html);
        self::assertStringContainsString('<i class="bi bi-chevron-up" aria-hidden="true"></i>', $html);
        self::assertStringContainsString('<i class="bi bi-chevron-down" aria-hidden="true"></i>', $html);
        self::assertStringContainsString('<morph-icon class="nowo-ui-filters-collapse__morph" data-filters-collapse-morph', $html);
        // q + tags + unread = 3 (empty, unchecked, per_page and hidden fields do not count).
        self::assertStringContainsString('data-testid="filters-collapse-summary">filters_collapse.many</p>', $html);

        // 2.14: a multi-select counts once when anything is chosen, a checkbox list once per checked box.
        $count = fn (array $fields): string => $this->render("{% include '@NowoUiKitBundle/partials/_filters_collapse.html.twig' with {filter_form: f, testids: true} only %}", ['f' => $this->formView($fields)], new Request([], [], ['_route' => 'items_index']));
        self::assertStringContainsString('>filters_collapse.one</p>', $count([['name' => 'status', 'value' => ['active', 'pending'], 'prefixes' => ['form', 'choice']]]));
        self::assertStringContainsString('>filters_collapse.none</p>', $count([['name' => 'status', 'value' => ['', null], 'prefixes' => ['form', 'choice']]]));
        self::assertStringContainsString('>filters_collapse.many</p>', $count([['name' => 'kinds', 'value' => ['a', 'b'], 'expanded' => true, 'prefixes' => ['form', 'choice']]]));

        $open = $this->render("{% include '@NowoUiKitBundle/partials/_filters_collapse.html.twig' only %}", [], new Request([], [], ['_route' => 'items_index']));
        self::assertStringContainsString('<div class="nowo-ui-filters-collapse" data-controller="filters-collapse"', $open);
        self::assertStringContainsString('aria-expanded="true" aria-label="filters_collapse.collapse"', $open);
        self::assertStringContainsString('data-filters-collapse-target="summary" hidden>filters_collapse.none</p>', $open);
        self::assertStringNotContainsString('<morph-icon', $open);
    }

    public function testFiltersCollapseOverrides(): void
    {
        $html = $this->render(
            "{% include '@NowoUiKitBundle/partials/_filters_collapse.html.twig' with {fold_key: 'k', fold_cookie: 'app_filters', folded: true, active_count: 1, labels: {one: 'One filter'}, icons: {collapsed: 'filter'}} only %}",
        );
        self::assertStringContainsString('class="nowo-ui-filters-collapse is-collapsed"', $html);
        self::assertStringContainsString('data-filters-collapse-key-value="k" data-filters-collapse-cookie-value="app_filters"', $html);
        self::assertStringContainsString('&quot;one&quot;:&quot;One filter&quot;', $html);
        self::assertStringContainsString('<i class="bi bi-funnel" aria-hidden="true"></i>', $html);
        self::assertStringContainsString('>One filter</p>', $html);

        // No request, no key: never folded from the cookie; a single many label gets the count.
        $many = $this->render(
            "{% include '@NowoUiKitBundle/partials/_filters_collapse.html.twig' with {active_count: 3, labels: {many: '%count% on'}} only %}",
            [],
        );
        self::assertStringContainsString('data-filters-collapse-key-value=""', $many);
        self::assertStringContainsString('class="nowo-ui-filters-collapse"', $many);
        self::assertStringContainsString(' hidden>3 on</p>', $many);
    }

    public function testFiltersPartialCollapsibleOption(): void
    {
        self::assertStringNotContainsString('nowo-ui-filters-collapse', $this->render("{% include '@NowoUiKitBundle/partials/_filters.html.twig' only %}"));

        $html = $this->render("{% include '@NowoUiKitBundle/partials/_filters.html.twig' with {q: 'abc', collapsible: true, collapse: {fold_key: 'search'}} only %}");
        self::assertMatchesRegularExpression('/role="search"\s*>\s*<div class="nowo-ui-filters-collapse"/', $html);
        self::assertStringContainsString('data-filters-collapse-key-value="search"', $html);
        self::assertStringContainsString('>filters_collapse.one</p>', $html);
    }

    public function testDisclosureIcon(): void
    {
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_disclosure_icon.html.twig' only %}");
        self::assertStringContainsString('<span class="nowo-ui-disclosure-icon" aria-hidden="true">', $html);
        self::assertStringContainsString('<span class="nowo-ui-disclosure-icon__static nowo-ui-disclosure-icon__static--open"><i class="bi bi-chevron-down" aria-hidden="true"></i></span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-disclosure-icon__static nowo-ui-disclosure-icon__static--closed"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>', $html);
        self::assertStringNotContainsString('morph-icon', $html);

        $morph = $this->render("{% include '@NowoUiKitBundle/partials/_disclosure_icon.html.twig' with {class: 'ms-1', morph: true, icons: {open: 'chevron_up'}} only %}");
        self::assertStringContainsString('class="nowo-ui-disclosure-icon ms-1"', $morph);
        self::assertStringContainsString('bi-chevron-up', $morph);
        self::assertStringContainsString('<morph-icon class="nowo-ui-disclosure-icon__morph" data-disclosure-morph size="14" stroke-width="2"></morph-icon>', $morph);
    }

    public function testCornerTab(): void
    {
        $button = $this->render("{% include '@NowoUiKitBundle/partials/_corner_tab.html.twig' with {label: 'Open \"drawer\"', icon: 'config', attr: {'data-action': 'drawer#open', 'aria-expanded': 'false'}} only %}");
        self::assertSame(
            '<button class="nowo-ui-corner-tab" type="button" aria-label="Open &quot;drawer&quot;" title="Open &quot;drawer&quot;" data-action="drawer#open" aria-expanded="false"><span class="nowo-ui-corner-tab__icon" aria-hidden="true"><i class="bi bi-gear" aria-hidden="true"></i></span></button>',
            $button,
        );

        $link = $this->render("{% include '@NowoUiKitBundle/partials/_corner_tab.html.twig' with {label: 'Help', href: '/help', under: true, class: 'x'} only %}");
        self::assertSame('<a class="nowo-ui-corner-tab nowo-ui-corner-tab--under x" href="/help" aria-label="Help" title="Help"><span class="visually-hidden">Help</span></a>', $link);
    }

    public function testBackLink(): void
    {
        $link = $this->render("{% include '@NowoUiKitBundle/partials/_back_link.html.twig' with {href: '/projects', label: 'Back to projects', testid: 'back'} only %}");
        self::assertSame(
            '<a href="/projects" class="nowo-ui-back-link" data-testid="back">'."\n".'    <span class="nowo-ui-back-link__icon" aria-hidden="true"><i class="bi bi-chevron-left" aria-hidden="true"></i></span>'."\n".'    <span>Back to projects</span>'."\n".'</a>',
            $link,
        );

        $custom = $this->render("{% include '@NowoUiKitBundle/partials/_back_link.html.twig' with {href: '/x', label: label, icon: 'close', class: 'mine'} only %}", ['label' => new \Nowo\UiKitBundle\View\Label('back.key')]);
        self::assertStringContainsString('<a href="/x" class="nowo-ui-back-link mine">', $custom);
        self::assertStringContainsString('bi-x-lg', $custom);
        self::assertStringContainsString('<span>back.key</span>', $custom);
    }

    /** 2.15.4: with `icon_set: none` the label is printed once (no text "icon" repeating it). */
    public function testBackLinkWithoutIconSet(): void
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader(['t.twig' => "{% include '@NowoUiKitBundle/partials/_back_link.html.twig' with {href: '/', label: 'Dashboard'} only %}"]), $fs]));
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addGlobal('nowo_ui_kit_icon_set', 'none');

        $link = trim($twig->render('t.twig'));
        self::assertSame('<a href="/" class="nowo-ui-back-link">'."\n".'    <span>Dashboard</span>'."\n".'</a>', $link);
        self::assertSame(1, substr_count($link, 'Dashboard'));
    }

    public function testEdgeTab(): void
    {
        $tab = $this->render("{% include '@NowoUiKitBundle/partials/_edge_tab.html.twig' with {href: 'https://docs/x.md'} only %}");
        self::assertStringContainsString('<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0">', $tab);
        self::assertStringContainsString('<a class="nowo-ui-edge-tab__link" href="https://docs/x.md" target="_blank" rel="noopener noreferrer" title="edge_tab.manual" aria-label="edge_tab.manual">', $tab);
        self::assertStringContainsString('<i class="bi bi-book" aria-hidden="true"></i>', $tab);

        $custom = $this->render("{% include '@NowoUiKitBundle/partials/_edge_tab.html.twig' with {href: '/h', label: 'Help', icon: 'view', slot: 2, new_tab: false, class: 'mine', testid: 'manual-link-admin'} only %}");
        self::assertStringContainsString('<div class="nowo-ui-edge-tab mine" data-nowo-ui-edge-tab-slot="2" data-testid="manual-link-admin">', $custom);
        self::assertStringContainsString('<a class="nowo-ui-edge-tab__link" href="/h" title="Help" aria-label="Help">', $custom);

        $panel = $this->render("{% include '@NowoUiKitBundle/partials/_edge_tab.html.twig' with {href: '/m', new_tab: false, attr: {'data-nowo-ui-dialog-open': 'help', 'data-nowo-ui-dialog-src': '/m?panel=1', 'onclick': 'x()', 'data-off': false}} only %}");
        self::assertStringContainsString('<a class="nowo-ui-edge-tab__link" href="/m" title="edge_tab.manual" aria-label="edge_tab.manual" data-nowo-ui-dialog-open="help" data-nowo-ui-dialog-src="/m?panel=1">', $panel);
        self::assertStringNotContainsString('onclick', $panel, 'no inline handlers (CSP)');

        $css = (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/nowo-ui.unlayered.css');
        foreach (range(1, 7) as $slot) {
            self::assertStringContainsString(\sprintf("[data-nowo-ui-edge-tab-slot='%d'] .nowo-ui-edge-tab__link {\n  --nowo-ui-edge-tab-slot: %d;", $slot, $slot), $css, 'slot '.$slot);
        }

        $inline = $this->render("{% include '@NowoUiKitBundle/partials/_edge_tab.html.twig' with {href: '/h', inline: true, testid: 't'} only %}");
        self::assertStringContainsString('<p class="nowo-ui-edge-link" data-testid="t">', $inline);
        self::assertStringContainsString('<a href="/h" target="_blank" rel="noopener noreferrer">edge_tab.manual</a>', $inline);
        $inlinePlain = $this->render("{% include '@NowoUiKitBundle/partials/_edge_tab.html.twig' with {href: '/h', inline: true, new_tab: false, class: 'c'} only %}");
        self::assertStringContainsString('<p class="nowo-ui-edge-link c">', $inlinePlain);
        self::assertStringContainsString('<a href="/h">edge_tab.manual</a>', $inlinePlain);
    }

    public function testNewCanonicalIconsInEverySet(): void
    {
        $expected = [
            'bootstrap-icons' => ['scroll_horizontal' => 'bi bi-arrows', 'rows' => 'bi bi-view-stacked', 'chevron_up' => 'bi bi-chevron-up', 'filter_off' => 'bi bi-x-circle', 'book' => 'bi bi-book'],
            'tabler-icons' => ['scroll_horizontal' => 'ti ti-arrows-horizontal', 'rows' => 'ti ti-layout-rows', 'chevron_down' => 'ti ti-chevron-down', 'filter_off' => 'ti ti-filter-off', 'chevron_right' => 'ti ti-chevron-right'],
        ];
        foreach ($expected as $set => $map) {
            foreach ($map as $name => $class) {
                self::assertStringContainsString('class="'.$class.'"', $this->renderIcon($name, $set), $set.' '.$name);
            }
        }
        foreach (['filter_off', 'scroll_horizontal', 'rows', 'chevron_up', 'chevron_down', 'chevron_right', 'book'] as $name) {
            self::assertMatchesRegularExpression('/<path fill="currentColor" d="M[^"]+"\/>/', $this->renderIcon($name, 'svg_inline'));
        }
    }

    public function testCssAndTranslations(): void
    {
        $root = \dirname(__DIR__, 2);
        $css = (string) file_get_contents($root.'/src/Resources/public/css/nowo-ui.css');
        foreach ([
            '.nowo-ui-corner-tab {', '.nowo-ui-corner-tab--under {', '--nowo-ui-corner-tab-width', '--nowo-ui-corner-tab-accent',
            '.nowo-ui-responsive-table[data-table-mode=\'responsive\'] [data-rt-hidden]', '.nowo-ui-row-detail__list {',
            'form:has(> .nowo-ui-filters-collapse.is-collapsed) > :not(.nowo-ui-filters-collapse)', 'isolation: isolate',
            '.nowo-ui-disclosure-icon {', '.nowo-ui-edge-tab__link {', 'html[data-nowo-ui-thin-scrollbars]', '.nowo-ui-thin-scroll',
            '::-webkit-scrollbar-thumb:hover', '@supports not selector(::-webkit-scrollbar)',
        ] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
        self::assertFileExists($root.'/src/Resources/assets/styles/_thin-scrollbars.scss');

        // 1.17.2: dialogs centre under a <dialog> margin reset (Tailwind preflight); responsive cells wrap their
        // nowrap descendants too (no horizontal scroll at 320px and above).
        self::assertMatchesRegularExpression('/\.nowo-ui-confirm \{[^}]*margin: auto;[^}]*max-height: calc\(100dvh - 2rem\);/s', $css);
        // 1.17.3 / 1.17.5 regressions: words never break while the peer measures (data-rt-measuring), and
        // `overflow-wrap: anywhere` only applies after the fit as a last resort (data-rt-overflow) — never by width alone,
        // otherwise the table always "fits" and no column is hidden.
        self::assertStringContainsString(".nowo-ui-responsive-table[data-table-mode='responsive'] :is(th, td) * {\n  white-space: normal !important;\n  overflow-wrap: break-word;", $css);
        self::assertStringContainsString(".nowo-ui-responsive-table[data-rt-measuring] :is(th, td) * {\n  overflow-wrap: normal !important;\n  word-break: normal !important;", $css);
        self::assertStringContainsString(".nowo-ui-responsive-table[data-table-mode='responsive'][data-rt-overflow]:not([data-rt-measuring]) :is(th, td) * {\n  overflow-wrap: anywhere;", $css);
        $tableRules = (string) preg_replace('/\/\*.*?\*\//s', '', (string) strstr((string) strstr($css, '/* --- Responsive table'), '/* Row detail dialog', true));
        // The row detail column shows (and only shows) when the fit hid a column.
        self::assertStringContainsString(".nowo-ui-row-detail-col,\n.nowo-ui-row-detail-cell {\n  display: none;", $tableRules);
        self::assertStringContainsString(".nowo-ui-responsive-table[data-table-mode='responsive'][data-rt-has-hidden] :is(.nowo-ui-row-detail-col, .nowo-ui-row-detail-cell) {\n  display: table-cell;", $tableRules);
        self::assertSame(1, substr_count($tableRules, 'overflow-wrap: anywhere'), 'anywhere only in the data-rt-overflow rule');
        preg_match_all('/([^{}]+)\{[^}]*overflow-wrap: anywhere/', $tableRules, $anywhere);
        foreach ($anywhere[1] as $selector) {
            self::assertStringContainsString('[data-rt-overflow]:not([data-rt-measuring])', $selector);
        }

        $keys = ['table_mode.to_responsive', 'table_mode.to_scroll', 'table_mode.details', 'table_mode.details_column', 'table_mode.close', 'table_mode.save_error',
            'filters_collapse.collapse', 'filters_collapse.expand', 'filters_collapse.none', 'filters_collapse.one', 'filters_collapse.many', 'edge_tab.manual'];
        foreach (['en', 'es', 'de', 'fr', 'it', 'nl', 'pt'] as $locale) {
            /** @var array<string, string> $catalogue */
            $catalogue = Yaml::parseFile($root.'/src/Resources/translations/NowoUiKitBundle.'.$locale.'.yaml');
            foreach ($keys as $key) {
                self::assertNotEmpty($catalogue[$key] ?? null, $locale.' '.$key);
            }
            self::assertStringContainsString('%count%', $catalogue['filters_collapse.many']);
        }
    }

    public function testKitchenSinkShowsTheHostChrome(): void
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['base.html.twig' => '{% block stylesheets %}{% endblock %}{% block body %}{% endblock %}{% block javascripts %}{% endblock %}']),
            $fs,
        ]), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addFunction(new \Twig\TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/bundles/nowouikit/'.$path));
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $name, array $params = []): string => '/'.$name));
        $twig->addGlobal('nowo_ui_kit_css_framework', 'bootstrap5');
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');
        $twig->addGlobal('nowo_ui_kit_row_actions_display', 'icon');
        $twig->addGlobal('app', ['request' => new Request([], [], ['_route' => 'nowo_ui_kit_kitchen_sink']), 'flashes' => []]);
        // The demo app registers the bundle: the 2.x partials (menu, segmented) read the theme functions.
        $twig->addExtension(new ThemeExtension(new Theme()));

        $html = $twig->render('@NowoUiKitBundle/demo/kitchen_sink.html.twig');
        foreach (['data-nowo-ui-popover-menu', 'popovertarget="demo-kebab-popover"', 'data-nowo-ui-segmented', 'js/nowo-ui-popover.js', 'js/nowo-ui-segmented.js', 'js/nowo-ui-tabs.js', 'id="demo-locale-tabs"', 'id="tab-demo-locale-tabs-en"', 'id="host-chrome"', 'data-controller="filters-collapse"', 'data-controller="responsive-table"', 'aria-labelledby="kitchen-sink-rt-title"',
            'nowo-ui-row-detail-cell', 'nowo-ui-corner-tab', 'nowo-ui-disclosure-icon', 'nowo-ui-edge-tab__link', 'bi bi-x-circle'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
    }

    /**
     * @param list<array{name: string, value: mixed, prefixes: list<string>, checked?: bool, expanded?: bool}> $fields
     */
    private function formView(array $fields): FormView
    {
        $view = new FormView();
        foreach ($fields as $field) {
            $child = new FormView($view);
            $child->vars['name'] = $field['name'];
            $child->vars['value'] = $field['value'];
            $child->vars['block_prefixes'] = $field['prefixes'];
            if (isset($field['checked'])) {
                $child->vars['checked'] = $field['checked'];
            }
            if (isset($field['expanded'])) {
                $child->vars['expanded'] = $field['expanded'];
            }
            $view->children[$field['name']] = $child;
        }

        return $view;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render(string $template, array $vars = [], ?Request $request = null): string
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader(['t.twig' => $template]), $fs]), ['strict_variables' => false]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addGlobal('nowo_ui_kit_css_framework', 'custom');
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');
        if ($request instanceof Request) {
            $twig->addGlobal('app', ['request' => $request]);
        }

        return trim($twig->render('t.twig', $vars));
    }

    private function renderIcon(string $name, string $set): string
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader(['t.twig' => "{% include '@NowoUiKitBundle/components/_icon.html.twig' with {name: name} only %}"]), $fs]));
        $twig->addGlobal('nowo_ui_kit_icon_set', $set);

        return trim($twig->render('t.twig', ['name' => $name]));
    }
}
