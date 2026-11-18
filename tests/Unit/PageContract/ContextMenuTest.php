<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Action;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * 2.54: context menus. The kebab / row_actions / menu partials mark their root with data-nowo-ui-context-menu-source
 * (theme.context_menus or the `context_menu` option); the scripts partial loads the opt-in `context-menu` runtime
 * with the theme flag. Off by default: the markup is unchanged.
 */
final class ContextMenuTest extends TestCase
{
    private const MARKER = 'data-nowo-ui-context-menu-source';

    public function testThemeFlagDefaultsToFalse(): void
    {
        self::assertFalse((new Theme())->contextMenus());
        self::assertFalse(Theme::fromConfig('bootstrap5', [])->contextMenus());
        self::assertTrue(Theme::fromConfig('bootstrap5', ['context_menus' => true])->contextMenus());
        self::assertTrue((new Theme(contextMenus: true))->contextMenus());
    }

    public function testKebabMarksItsRootWithTheOptionOrTheThemeFlag(): void
    {
        $tpl = "{% include '@NowoUiKitBundle/partials/_kebab.html.twig' with {items: [{label: 'Edit', href: '/e'}], popover: popover, context_menu: ctx} %}";

        foreach ([true, false] as $popover) {
            self::assertStringNotContainsString(self::MARKER, $this->render($tpl, ['popover' => $popover, 'ctx' => null]));

            $c = new Crawler($this->render($tpl, ['popover' => $popover, 'ctx' => true]));
            $root = $c->filter('[data-nowo-ui-kebab]');
            self::assertNotNull($root->attr(self::MARKER));
            self::assertSame('Actions', $root->attr('data-nowo-ui-context-menu-label'));

            self::assertCount(1, (new Crawler($this->render($tpl, ['popover' => $popover, 'ctx' => null], ['context_menus' => true])))->filter('['.self::MARKER.']'));
            self::assertStringNotContainsString(self::MARKER, $this->render($tpl, ['popover' => $popover, 'ctx' => false], ['context_menus' => true]));
        }
    }

    public function testKebabRendersSeparatorsAndRowsOfItems(): void
    {
        $tpl = "{% include '@NowoUiKitBundle/partials/_kebab.html.twig' with {popover: popover, items: [
            {row: [{label: '👍', attr: {'aria-label': 'React 👍', 'data-part': 'react'}}, {label: '…', current: true}], label: 'Quick reactions', attr: {'data-part': 'row', class: 'x-row'}},
            {separator: true, attr: {'data-part': 'sep', hidden: 'hidden', class: 'x-sep'}},
            {separator: true},
            {row: [{label: 'Alone'}]},
            {label: 'Reply', href: '/r'},
        ]} %}";

        foreach ([true, false] as $popover) {
            $c = new Crawler($this->render($tpl, ['popover' => $popover]));
            $menu = $c->filter('[role="menu"]');
            $row = $menu->filter('[data-nowo-ui-menu-row]')->first();
            self::assertSame('group', $row->attr('role'));
            self::assertSame('Quick reactions', $row->attr('aria-label'));
            self::assertSame('row', $row->attr('data-part'));
            self::assertSame('nowo-ui-kebab__row x-row', $row->attr('class'));
            self::assertCount(2, $row->filter('[role="menuitem"]'));
            self::assertSame('React 👍', $row->filter('[role="menuitem"]')->first()->attr('aria-label'));
            self::assertStringContainsString('is-current', (string) $row->filter('[role="menuitem"]')->last()->attr('class'));
            self::assertNull($menu->filter('[data-nowo-ui-menu-row]')->last()->attr('aria-label'));

            $sep = $menu->filter('hr');
            self::assertCount(2, $sep);
            self::assertSame('separator', $sep->first()->attr('role'));
            self::assertSame('nowo-ui-kebab__separator x-sep', $sep->first()->attr('class'));
            self::assertSame('hidden', $sep->first()->attr('hidden'));
            self::assertSame('sep', $sep->first()->attr('data-part'));
            self::assertSame('nowo-ui-kebab__separator', $sep->last()->attr('class'));

            self::assertSame(['👍', '…', 'Alone', 'Reply'], $menu->filter('[role="menuitem"]')->each(static fn (Crawler $n): string => trim($n->text())));
        }
    }

    public function testKebabSheetOptionMarksTheRoot(): void
    {
        $tpl = "{% include '@NowoUiKitBundle/partials/_kebab.html.twig' with {items: [{label: 'Edit'}], popover: popover, sheet: sheet} %}";

        foreach ([true, false] as $popover) {
            self::assertNotNull((new Crawler($this->render($tpl, ['popover' => $popover, 'sheet' => true])))->filter('[data-nowo-ui-kebab]')->attr('data-nowo-ui-sheet'));
            self::assertStringNotContainsString('data-nowo-ui-sheet', $this->render($tpl, ['popover' => $popover, 'sheet' => false]));
            self::assertStringNotContainsString('data-nowo-ui-sheet', $this->render("{% include '@NowoUiKitBundle/partials/_kebab.html.twig' with {items: [], popover: popover} %}", ['popover' => $popover]));
        }
    }

    public function testKebabLabelIsTranslated(): void
    {
        $html = PageContractTwig::create(['t' => "{% include '@NowoUiKitBundle/partials/_kebab.html.twig' with {items: [], context_menu: true} %}"], locale: 'es')->render('t');
        self::assertSame('Acciones', (new Crawler($html))->filter('[data-nowo-ui-kebab]')->attr('data-nowo-ui-context-menu-label'));
    }

    public function testRowActionsMarkTheirRootInBothModes(): void
    {
        $actions = [Action::create('/items/1', 'Open'), ['kind' => 'edit', 'href' => '/items/1/edit']];
        $tpl = "{{ include(nowo_ui_partial('row_actions'), {actions: actions, menu: menu, popover: true, context_menu: ctx}, false) }}";

        foreach ([true, false] as $menu) {
            self::assertStringNotContainsString(self::MARKER, $this->render($tpl, ['actions' => $actions, 'menu' => $menu, 'ctx' => null]));

            $c = new Crawler($this->render($tpl, ['actions' => $actions, 'menu' => $menu, 'ctx' => null], ['context_menus' => true]));
            $marked = $c->filter('['.self::MARKER.']');
            self::assertCount(1, $marked, 'only the row_actions root is marked, not the menu inside it');
            self::assertStringContainsString('nowo-ui-row-actions', (string) $marked->attr('class'));

            self::assertStringNotContainsString(self::MARKER, $this->render($tpl, ['actions' => $actions, 'menu' => $menu, 'ctx' => false], ['context_menus' => true]));
            self::assertStringContainsString(self::MARKER, $this->render($tpl, ['actions' => $actions, 'menu' => $menu, 'ctx' => true]));
        }
    }

    public function testMenuPartialIsOptInOnly(): void
    {
        $tpl = "{{ include(nowo_ui_partial('menu'), {items: [{label: 'Open', href: '/o'}], popover: popover, context_menu: ctx}, false) }}";

        foreach ([true, false] as $popover) {
            self::assertStringNotContainsString(self::MARKER, $this->render($tpl, ['popover' => $popover, 'ctx' => null], ['context_menus' => true]), 'the theme flag covers kebabs and row actions, not every menu');
            $c = new Crawler($this->render($tpl, ['popover' => $popover, 'ctx' => true]));
            self::assertSame('Actions', $c->filter('[data-nowo-ui-menu]')->attr('data-nowo-ui-context-menu-label'));
        }
    }

    public function testScriptsPartialLoadsTheRuntimeWithTheThemeFlag(): void
    {
        $tpl = "{{ include(nowo_ui_partial('scripts'), names is null ? {} : {script_names: names}) }}";
        $runtime = '/bundles/nowouikit/js/nowo-ui-context-menu.js';

        self::assertStringNotContainsString($runtime, $this->render($tpl, ['names' => null]));
        self::assertNotContains('context-menu', ThemeSlots::SCRIPTS, 'opt-in runtime');

        $on = $this->render($tpl, ['names' => null], ['context_menus' => true]);
        self::assertSame(1, substr_count($on, $runtime));
        self::assertSame(\count(ThemeSlots::SCRIPTS) + 1, substr_count($on, '<script '));

        self::assertSame(1, substr_count($this->render($tpl, ['names' => ['popover', 'context-menu']], ['context_menus' => true]), $runtime));
        self::assertSame(1, substr_count($this->render($tpl, ['names' => ['context-menu']]), $runtime));
    }

    /**
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $theme
     */
    private function render(string $template, array $vars = [], array $theme = []): string
    {
        return PageContractTwig::create(['t' => $template], $theme)->render('t', $vars);
    }
}
