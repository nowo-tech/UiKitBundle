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
