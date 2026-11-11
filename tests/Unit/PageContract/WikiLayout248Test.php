<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * 2.48 (Beacon 1.149 Wiki page view): side panel `drawer_below` + `persist`, the `rail_toggle` partial, page header
 * `tabs_inline`, the reading width token and the overflow helpers. Every option is opt-in: Layout248BcTest pins the
 * output without them to UiKit 2.47.0.
 */
final class WikiLayout248Test extends TestCase
{
    private const PANEL = '@NowoUiKitBundle/partials/_side_panel.html.twig';
    private const CONTAINER = '@NowoUiKitBundle/partials/_side_panel_container.html.twig';
    private const RAIL = '@NowoUiKitBundle/partials/_rail_toggle.html.twig';
    private const HEADER = '@NowoUiKitBundle/partials/_page_header.html.twig';

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function drawerBelow(): iterable
    {
        yield 'lg' => ['lg', ' nowo-ui-side-panel--drawer-lg" data-nowo-ui-side-panel data-side="end" data-layout="inline" aria-labelledby="c-title" data-nowo-ui-side-panel-opened="Comments opened" data-nowo-ui-side-panel-closed="Comments closed" hidden data-drawer-below="lg">'];
        yield 'xl' => ['xl', ' nowo-ui-side-panel--drawer-xl" data-nowo-ui-side-panel data-side="end" data-layout="inline" aria-labelledby="c-title" data-nowo-ui-side-panel-opened="Comments opened" data-nowo-ui-side-panel-closed="Comments closed" hidden data-drawer-below="xl">'];
    }

    #[DataProvider('drawerBelow')]
    public function testDrawerBelowRendersTheModifierAndTheBreakpoint(string $value, string $expected): void
    {
        $html = $this->render(self::PANEL, ['id' => 'c', 'title' => 'Comments', 'drawer_below' => $value]);

        self::assertStringContainsString('class="nowo-ui-side-panel nowo-ui-side-panel--end nowo-ui-side-panel--inline'.$expected, $html);
    }

    public function testDrawerBelowMdOrAnInvalidValueRendersThe247Markup(): void
    {
        $plain = $this->render(self::PANEL, ['id' => 'c', 'title' => 'Comments']);
        foreach (['md', 'sm', '2xl', '', null, 7, ['lg']] as $value) {
            self::assertSame($plain, $this->render(self::PANEL, ['id' => 'c', 'title' => 'Comments', 'drawer_below' => $value]), var_export($value, true));
        }
        self::assertStringNotContainsString('drawer', $plain);
    }

    public function testPersistKeyIsValidatedAndEscapedNowhere(): void
    {
        $html = $this->render(self::PANEL, ['id' => 'c', 'title' => 'Comments', 'persist' => 'wiki-comments', 'drawer_below' => 'lg']);
        self::assertStringContainsString(' data-drawer-below="lg" data-nowo-ui-side-panel-persist="wiki-comments">', $html);

        $plain = $this->render(self::PANEL, ['id' => 'c', 'title' => 'Comments']);
        foreach (['bad key', 'x"onmouseover="1', str_repeat('a', 65), '', ['k'], null] as $value) {
            self::assertSame($plain, $this->render(self::PANEL, ['id' => 'c', 'title' => 'Comments', 'persist' => $value]), var_export($value, true));
        }
        self::assertStringContainsString('data-nowo-ui-side-panel-persist="Space:1.tree_rail"', $this->render(self::PANEL, ['id' => 'c', 'title' => 'C', 'persist' => 'Space:1.tree_rail']));
    }

    public function testTheContainerFollowsTheBreakpoint(): void
    {
        $twig = PageContractTwig::create(['c' => "{% embed '".self::CONTAINER."' with {drawer_below: bp, attr: {class: 'x', 'data-testid': 'layout'}} only %}{% block nowo_ui_side_panel_main %}M{% endblock %}{% endembed %}"]);

        self::assertStringContainsString('<div class="nowo-ui-side-panel-container nowo-ui-side-panel-container--drawer-xl x" data-nowo-ui-side-panel-container data-drawer-below="xl" data-testid="layout">', $twig->render('c', ['bp' => 'xl']));
        self::assertStringContainsString('<div class="nowo-ui-side-panel-container x" data-nowo-ui-side-panel-container data-testid="layout">', $twig->render('c', ['bp' => 'md']));
    }

    public function testRailToggleAsAButton(): void
    {
        $html = $this->render(self::RAIL, ['target' => 'tree-body', 'title' => 'Pages']);

        self::assertStringStartsWith('<button type="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-rail-toggle btn btn-sm btn-outline-secondary nowo-ui-rail-toggle--start" aria-controls="tree-body" aria-expanded="true" data-nowo-ui-rail-toggle data-nowo-ui-rail-label-collapse="Collapse Pages" data-nowo-ui-rail-label-expand="Expand Pages">', $html);
        self::assertStringContainsString('<span class="nowo-ui-rail-toggle__icon" aria-hidden="true">', $html);
        self::assertStringContainsString('<span class="visually-hidden" data-nowo-ui-rail-toggle-label>Collapse Pages</span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-rail-toggle__title" aria-hidden="true">Pages</span>', $html);
        self::assertStringEndsWith('</button>', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertStringNotContainsString(' on', str_replace(['nowo-ui-', 'data-nowo'], '', $html));
    }

    public function testRailToggleAsALinkCollapsedWithAHostStoreAndAShortcut(): void
    {
        $html = $this->render(self::RAIL, [
            'target' => 'tree-body',
            'title' => new Label('wiki.tree.title'),
            'collapsed' => true,
            'href' => '?tree=full',
            'side' => 'end',
            'labels' => ['collapse' => 'Hide', 'expand' => 'Show'],
            'show_title' => false,
            'persist' => 'wiki-tree',
            'state_url' => '/spaces/a/panel-state',
            'state_token' => 'tok',
            'state_panel' => 'tree_rail',
            'shortcut' => 'Alt+Shift+P',
            'attr' => ['data-testid' => 'rail', 'class' => 'extra'],
        ]);

        self::assertStringStartsWith('<a href="?tree=full" role="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-rail-toggle btn btn-sm btn-outline-secondary nowo-ui-rail-toggle--end extra" aria-controls="tree-body" aria-expanded="false" data-nowo-ui-rail-toggle data-nowo-ui-rail-label-collapse="Hide" data-nowo-ui-rail-label-expand="Show" data-nowo-ui-rail-persist="wiki-tree" data-nowo-ui-rail-state-url="/spaces/a/panel-state" data-nowo-ui-rail-state-token="tok" data-nowo-ui-rail-state-panel="tree_rail" aria-keyshortcuts="Alt+Shift+P" data-nowo-ui-rail-shortcut="Alt+Shift+P" data-testid="rail">', $html);
        self::assertStringContainsString('<span class="visually-hidden" data-nowo-ui-rail-toggle-label>Show</span>', $html);
        self::assertStringNotContainsString('nowo-ui-rail-toggle__title', $html);
        self::assertStringEndsWith('</a>', $html);

        // Invalid keys, sides and a state URL without token / panel.
        $odd = $this->render(self::RAIL, ['target' => 't', 'title' => '<b>P</b>', 'side' => 'left', 'persist' => 'a b', 'collapsed' => 'yes', 'state_url' => '/s']);
        self::assertStringContainsString('nowo-ui-rail-toggle--start" aria-controls="t" aria-expanded="true"', $odd);
        self::assertStringContainsString('data-nowo-ui-rail-label-expand="Expand &lt;b&gt;P&lt;/b&gt;" data-nowo-ui-rail-state-url="/s">', $odd);
        self::assertStringNotContainsString('data-nowo-ui-rail-persist', $odd);
        self::assertStringNotContainsString('<b>', $odd);
    }

    public function testRailToggleRefusesInlineHandlers(): void
    {
        $this->expectExceptionMessage('inline event handler attribute "onclick" is not allowed');
        $this->render(self::RAIL, ['target' => 't', 'title' => 'P', 'attr' => ['onclick' => 'x()']]);
    }

    public function testRailToggleIsAThemeSlotAndItsLabelsExistInEveryLocale(): void
    {
        self::assertSame('@NowoUiKitBundle/partials/_rail_toggle.html.twig', ThemeSlots::PARTIALS['rail_toggle']);
        self::assertSame('btn btn-sm btn-outline-secondary', ThemeSlots::SLOTS['rail_toggle'][1]['bootstrap']);
        foreach (['en', 'es', 'fr', 'de', 'it', 'nl', 'pt'] as $locale) {
            $messages = Yaml::parseFile(PageContractTwig::root().'/src/Resources/translations/NowoUiKitBundle.'.$locale.'.yaml');
            self::assertIsArray($messages);
            foreach (['rail.collapse', 'rail.expand'] as $id) {
                self::assertArrayHasKey($id, $messages, $locale.' '.$id);
                self::assertStringContainsString('%title%', (string) $messages[$id], $locale.' '.$id);
            }
        }
        $es = PageContractTwig::create(locale: 'es')->render(self::RAIL, ['target' => 't', 'title' => 'Páginas']);
        self::assertStringContainsString('data-nowo-ui-rail-label-collapse="Contraer Páginas" data-nowo-ui-rail-label-expand="Expandir Páginas"', $es);
    }

    public function testTabsInlinePutsTabsAndActionsOnOneRow(): void
    {
        $header = $this->header();
        $html = $this->render(self::HEADER, ['header' => $header, 'tabs_inline' => true]);

        self::assertStringContainsString(' nowo-ui-page-header--tabs-inline"', $html);
        $bar = substr($html, (int) strpos($html, '<div class="nowo-ui-page-header__bar">'));
        self::assertNotSame('', $bar);
        self::assertLessThan(strpos($bar, '/pages/1/edit'), strpos($bar, 'nowo-ui-page-header__nav'), 'tabs first, then the toolbar');
        self::assertStringContainsString('aria-current="page"', $bar);
        self::assertStringContainsString('data-nowo-ui-region="area-nav"', $bar);
        self::assertSame(1, substr_count($html, '/pages/1/edit'));
        self::assertSame(1, substr_count($html, 'nowo-ui-page-header__nav'));

        // Split actions and a pre-rendered toolbar (the contract) stay in the bar too.
        $split = $this->render(self::HEADER, ['header' => $header->withSplitActions(), 'tabs_inline' => true]);
        self::assertStringContainsString('nowo-ui-header-actions', substr($split, (int) strpos($split, 'nowo-ui-page-header__bar')));
        $contract = PageContractTwig::create()->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'tabs_inline' => true]);
        self::assertStringContainsString('/pages/1/edit', substr($contract, (int) strpos($contract, 'nowo-ui-page-header__bar')));
        $fn = PageContractTwig::create(['f' => '{{ nowo_ui_page_header(header, {tabs_inline: true}) }}'])->render('f', ['header' => $header]);
        self::assertStringContainsString('nowo-ui-page-header__bar', $fn);
        $toolbarOnly = $this->render(self::HEADER, ['header' => $header->withActions([]), 'toolbar' => '<em>T</em>', 'tabs_inline' => true]);
        self::assertStringContainsString('<em>T</em></div>', substr($toolbarOnly, (int) strpos($toolbarOnly, 'nowo-ui-page-header__bar')));
        $none = $this->render(self::HEADER, ['header' => $header->withActions([]), 'tabs_inline' => true]);
        self::assertStringNotContainsString('nowo-ui-toolbar', $none);
    }

    public function testTabsInlineNeedsTabsAndTrue(): void
    {
        $noTabs = $this->header()->withTabs(null);
        self::assertSame($this->render(self::HEADER, ['header' => $noTabs]), $this->render(self::HEADER, ['header' => $noTabs, 'tabs_inline' => true]));
        self::assertSame($this->render(self::HEADER, ['header' => $this->header()]), $this->render(self::HEADER, ['header' => $this->header(), 'tabs_inline' => 'yes']));
        $fn = PageContractTwig::create(['f' => '{{ nowo_ui_page_header(header, {tabs_inline: 1}) }}', 'g' => '{{ nowo_ui_page_header(header) }}']);
        self::assertSame($fn->render('g', ['header' => $this->header()]), $fn->render('f', ['header' => $this->header()]));
    }

    public function testCssRules(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');

        self::assertStringContainsString("--nowo-ui-reading-width: 75ch;\n}", $css);
        self::assertStringContainsString("@media (max-width: 1199px) {\n  .nowo-ui-side-panel-container:is(.nowo-ui-side-panel-container--drawer-lg, .nowo-ui-side-panel-container--drawer-xl, :has(> .nowo-ui-side-panel--drawer-lg, > .nowo-ui-side-panel--drawer-xl)) {\n    display: block;", $css);
        self::assertStringContainsString("@media (max-width: 1439px) {\n  .nowo-ui-side-panel-container:is(.nowo-ui-side-panel-container--drawer-xl", $css);
        self::assertStringContainsString("[data-nowo-ui-rail-host][data-nowo-ui-rail='collapsed'] [data-nowo-ui-rail-body] {\n  display: none !important;", $css);
        self::assertStringContainsString('inline-size: var(--nowo-ui-rail-width, 2.75rem);', $css);
        self::assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s+\[data-nowo-ui-rail-host\]:not\(\[data-motion=\'reduce\'\] \*\) \{\s+transition:/', $css);
        self::assertStringContainsString(":where(.nowo-ui-readable) {\n  box-sizing: border-box;\n  inline-size: 100%;\n  max-inline-size: var(--nowo-ui-reading-width, 75ch);", $css);
        $notEditable = ":not(:where([contenteditable]:not([contenteditable='false'])), :where([contenteditable]:not([contenteditable='false'])) *)";
        self::assertStringContainsString(':where([data-nowo-ui-scroll-region])'.$notEditable." {\n  max-inline-size: 100%;\n  overflow-x: auto;", $css);
        self::assertStringContainsString(':where(.nowo-ui-rendered-content, [data-nowo-ui-rendered-content]) :where(pre)'.$notEditable, $css);
        self::assertStringContainsString(':where(.nowo-ui-rendered-content, [data-nowo-ui-rendered-content]) :where(table)'.$notEditable." {\n  display: block;", $css);
        self::assertStringContainsString(":where(.nowo-ui-grid-fit) {\n  display: grid;\n  grid-template-columns: minmax(0, 1fr);", $css);
        self::assertStringContainsString(":where(.nowo-ui-grid-fit) > * {\n  min-inline-size: 0;", $css);
        self::assertStringContainsString(".nowo-ui-page-header__bar {\n  flex: 1 0 100%;", $css);
    }

    private function header(): PageHeader
    {
        return new PageHeader(
            'Getting started',
            actions: [Action::edit('/pages/1/edit', 'Edit')],
            tabs: new AreaNav([new AreaNavItem('Page', '/pages/1', true), new AreaNavItem('History', '/pages/1/history')], 'Page sections'),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        return trim(PageContractTwig::create()->render($template, $context));
    }
}
