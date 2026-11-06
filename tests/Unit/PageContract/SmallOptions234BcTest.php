<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.34: pages that use none of the new options (table `rowAttributes` / `foot_html` / `foot_attr`, tree `link_attr` /
 * `item_attr` / `leading_template`, dialog `footer_attr` / `header_attr` / `back_href`, remote form dialog `placement`,
 * progress `value` + `max` / `label_visible` / `meter_attr`) render byte-identical to UiKit 2.33.1. The fixtures under
 * tests/Fixtures/bc-2.33 were rendered by the 2.33.1 templates (commit 283a46d) from exactly these scenarios; no
 * whitespace normalisation. The `inherited` scenarios include a partial with the parent context, the way a host page
 * does without `only`: a lone `value` in scope (a form block) must not turn an indeterminate meter determinate.
 */
final class SmallOptions234BcTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function scenarios(): iterable
    {
        foreach (array_keys(self::renders()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('scenarios')]
    public function testDefaultOutputIsByteIdentical(string $name): void
    {
        self::assertSame((string) file_get_contents(self::fixtureDir().'/'.$name.'.html'), self::renders()[$name]());
    }

    public static function fixtureDir(): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/bc-2.33';
    }

    /**
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $partials = '@NowoUiKitBundle/partials/';
        $render = static fn (string $template, array $context): string => PageContractTwig::create()->render($partials.$template, $context);
        $nodes = [
            ['id' => 'a', 'label' => 'Alpha', 'href' => '/a', 'expanded' => true, 'icon' => 'A', 'badge' => '2', 'children' => [
                ['id' => 'a1', 'label' => 'Apple', 'href' => '/a1', 'current' => true],
                ['id' => 'a2', 'label' => 'Avocado', 'href' => '/a2', 'has_children' => true, 'children_url' => '/children?parent=a2'],
            ]],
            ['id' => 'b', 'label' => 'Beta', 'href' => '/b'],
        ];

        return [
            'dialog_body_footer' => static fn (): string => $render('_dialog.html.twig', ['id' => 'd1', 'title' => 'Title', 'body' => '<p>Body</p>', 'footer' => '<button type="button">OK</button>', 'attr' => ['data-testid' => 'd1']]),
            'dialog_status_no_footer' => static fn (): string => $render('_dialog.html.twig', ['id' => 'd2', 'title' => 'Title', 'status' => ['label' => 'Up', 'tone' => 'success'], 'placement' => 'end', 'size' => 'wide']),
            'remote_form_dialog' => static fn (): string => $render('_remote_form_dialog.html.twig', ['id' => 'r1', 'title' => 'Edit']),
            'remote_form_dialog_stimulus' => static fn (): string => $render('_remote_form_dialog.html.twig', ['id' => 'r2', 'stimulus' => true, 'size' => 'md']),
            'tree' => static fn (): string => $render('_tree.html.twig', ['nodes' => $nodes, 'label' => 'Pages', 'movable' => true, 'attr' => ['id' => 'pages']]),
            'tree_actions' => static fn (): string => PageContractTwig::create(['actions' => '<button type="button" tabindex="-1">{{ node.id }}</button>'])->render($partials.'_tree.html.twig', ['nodes' => $nodes, 'label' => 'Pages', 'actions_template' => 'actions', 'attr' => ['id' => 'pages']]),
            'table' => static fn (): string => $render('_table.html.twig', ['table' => new Table([new Column('name', 'Name')], [['name' => 'One'], ['name' => '']], label: 'Rows')]),
            'progress_percent' => static fn (): string => $render('_progress.html.twig', ['percent' => 140, 'label' => 'Copying', 'id' => 'p1', 'attr' => ['data-testid' => 'p1']]),
            'progress_no_value_text' => static fn (): string => $render('_progress.html.twig', ['percent' => 0, 'show_value' => false, 'id' => 'p2']),
            'progress_indeterminate' => static fn (): string => $render('_progress.html.twig', ['id' => 'p3']),
            'progress_inherited' => static fn (): string => PageContractTwig::create(['page' => "{{ include(nowo_ui_partial('progress'), {id: 'p4'}) }}|{{ include(nowo_ui_partial('progress'), {id: 'p5', percent: 40}) }}"])->render('page', ['value' => 7]),
            'progress_percent_beats_inherited' => static fn (): string => PageContractTwig::create(['page' => "{{ include(nowo_ui_partial('progress'), {id: 'p6', percent: 40}) }}"])->render('page', ['value' => 7, 'max' => 9]),
        ];
    }
}
