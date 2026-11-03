<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * 2.24: the `tree` partial (APG navigation treeview markup) and nowo_ui_tree_has_current().
 */
final class TreePartialTest extends TestCase
{
    public function testTheTreeMarkupFollowsTheTreeviewPattern(): void
    {
        $html = $this->render([
            ['id' => 'a', 'label' => 'Alpha <b>', 'href' => '/a', 'expanded' => true, 'icon' => '📘', 'badge' => '2', 'children' => [
                ['id' => 'a1', 'label' => 'Apple', 'href' => '/a1', 'current' => true],
                ['id' => 'a2', 'label' => 'Avocado', 'href' => '/a2', 'has_children' => true, 'children_url' => '/children?parent=a2'],
            ]],
            ['id' => 'b', 'label' => 'Beta', 'href' => '/b'],
        ], ['movable' => true, 'attr' => ['id' => 'pages', 'data-testid' => 'pages']]);

        self::assertStringContainsString('<ul class="nowo-ui-tree" id="pages" role="tree" aria-label="Pages" data-nowo-ui-tree data-nowo-ui-tree-movable data-testid="pages">', $html);
        self::assertStringContainsString('<li class="nowo-ui-tree__item" role="none" data-nowo-ui-tree-node="a">', $html);
        self::assertStringContainsString('href="/a" role="treeitem" data-nowo-ui-tree-item tabindex="-1" aria-level="1" aria-setsize="2" aria-posinset="1" aria-expanded="true" aria-owns="pages-a-group" draggable="true"', $html);
        self::assertStringContainsString('Alpha &lt;b&gt;', $html);
        self::assertStringContainsString('<span class="nowo-ui-tree__icon" aria-hidden="true">📘</span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-tree__badge">2</span>', $html);
        self::assertStringContainsString('<ul class="nowo-ui-tree__group" role="group" id="pages-a-group">', $html);
        self::assertStringContainsString('href="/a1" role="treeitem" data-nowo-ui-tree-item tabindex="0" aria-level="2" aria-setsize="2" aria-posinset="1" aria-current="page"', $html);
        self::assertStringContainsString('aria-expanded="false" aria-owns="pages-a2-group"', $html);
        self::assertStringContainsString('<ul class="nowo-ui-tree__group" role="group" id="pages-a2-group" hidden data-nowo-ui-tree-children-url="/children?parent=a2">', $html);
        self::assertStringContainsString('<span class="nowo-ui-tree__spacer" aria-hidden="true"></span>', $html);
        self::assertSame(2, substr_count($html, 'data-nowo-ui-tree-toggle'));
        self::assertSame(1, substr_count($html, 'tabindex="0"'));
    }

    public function testWithoutACurrentPageTheFirstItemTakesTheTabStop(): void
    {
        $html = $this->render([['id' => 'x', 'label' => 'X', 'href' => '/x'], ['id' => 'y', 'label' => 'Y', 'href' => '/y']]);
        self::assertStringContainsString('href="/x" role="treeitem" data-nowo-ui-tree-item tabindex="0"', $html);
        self::assertStringContainsString('href="/y" role="treeitem" data-nowo-ui-tree-item tabindex="-1"', $html);
        self::assertStringNotContainsString('draggable', $html);
        self::assertStringNotContainsString('data-nowo-ui-tree-movable', $html);
    }

    public function testGroupIdsAreUniquePerTreeAndSanitised(): void
    {
        $nodes = [['id' => 'a b/<c>', 'label' => 'A', 'href' => '/a', 'children' => [['id' => 'x', 'label' => 'X', 'href' => '/x']]]];
        $one = $this->render($nodes, ['attr' => ['id' => 'left']]);
        $two = $this->render($nodes, ['attr' => ['id' => 'right']]);
        self::assertStringContainsString('aria-owns="left-a_b__c_-group"', $one);
        self::assertStringContainsString('id="left-a_b__c_-group"', $one);
        self::assertStringContainsString('aria-owns="right-a_b__c_-group"', $two);
        // Without an id each tree gets a generated one.
        preg_match('/<ul class="nowo-ui-tree" id="([^"]+)"/', $this->render($nodes), $a);
        preg_match('/<ul class="nowo-ui-tree" id="([^"]+)"/', $this->render($nodes), $b);
        self::assertMatchesRegularExpression('/^nowo-ui-tree-\d{9}$/', $a[1] ?? '');
        self::assertNotSame($a[1] ?? '', $b[1] ?? '');
    }

    public function testTheAncestorsOfTheCurrentNodeAreExpanded(): void
    {
        $html = $this->render([['id' => 'a', 'label' => 'A', 'href' => '/a', 'children' => [
            ['id' => 'b', 'label' => 'B', 'href' => '/b', 'children' => [['id' => 'c', 'label' => 'C', 'href' => '/c', 'current' => true]]],
        ]]], ['attr' => ['id' => 't']]);
        self::assertStringContainsString('aria-expanded="true" aria-owns="t-a-group"', $html);
        self::assertStringContainsString('aria-expanded="true" aria-owns="t-b-group"', $html);
        self::assertStringNotContainsString(' hidden', $html, 'the current branch is shown without JavaScript');
        self::assertSame(1, substr_count($html, 'tabindex="0"'));
        self::assertStringContainsString('href="/c" role="treeitem" data-nowo-ui-tree-item tabindex="0"', $html);
    }

    public function testActionsTemplateIsIncludedPerNode(): void
    {
        $html = $this->render([['id' => 'x', 'label' => 'X', 'href' => '/x']], ['actions_template' => 'actions.twig']);
        self::assertStringContainsString('<button data-for="x"></button>', $html);
    }

    public function testHasCurrentLooksAtEveryDepth(): void
    {
        self::assertFalse(ThemeExtension::treeHasCurrent([]));
        self::assertFalse(ThemeExtension::treeHasCurrent(['junk', ['children' => [['current' => false]]]]));
        self::assertTrue(ThemeExtension::treeHasCurrent([['children' => [['children' => [['current' => true]]]]]]));
        self::assertSame('t-1-group', ThemeExtension::treeGroupId('t', 1));
    }

    /**
     * @param list<mixed>          $nodes
     * @param array<string, mixed> $options
     */
    private function render(array $nodes, array $options = []): string
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader([
            't.twig' => "{% include '@NowoUiKitBundle/partials/_tree.html.twig' with {nodes: nodes, label: 'Pages'}|merge(options) only %}",
            'actions.twig' => '<button data-for="{{ node.id }}"></button>',
        ]), $fs]), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new ThemeExtension(new Theme()));
        $twig->addGlobal('nowo_ui_kit_css_framework', 'custom');

        return trim($twig->render('t.twig', ['nodes' => $nodes, 'options' => $options]));
    }
}
