<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.5: the `locale_tabs` partial (no-JS stacked fieldsets upgraded to WAI-ARIA tabs by nowo-ui-tabs.js) and the
 * `tab_panel` slot.
 */
final class LocaleTabsPartialTest extends TestCase
{
    private const TPL = "{{ include(nowo_ui_partial('locale_tabs'), vars, false) }}";

    public function testRegistered(): void
    {
        self::assertSame('@NowoUiKitBundle/partials/_locale_tabs.html.twig', ThemeSlots::PARTIALS['locale_tabs']);
        self::assertSame('nowo-ui-tabs__panel', ThemeSlots::semantic('tab_panel'));
        self::assertContains('tabs', ThemeSlots::SCRIPTS);
    }

    #[RequiresPhpExtension('intl')] // language names ("English")
    public function testProgressiveMarkupRendersStackedFieldsetsWithAHiddenTabList(): void
    {
        $c = $this->crawl(['id' => 'lt', 'panels' => $this->panels(), 'default_locale' => 'en']);

        $root = $c->filter('#lt');
        self::assertSame('nowo-ui-locale-tabs', $root->attr('class'));
        foreach (['data-nowo-ui-locale-tabs', 'data-nowo-ui-tabs', 'data-nowo-ui-tabs-upgrade'] as $attr) {
            self::assertNotNull($root->attr($attr), $attr);
        }
        self::assertSame('lt-en', $root->attr('data-nowo-ui-tabs-active'));
        self::assertSame('nowo-ui-tabs__item nav-link', $root->attr('data-nowo-ui-tabs-tab-class'));
        self::assertSame('nowo-ui-tabs__item nowo-ui-tabs__item--active nav-link active', $root->attr('data-nowo-ui-tabs-tab-active-class'));
        self::assertNull($root->attr('data-controller'));

        $list = $c->filter('[role="tablist"]');
        self::assertSame(['Languages', 'nowo-ui-tabs nav nav-tabs mb-3 nowo-ui-locale-tabs__nav'], [$list->attr('aria-label'), $list->attr('class')]);
        self::assertNotNull($list->attr('hidden'));
        self::assertNotNull($list->attr('data-nowo-ui-tabs-list'));

        // Default locale first, then the given order.
        $tabs = $c->filter('button[role="tab"]');
        self::assertSame(['en', 'es', 'ar'], $tabs->each(static fn (Crawler $t): string => (string) $t->attr('data-locale')));
        self::assertSame(['true', 'false', 'false'], $tabs->each(static fn (Crawler $t): string => (string) $t->attr('aria-selected')));
        self::assertSame(['0', '-1', '-1'], $tabs->each(static fn (Crawler $t): string => (string) $t->attr('tabindex')));
        self::assertSame(['tab-lt-en', 'tab-lt-en-panel', 'lt-en', 'button'], [$tabs->eq(0)->attr('id'), $tabs->eq(0)->attr('aria-controls'), $tabs->eq(0)->attr('data-nowo-ui-tab-id'), $tabs->eq(0)->attr('type')]);
        self::assertSame('nowo-ui-tabs__item nowo-ui-tabs__item--active nav-link active', $tabs->eq(0)->attr('class'));
        self::assertSame('nowo-ui-tabs__item nav-link', $tabs->eq(1)->attr('class'));
        self::assertSame('EN', $tabs->eq(0)->filter('.nowo-ui-locale-tabs__code')->text());
        self::assertSame('(English, default language)', $tabs->eq(0)->filter('.visually-hidden')->text());
        self::assertSame('★', $tabs->eq(0)->filter('.nowo-ui-locale-tabs__marker--default[aria-hidden="true"]')->text());
        self::assertNotNull($tabs->eq(0)->attr('data-default'));
        self::assertNull($tabs->eq(1)->attr('data-default'));

        // Panels: visible (no hidden, no role until upgraded), lang / dir, fieldset + legend, Markup content as is.
        $panels = $c->filter('[data-nowo-ui-tabs-panel]');
        self::assertCount(3, $panels);
        self::assertSame(['tab-lt-en-panel', 'tab-lt-es-panel', 'tab-lt-ar-panel'], $panels->each(static fn (Crawler $p): string => (string) $p->attr('id')));
        self::assertSame([null, null, null], $panels->each(static fn (Crawler $p): ?string => $p->attr('hidden')));
        self::assertSame([null, null, null], $panels->each(static fn (Crawler $p): ?string => $p->attr('role')));
        self::assertSame(['ltr', 'ltr', 'rtl'], $panels->each(static fn (Crawler $p): string => (string) $p->attr('dir')));
        self::assertSame(['en', 'es', 'ar'], $panels->each(static fn (Crawler $p): string => (string) $p->attr('lang')));
        self::assertSame('nowo-ui-tabs__panel nowo-ui-locale-tabs__panel', $panels->eq(0)->attr('class'));
        self::assertSame('English EN★ (default language)', $panels->eq(0)->filter('fieldset > legend.nowo-ui-locale-tabs__legend')->text());
        self::assertSame('Spanish ES', $panels->eq(1)->filter('legend')->text());
        self::assertSame('Título', $panels->eq(1)->filter('label')->text());

        $html = $this->render(['id' => 'lt', 'panels' => $this->panels()]);
        self::assertStringContainsString('&lt;b&gt;escaped&lt;/b&gt;', $html, 'plain string content is escaped');
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $html);
    }

    #[RequiresPhpExtension('intl')] // language names ("English")
    public function testErrorsRequiredMarkersAndActivation(): void
    {
        $panels = $this->panels();
        $panels[0]['invalid'] = true;
        $c = $this->crawl(['id' => 'e', 'panels' => $panels, 'required_locales' => ['en', 'es'], 'default_locale' => 'en']);

        // The first invalid panel opens when the active one is valid.
        self::assertSame('e-es', $c->filter('#e')->attr('data-nowo-ui-tabs-active'));
        $es = $c->filter('#tab-e-es');
        self::assertSame('true', $es->attr('aria-selected'));
        self::assertNotNull($es->attr('data-invalid'));
        self::assertNotNull($es->attr('data-required'));
        self::assertSame('*', $es->filter('.nowo-ui-locale-tabs__marker--required')->text());
        self::assertSame('!', $es->filter('.nowo-ui-locale-tabs__marker--invalid')->text());
        self::assertSame('(Spanish, required, contains errors)', $es->filter('.visually-hidden')->text());
        self::assertNotNull($c->filter('#tab-e-es-panel')->attr('data-invalid'));
        self::assertSame('(default language, required)', $c->filter('#tab-e-en-panel legend .visually-hidden')->text());
        self::assertNull($c->filter('#tab-e-ar')->attr('data-required'));

        // An explicitly active invalid panel stays; activate_invalid: false keeps the default / request locale.
        $panels[2]['invalid'] = true;
        self::assertSame('e-ar', $this->crawl(['id' => 'e', 'panels' => $panels, 'active_locale' => 'ar'])->filter('#e')->attr('data-nowo-ui-tabs-active'));
        self::assertSame('e-en', $this->crawl(['id' => 'e', 'panels' => $panels, 'activate_invalid' => false])->filter('#e')->attr('data-nowo-ui-tabs-active'));

        // Per-panel `required` wins over required_locales; custom markers; no default marker.
        $panels = $this->panels();
        $panels[1]['required'] = false;
        $panels[2]['required'] = true;
        $c = $this->crawl(['id' => 'm', 'panels' => $panels, 'required_locales' => ['en'], 'mark_default' => false, 'required_marker' => 'R', 'default_marker' => 'D', 'error_marker' => 'X']);
        self::assertCount(0, $c->filter('#tab-m-en .nowo-ui-locale-tabs__marker'));
        self::assertSame('R', $c->filter('#tab-m-ar .nowo-ui-locale-tabs__marker--required')->text());
        self::assertCount(0, $c->filter('[data-default]'));
    }

    public function testRequestLocaleOpensWhenListedAndUnknownActiveFallsBack(): void
    {
        $request = Request::create('/');
        $request->setLocale('es');
        $html = PageContractTwig::create(['t' => self::TPL], request: $request, locale: 'es')->render('t', ['vars' => ['id' => 'r', 'panels' => $this->panels()]]);
        $c = new Crawler($html);
        self::assertSame('r-es', $c->filter('#r')->attr('data-nowo-ui-tabs-active'));
        // Names in the request locale, translated UI strings.
        self::assertSame('Idiomas', $c->filter('[role="tablist"]')->attr('aria-label'));
        self::assertStringEndsWith(', idioma predeterminado)', $c->filter('#tab-r-es .visually-hidden')->text());

        self::assertSame('x-en', $this->crawl(['id' => 'x', 'panels' => $this->panels(), 'active_locale' => 'zz'])->filter('#x')->attr('data-nowo-ui-tabs-active'));
    }

    public function testStaticModeLabelsStimulusAttributesAndBundleSlots(): void
    {
        $panels = [
            ['locale' => 'es', 'content' => 'Hola', 'label' => new Label('lang.es', 'messages'), 'name' => 'Castellano', 'dir' => 'rtl', 'attr' => ['data-testid' => 'p-es']],
            ['locale' => 'pt_BR', 'content' => 'Olá'],
        ];
        $html = PageContractTwig::create(['t' => self::TPL], [
            'classes' => ['tab_panel' => 'app-panel', 'tabs' => 'app-tabs'],
            'attributes' => ['tab_panel' => ['data-x' => 'y']],
        ])->render('t', ['vars' => [
            'id' => 's', 'panels' => $panels, 'progressive' => false, 'legend' => false, 'default_first' => false,
            'default_locale' => 'pt_BR', 'active_locale' => 'es', 'stimulus' => 'tabs', 'label' => new Label('locale_tabs.label', 'NowoUiKitBundle'),
            'attr' => ['data-testid' => 'tabs', 'class' => 'ignored-by-nothing'],
        ]]);
        $c = new Crawler($html);

        $root = $c->filter('#s');
        self::assertNull($root->attr('data-nowo-ui-tabs-upgrade'));
        self::assertSame(['tabs', 's-es', 'tabs'], [$root->attr('data-controller'), $root->attr('data-tabs-active-tab-value'), $root->attr('data-testid')]);
        $list = $c->filter('[role="tablist"]');
        self::assertNull($list->attr('hidden'));
        self::assertSame('list', $list->attr('data-tabs-target'));
        self::assertStringContainsString('app-tabs', (string) $list->attr('class'));
        self::assertSame('Languages', $list->attr('aria-label'));

        $tabs = $c->filter('[role="tab"]');
        self::assertSame(['es', 'pt_BR'], $tabs->each(static fn (Crawler $t): string => (string) $t->attr('data-locale')), 'default_first: false keeps the order');
        self::assertSame('lang.es', $tabs->eq(0)->filter('.nowo-ui-locale-tabs__code')->text());
        self::assertSame('(Castellano)', $tabs->eq(0)->filter('.visually-hidden')->text());
        self::assertSame(['click->tabs#open', 'trigger', 's-es'], [$tabs->eq(0)->attr('data-action'), $tabs->eq(0)->attr('data-tabs-target'), $tabs->eq(0)->attr('data-tab-id')]);
        self::assertNotNull($tabs->eq(1)->attr('data-default'));

        $panel = $c->filter('#tab-s-es-panel');
        self::assertSame(['tabpanel', 'tab-s-es', 'rtl', 'tab', 'p-es', 'y', 'active'], [$panel->attr('role'), $panel->attr('aria-labelledby'), $panel->attr('dir'), $panel->attr('data-tabs-target'), $panel->attr('data-testid'), $panel->attr('data-x'), $panel->attr('data-state')]);
        self::assertStringContainsString('app-panel', (string) $panel->attr('class'));
        self::assertNull($panel->attr('hidden'));
        $pt = $c->filter('#tab-s-pt_BR-panel');
        self::assertNotNull($pt->attr('hidden'));
        self::assertSame(['inactive', 'pt-BR'], [$pt->attr('data-state'), $pt->attr('lang')]);
        self::assertCount(0, $c->filter('legend'));
    }

    public function testRandomIdAndEmptyPanels(): void
    {
        $c = $this->crawl(['panels' => [['locale' => 'en', 'content' => 'x'], ['locale' => 'zz', 'content' => 'y']]]);
        self::assertMatchesRegularExpression('/^nowo-ui-locale-tabs-\d+$/', (string) $c->filter('.nowo-ui-locale-tabs')->attr('id'));
        // A locale without a known name shows its code once.
        self::assertSame('ZZ', $c->filter('legend')->eq(1)->text());
        self::assertCount(0, $c->filter('[data-locale="zz"] .visually-hidden'));

        $empty = $this->crawl(['id' => 'z', 'panels' => []]);
        self::assertCount(0, $empty->filter('[role="tab"]'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function panels(): array
    {
        return [
            ['locale' => 'es', 'content' => new \Twig\Markup('<label for="t-es">Título</label><input id="t-es" name="t[es]">', 'UTF-8')],
            ['locale' => 'en', 'content' => new \Twig\Markup('<input name="t[en]">', 'UTF-8')],
            ['locale' => 'ar', 'content' => '<b>escaped</b>'],
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render(array $vars): string
    {
        return PageContractTwig::create(['t' => self::TPL])->render('t', ['vars' => $vars]);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function crawl(array $vars): Crawler
    {
        return new Crawler($this->render($vars));
    }
}
