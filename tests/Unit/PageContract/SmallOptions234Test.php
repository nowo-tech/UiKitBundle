<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use Twig\Environment;
use TypeError;

/**
 * 2.34: table `rowAttributes` and foot, tree `link_attr` / `item_attr` / `leading_template`, dialog `footer_attr` /
 * `header_attr` / `back_href`, `remote_form_dialog` `placement`, progress `value` + `max` / `label_visible` /
 * `meter_attr`. Default output: {@see SmallOptions234BcTest}.
 */
final class SmallOptions234Test extends TestCase
{
    private const PARTIALS = '@NowoUiKitBundle/partials/';

    /**
     * Theme with a host class and attribute on the slots these options merge with.
     */
    private const THEME = [
        'classes' => ['table_foot' => 'app-foot', 'dialog_back' => 'app-back', 'dialog_footer' => 'app-df'],
        'attributes' => ['dialog_footer' => ['data-host' => '1'], 'progress_meter' => ['data-host' => '1'], 'tree_link' => ['data-host' => '1']],
    ];

    private static function twig(array $templates = [], array $theme = []): Environment
    {
        return PageContractTwig::create($templates, $theme);
    }

    private static function table(?callable $rowAttributes = null): Table
    {
        return new Table([new Column('name', 'Name')], ['first' => ['name' => 'One'], 'second' => ['name' => 'Two']], label: 'Rows', rowAttributes: $rowAttributes);
    }

    // K3 — table

    public function testRowAttributesPerRowWithTheClassAfterTheSlot(): void
    {
        $html = self::twig()->render(self::PARTIALS.'_table.html.twig', ['table' => self::table(static fn (array $row, string $key): array => [
            'class' => 'list__row "x',
            'data-key' => $key,
            'data-name' => $row['name'],
            'data-skip' => null,
        ])]);

        self::assertStringContainsString('<tr class="nowo-ui-table__row list__row &quot;x" data-key="first" data-name="One">', $html);
        self::assertStringContainsString('<tr class="nowo-ui-table__row list__row &quot;x" data-key="second" data-name="Two">', $html);
        self::assertStringNotContainsString('data-skip', $html);
    }

    public function testRowAttributesReachTheListScaffold(): void
    {
        $page = new ListPage(new PageHeader('Rows'), [new Column('name', 'Name')], [['name' => 'One']], rowAttributes: static fn (): array => ['data-testid' => 'row']);
        $html = self::twig()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertStringContainsString('<tr class="nowo-ui-table__row" data-testid="row">', $html);
        self::assertStringNotContainsString('<tfoot', $html);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function badRowAttributes(): iterable
    {
        yield 'handler' => [['onclick' => 'x()']];
        yield 'javascript url' => [['data-url' => 'javascript:x()']];
        yield 'style' => [['style' => 'color:red']];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('badRowAttributes')]
    public function testRowAttributesAreChecked(array $attributes): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::table(static fn (): array => $attributes)->rowAttributes(['name' => 'One'], 'first');
    }

    public function testRowAttributesMustBeAnArray(): void
    {
        $this->expectException(TypeError::class);
        self::table(static fn (): string => 'class')->rowAttributes(['name' => 'One'], 'first');
    }

    public function testWithoutRowAttributesARowHasNone(): void
    {
        self::assertSame([], self::table()->rowAttributes(['name' => 'One'], 'first'));
    }

    public function testFootThroughEmbedWithFootAttr(): void
    {
        $template = <<<'TWIG'
{% embed nowo_ui_partial('table') with {table: table, foot_attr: {class: 'time-col', 'data-target': 'totals'}} %}
    {% block nowo_ui_table_foot %}<tr><th scope="row">Total {{ table.rows|length }}</th></tr>{% endblock %}
{% endembed %}
TWIG;
        $html = self::twig(['page' => $template], self::THEME)->render('page', ['table' => self::table()]);
        self::assertStringContainsString('</tbody><tfoot class="nowo-ui-table__foot app-foot time-col" data-target="totals"><tr><th scope="row">Total 2</th></tr></tfoot>', $html);
    }

    public function testAnEmptyFootPrintsNothing(): void
    {
        $template = "{% embed nowo_ui_partial('table') with {table: table} %}{% block nowo_ui_table_foot %}  \n  {% endblock %}{% endembed %}";
        $html = self::twig(['page' => $template])->render('page', ['table' => self::table()]);
        self::assertStringNotContainsString('<tfoot', $html);
    }

    public function testListScaffoldFootBlock(): void
    {
        $template = "{% extends '@NowoUiKit/page/list.html.twig' %}{% block nowo_ui_list_foot %}<tr><th>Sum</th></tr>{% endblock %}";
        $page = new ListPage(new PageHeader('Rows'), [new Column('name', 'Name')], [['name' => 'One']]);
        $html = self::twig(['page' => $template])->render('page', ['page' => $page]);
        self::assertStringContainsString('<tfoot class="nowo-ui-table__foot"><tr><th>Sum</th></tr></tfoot>', $html);
    }

    public function testFootAttrIsChecked(): void
    {
        $this->assertTwigRefuses(self::PARTIALS.'_table.html.twig', ['table' => self::table(), 'foot_html' => 'x', 'foot_attr' => ['onclick' => 'x()']]);
    }

    // K4 — tree

    public function testTreeNodeHooks(): void
    {
        $nodes = [['id' => 'a', 'label' => 'Alpha', 'href' => '/a', 'expanded' => true,
            'item_attr' => ['class' => 'subtree__item', 'data-task-id' => '7', 'role' => 'treeitem', 'data-nowo-ui-tree-node' => 'z'],
            'link_attr' => ['class' => 'subtree__title', 'data-task-panel-url' => '/panel/7', 'data-title' => 'JavaScript: "plan"', 'href' => '/evil', 'role' => 'button', 'TABINDEX' => '5', 'aria-level' => '9', 'draggable' => 'true'],
            'done' => true,
            'children' => [['id' => 'b', 'label' => 'Beta', 'href' => '/b', 'link_attr' => ['data-child' => '1']]],
        ]];
        $html = self::twig(['check' => '<i class="check">{{ node.done|default(false) ? "done" : "open" }}</i>'], self::THEME)->render(self::PARTIALS.'_tree.html.twig', [
            'nodes' => $nodes, 'label' => 'Tasks', 'leading_template' => 'check', 'attr' => ['id' => 't'],
        ]);

        self::assertStringContainsString('<li class="nowo-ui-tree__item subtree__item" role="none" data-nowo-ui-tree-node="a" data-task-id="7">', $html);
        self::assertStringContainsString('<a class="nowo-ui-tree__link subtree__title" href="/a" role="treeitem" data-nowo-ui-tree-item tabindex="0" aria-level="1" aria-setsize="1" aria-posinset="1" aria-expanded="true" aria-owns="t-a-group" data-host="1" data-task-panel-url="/panel/7" data-title="JavaScript: &quot;plan&quot;">', $html);
        self::assertStringContainsString('data-child="1"><i class="check">open</i><span class="nowo-ui-tree__label">Beta</span>', $html);
        self::assertStringContainsString('><i class="check">done</i><span class="nowo-ui-tree__label">Alpha</span>', $html);
        self::assertStringNotContainsString('/evil', $html);
        self::assertSame(1, substr_count($html, 'data-nowo-ui-tree-node="a"'));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function badNodeAttributes(): iterable
    {
        yield 'link handler' => ['link_attr', ['onclick' => 'x()']];
        yield 'link javascript' => ['link_attr', ['data-task-panel-url' => 'javascript:x()']];
        yield 'link reserved href, unsafe' => ['link_attr', ['href' => 'javascript:x()']];
        yield 'link style' => ['link_attr', ['style' => 'color:red']];
        yield 'item handler' => ['item_attr', ['onfocus' => 'x()']];
        yield 'item javascript' => ['item_attr', ['data-src' => ' JAVASCRIPT:x()']];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('badNodeAttributes')]
    public function testTreeNodeHooksAreChecked(string $option, array $attributes): void
    {
        $this->assertTwigRefuses(self::PARTIALS.'_tree.html.twig', ['label' => 'T', 'nodes' => [['id' => 'a', 'label' => 'A', 'href' => '/a', $option => $attributes]]]);
    }

    // K5 — dialog

    public function testDialogHooksMergeOverTheSlots(): void
    {
        $html = self::twig([], self::THEME)->render(self::PARTIALS.'_dialog.html.twig', [
            'id' => 'd', 'title' => 'T', 'footer' => 'F',
            'header_attr' => ['class' => 'dh', 'data-a' => '1'],
            'footer_attr' => ['class' => 'task-dialog__actions', 'data-host' => '2'],
        ]);
        self::assertStringContainsString('<header class="nowo-ui-dialog__header dh" data-a="1">', $html);
        self::assertStringContainsString('<footer class="nowo-ui-dialog__footer app-df task-dialog__actions" data-host="2">F</footer>', $html);
    }

    public function testFooterAttrOnTheFooterTargetKeepsItsContract(): void
    {
        $html = self::twig()->render(self::PARTIALS.'_dialog.html.twig', ['id' => 'd', 'title' => 'T', 'footer_target' => true, 'footer_attr' => ['class' => 'x', 'data-a' => '1', 'hidden' => false, 'data-nowo-ui-dialog-footer' => 'z']]);
        self::assertStringContainsString('<footer class="nowo-ui-dialog__footer x" data-nowo-ui-dialog-footer hidden data-a="1"></footer>', $html);
    }

    public function testBackArrowBeforeTheTitle(): void
    {
        $html = self::twig([], self::THEME)->render(self::PARTIALS.'_dialog.html.twig', ['id' => 'd', 'title' => 'Step 2', 'back_href' => '/step-1', 'status' => ['label' => 'Up']]);
        self::assertMatchesRegularExpression('#<a class="nowo-ui-dialog__back app-back" href="/step-1" aria-label="Back" title="Back"><span aria-hidden="true">&larr;</span></a><div class="nowo-ui-dialog__title-row"><h2 #', $html);
        $html = self::twig()->render(self::PARTIALS.'_dialog.html.twig', ['id' => 'd', 'title' => 'T', 'back_href' => '/x', 'back_label' => 'Back to scope']);
        self::assertStringContainsString('href="/x" aria-label="Back to scope" title="Back to scope"><span aria-hidden="true">&larr;</span></a><h2 ', $html);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeBackHrefs(): iterable
    {
        yield ['javascript:alert(1)'];
        yield ['JaVaScRiPt:alert(1)'];
        yield ["java\tscript:alert(1)"];
        yield ['data:text/html,x'];
    }

    #[DataProvider('unsafeBackHrefs')]
    public function testUnsafeBackHrefPrintsNoArrow(string $href): void
    {
        $html = self::twig()->render(self::PARTIALS.'_dialog.html.twig', ['id' => 'd', 'title' => 'T', 'back_href' => $href]);
        self::assertStringNotContainsString('nowo-ui-dialog__back', $html);
        self::assertStringNotContainsString('script:', $html);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function badDialogAttributes(): iterable
    {
        yield 'footer handler' => ['footer_attr', ['onclick' => 'x()']];
        yield 'footer javascript' => ['footer_attr', ['data-url' => 'javascript:x()']];
        yield 'header handler' => ['header_attr', ['onclick' => 'x()']];
        yield 'header style' => ['header_attr', ['style' => 'x']];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('badDialogAttributes')]
    public function testDialogHooksAreChecked(string $option, array $attributes): void
    {
        $this->assertTwigRefuses(self::PARTIALS.'_dialog.html.twig', ['id' => 'd', 'title' => 'T', 'footer' => 'F', $option => $attributes]);
    }

    public function testRemoteFormDialogForwardsPlacement(): void
    {
        $html = self::twig()->render(self::PARTIALS.'_remote_form_dialog.html.twig', ['id' => 'r', 'placement' => 'end']);
        self::assertStringContainsString('class="nowo-ui-dialog nowo-ui-dialog--lg nowo-ui-dialog--end nowo-ui-dialog--remote-form"', $html);
        $html = self::twig()->render(self::PARTIALS.'_remote_form_dialog.html.twig', ['id' => 'r', 'placement' => 'start', 'stimulus' => true, 'size' => 'md']);
        self::assertStringContainsString('class="nowo-ui-dialog nowo-ui-dialog--start nowo-ui-dialog--remote-form"', $html);
        $html = self::twig()->render(self::PARTIALS.'_remote_form_dialog.html.twig', ['id' => 'r', 'placement' => 'nonsense']);
        self::assertStringContainsString('class="nowo-ui-dialog nowo-ui-dialog--lg nowo-ui-dialog--remote-form"', $html);
    }

    public function testRemoteSidePanelDropsThePhoneGutter(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        self::assertMatchesRegularExpression('/\.nowo-ui-dialog--remote-form:is\(\.nowo-ui-dialog--end, \.nowo-ui-dialog--start\) \{\s+max-width: 100vw;\s+margin: 0;/', $css);
    }

    // K6 — progress

    public function testAbsoluteAmount(): void
    {
        $html = self::twig()->render(self::PARTIALS.'_progress.html.twig', ['value' => 3, 'max' => 5, 'id' => 'p']);
        self::assertStringContainsString('<progress id="p" class="nowo-ui-progress-meter" max="5" value="3">60 %</progress>', $html);
        self::assertStringContainsString('<span class="nowo-ui-progress-field__value" aria-hidden="true">60 %</span>', $html);
        self::assertStringNotContainsString('--indeterminate', $html);
    }

    public function testAmountIsClampedAndPercentWins(): void
    {
        $html = self::twig()->render(self::PARTIALS.'_progress.html.twig', ['value' => 9, 'max' => 5, 'id' => 'p']);
        self::assertStringContainsString('max="5" value="5">100 %</progress>', $html);
        $html = self::twig()->render(self::PARTIALS.'_progress.html.twig', ['value' => -1, 'max' => 5, 'id' => 'p']);
        self::assertStringContainsString('max="5" value="0">0 %</progress>', $html);
        $html = self::twig()->render(self::PARTIALS.'_progress.html.twig', ['value' => 3, 'max' => 5, 'percent' => 10, 'id' => 'p']);
        self::assertStringContainsString('max="100" value="10">10 %</progress>', $html);
        $html = self::twig()->render(self::PARTIALS.'_progress.html.twig', ['value' => 3, 'max' => 5, 'indeterminate' => true, 'id' => 'p']);
        self::assertStringContainsString('nowo-ui-progress-field--indeterminate', $html);
        self::assertStringNotContainsString('value=', $html);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function noAmount(): iterable
    {
        yield 'value alone' => [['value' => 3]];
        yield 'max alone' => [['max' => 5]];
        yield 'nothing to count yet' => [['value' => 0, 'max' => 0]];
        yield 'negative max' => [['value' => 1, 'max' => -5]];
        yield 'text' => [['value' => 'x', 'max' => 5]];
        yield 'list value (a form block)' => [['value' => ['a'], 'max' => 5]];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('noAmount')]
    public function testNoAmountStaysIndeterminate(array $context): void
    {
        $html = self::twig()->render(self::PARTIALS.'_progress.html.twig', ['id' => 'p'] + $context);
        self::assertStringContainsString('<progress id="p" class="nowo-ui-progress-meter" max="100">', $html);
        self::assertNull(ThemeExtension::progressAmount($context['value'] ?? null, $context['max'] ?? null));
    }

    public function testProgressAmountHelper(): void
    {
        self::assertSame(['value' => 1.5, 'max' => 3, 'percent' => 50], ThemeExtension::progressAmount('1.5', '3'));
        self::assertNull(ThemeExtension::progressAmount(\INF, 3));
        self::assertNull(ThemeExtension::progressAmount(1, \INF));
    }

    public function testHiddenLabelKeepsTheAccessibleName(): void
    {
        $html = self::twig()->render(self::PARTIALS.'_progress.html.twig', ['percent' => 40, 'label' => 'Copying', 'label_visible' => false, 'id' => 'p']);
        self::assertStringContainsString('<label class="nowo-ui-progress-field__label visually-hidden" for="p">Copying</label>', $html);
    }

    public function testMeterAttr(): void
    {
        $html = self::twig([], self::THEME)->render(self::PARTIALS.'_progress.html.twig', ['value' => 1, 'max' => 2, 'id' => 'p', 'meter_attr' => [
            'class' => 'task-sync__bar', 'data-sync-target' => 'bar', 'data-host' => '2', 'id' => 'other', 'max' => '9', 'value' => '9',
        ]]);
        self::assertStringContainsString('<progress id="p" class="nowo-ui-progress-meter task-sync__bar" max="2" value="1" data-host="2" data-sync-target="bar">', $html);
        self::assertSame(0, preg_match('/\sstyle=/', $html));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function badMeterAttributes(): iterable
    {
        yield 'handler' => [['onclick' => 'x()']];
        yield 'javascript' => [['data-progress-url-value' => 'javascript:x()']];
        yield 'style' => [['style' => 'width:1%']];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('badMeterAttributes')]
    public function testMeterAttrIsChecked(array $attributes): void
    {
        $this->assertTwigRefuses(self::PARTIALS.'_progress.html.twig', ['percent' => 1, 'meter_attr' => $attributes]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function assertTwigRefuses(string $template, array $context): void
    {
        try {
            $html = self::twig()->render($template, $context);
        } catch (Throwable $e) {
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious() ?? $e);

            return;
        }
        self::fail('The attribute was printed: '.$html);
    }
}
