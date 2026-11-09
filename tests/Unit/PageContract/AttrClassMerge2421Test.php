<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingRegistry;
use Nowo\UiKitBundle\Tests\Unit\Listing\InvoiceListing;
use Nowo\UiKitBundle\Tests\Unit\Test\ScaffoldFixtures;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\Stat;
use Nowo\UiKitBundle\View\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

/**
 * 2.42.1: an attribute map option that carries `class` (`attr`, `toggle_attr`, `dialog_attr`, `title_attr`,
 * `body_attr`, item / option / panel `attr`, the `attributes` of View\Action, Stat, DetailItem, PageStatus, Table and
 * the scaffold pages) appends it to the element's own class list instead of printing a second `class` attribute,
 * which browsers drop (TaskBoard `manage/gantt.html.twig` `_tabs` `attr: {class: 'nowo-task-gantt__scales'}`).
 *
 * Every scenario renders the element(s) that take the map, with and without the ThemeExtension where the partial
 * renders without it. Without `class` the output is byte-identical to UiKit 2.42.0: the fixtures under
 * tests/Fixtures/bc-2.42 were rendered by the 2.42.0 templates (commit 4331a5b) from exactly these scenarios
 * (`data-probe="p"` stands for the other attributes), no whitespace normalisation.
 */
final class AttrClassMerge2421Test extends TestCase
{
    private const PROBE = ['data-probe' => 'p'];

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
        $html = self::renders()[$name][0](self::PROBE);
        $file = self::fixtureDir().'/'.$name.'.html';
        if ('1' === getenv('UIKIT_BC_2_42_WRITE')) {
            if (!is_dir(self::fixtureDir())) {
                mkdir(self::fixtureDir(), 0o777, true);
            }
            file_put_contents($file, $html);
        }

        self::assertSame((string) file_get_contents($file), $html);
    }

    #[DataProvider('scenarios')]
    public function testClassJoinsTheElementClass(string $name): void
    {
        [$render, $elements] = self::renders()[$name];
        $tags = $this->probedTags($render(self::PROBE + ['class' => 'nowo-probe-x']));

        self::assertCount($elements, $tags, $name.': elements that take the map');
        foreach ($tags as $tag) {
            self::assertSame(1, preg_match_all('/\sclass="/', $tag), $name.': one class attribute in '.$tag);
            self::assertMatchesRegularExpression('/\sclass="[^"]*\S [^"]*\bnowo-probe-x\b[^"]*"/', $tag, $name.': own classes + x in '.$tag);
        }
    }

    #[DataProvider('scenarios')]
    public function testHostileClassIsEscaped(string $name): void
    {
        [$render, $elements] = self::renders()[$name];
        $html = $render(self::PROBE + ['class' => 'x" onmouseover="alert(1)" y=\'<b>']);

        self::assertCount($elements, $this->probedTags($html), $name);
        self::assertStringNotContainsString('onmouseover="', $html, $name);
        self::assertStringNotContainsString("y='<b>", $html, $name);
        self::assertStringContainsString('x&quot; onmouseover=&quot;alert(1)&quot; y=&#039;&lt;b&gt;', $html, $name);
    }

    public static function fixtureDir(): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/bc-2.42';
    }

    /**
     * @return list<string> opening tags that carry the probe attribute
     */
    private function probedTags(string $html): array
    {
        preg_match_all('/<[a-z][a-z0-9]*(?:\s+[^\s"\'=>\/]+(?:="[^"]*")?)*\s*\/?>/i', $html, $m);

        return array_values(array_filter($m[0], static fn (string $tag): bool => str_contains($tag, ' data-probe="p"')));
    }

    /**
     * @return array<string, array{0: Closure(array<string, string>): string, 1: int}> render with the map => elements
     */
    private static function renders(): array
    {
        $p = '@NowoUiKitBundle/partials/';
        $inc = static fn (string $template, array $context, array $templates = []): string => PageContractTwig::create($templates)->render($p.$template, $context);
        $tpl = static fn (string $source, array $context): string => PageContractTwig::create(['t' => $source])->render('t', $context);
        $bare = static function (string $template, array $context) use ($p): string {
            $fs = new FilesystemLoader();
            $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
            $twig = new Environment($fs, ['strict_variables' => false]);
            $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));

            return $twig->render($p.$template, $context);
        };
        $items = static fn (array $a): array => [['label' => 'A', 'href' => '/a', 'current' => true, 'attr' => $a], ['label' => 'B', 'href' => '/b']];
        $plain = [['label' => 'A', 'href' => '/a', 'current' => true], ['label' => 'B', 'href' => '/b']];
        $edge = "{{ include('@NowoUiKitBundle/partials/_edge_tab.html.twig', opts) }}";
        $renders = [
            'tabs' => [static fn (array $a): string => $inc('_tabs.html.twig', ['items' => $plain, 'aria_label' => 'S', 'attr' => $a]), 1],
            'tabs_secondary' => [static fn (array $a): string => $inc('_tabs.html.twig', ['items' => $plain, 'level' => 'secondary', 'attr' => $a]), 1],
            'tabs_item' => [static fn (array $a): string => $inc('_tabs.html.twig', ['items' => $items($a)]), 1],
            'tabs_bare' => [static fn (array $a): string => $bare('_tabs.html.twig', ['items' => $plain, 'aria_label' => 'S', 'attr' => $a]), 1],
            'tabs_secondary_bare' => [static fn (array $a): string => $bare('_tabs.html.twig', ['items' => $plain, 'level' => 'secondary', 'attr' => $a]), 1],
            'tabs_item_bare' => [static fn (array $a): string => $bare('_tabs.html.twig', ['items' => $items($a)]), 1],
            'card' => [static fn (array $a): string => $inc('_card.html.twig', ['title' => 'T', 'body' => 'b', 'attr' => $a]), 1],
            'card_bare' => [static fn (array $a): string => $bare('_card.html.twig', ['title' => 'T', 'body' => 'b', 'attr' => $a]), 1],
            'burger' => [static fn (array $a): string => $inc('_burger.html.twig', ['attr' => $a]), 1],
            'burger_bare' => [static fn (array $a): string => $bare('_burger.html.twig', ['attr' => $a]), 1],
            'shell' => [static fn (array $a): string => $inc('_shell.html.twig', ['attr' => $a]), 1],
            'shell_bare' => [static fn (array $a): string => $bare('_shell.html.twig', ['attr' => $a]), 1],
            'kebab' => [static fn (array $a): string => $inc('_kebab.html.twig', ['items' => $items($a)]), 1],
            'kebab_bare' => [static fn (array $a): string => $bare('_kebab.html.twig', ['items' => $items($a)]), 1],
            'aside_nav_items' => [static fn (array $a): string => $inc('_aside_nav_items.html.twig', ['items' => $items($a)]), 1],
            'row_actions' => [static fn (array $a): string => $inc('_row_actions.html.twig', ['actions' => [['kind' => 'edit', 'href' => '/e', 'attr' => $a], ['kind' => 'view', 'tag' => 'button', 'attr' => $a]]]), 2],
            'row_actions_bare' => [static fn (array $a): string => $bare('_row_actions.html.twig', ['actions' => [['kind' => 'edit', 'href' => '/e', 'attr' => $a], ['kind' => 'view', 'tag' => 'button', 'attr' => $a]]]), 2],
            'shell_burger' => [static fn (array $a): string => PageContractTwig::create()->render('@NowoUiKitBundle/shell/_burger.html.twig', ['controls' => 'aside', 'attr' => $a]), 1],
            'app_shell_open' => [static fn (array $a): string => $tpl("{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main>BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {footer_html: false}) }}", ['opts' => ['attr' => $a]]), 1],
            'side_panel_toggle' => [static fn (array $a): string => $inc('_side_panel_toggle.html.twig', ['target' => 'c', 'label' => 'Comments', 'attr' => $a]), 1],
            'header_context' => [static fn (array $a): string => $inc('_header_context.html.twig', ['home_href' => '/', 'attr' => $a]), 1],
            'header_actions' => [static fn (array $a): string => $inc('_header_actions.html.twig', ['actions' => [Action::create('/n')], 'attr' => $a]), 1],
            'header_crumbs' => [static fn (array $a): string => $inc('_header_crumbs.html.twig', ['crumbs' => [new Crumb('Home', '/'), new Crumb('Items', '/items'), new Crumb('Edit')], 'attr' => $a]), 1],
            'side_panel_container' => [static fn (array $a): string => $inc('_side_panel_container.html.twig', ['attr' => $a]), 1],
            'side_panel' => [static fn (array $a): string => $inc('_side_panel.html.twig', ['id' => 'sp', 'title' => 'T', 'attr' => $a]), 1],
            'stat_row' => [static fn (array $a): string => $inc('_stat_row.html.twig', ['items' => [new Stat('Items', '3', attributes: $a), new Stat('Errors', '1')], 'attr' => $a]), 2],
            'segmented' => [static fn (array $a): string => $inc('_segmented.html.twig', ['name' => 'only', 'checked' => true, 'options' => [['label' => 'A', 'checked' => false, 'attr' => $a], ['label' => 'B', 'checked' => true]], 'attr' => $a]), 2],
            'sort_header_link' => [static fn (array $a): string => $inc('_sort_header.html.twig', ['label' => 'Name', 'href' => '/s', 'attr' => $a]), 1],
            'sort_header_button' => [static fn (array $a): string => $inc('_sort_header.html.twig', ['label' => 'Name', 'attr' => $a]), 1],
            'locale_tabs' => [static fn (array $a): string => $inc('_locale_tabs.html.twig', ['id' => 'lt', 'panels' => [['locale' => 'es', 'content' => 'x', 'attr' => $a], ['locale' => 'en', 'content' => 'y']], 'attr' => $a]), 2],
            'menu_details' => [static fn (array $a): string => $inc('_menu.html.twig', ['items' => [new Action('custom', 'Go', '/go', attributes: $a), ['label' => 'Hash', 'href' => '/h', 'attr' => $a]], 'attr' => $a, 'toggle_attr' => $a]), 4],
            'menu_popover' => [static fn (array $a): string => $inc('_menu.html.twig', ['items' => [['label' => 'Hash', 'href' => '/h', 'attr' => $a]], 'popover' => true, 'id' => 'm', 'attr' => $a, 'toggle_attr' => $a]), 3],
            'tree' => [static fn (array $a): string => $inc('_tree.html.twig', ['nodes' => [['id' => 'a', 'label' => 'A', 'href' => '/a']], 'label' => 'Pages', 'attr' => ['id' => 'pages'] + $a]), 1],
            'progress' => [static fn (array $a): string => $inc('_progress.html.twig', ['value' => 30, 'attr' => $a]), 1],
            'confirm_dialog' => [static fn (array $a): string => $tpl("{% embed nowo_ui_partial('confirm_dialog') with opts %}{% block form %}<form method=\"post\" action=\"/r\"><button type=\"submit\">Go</button></form>{% endblock %}{% endembed %}", ['opts' => ['dialog_id' => 'cd', 'trigger_label' => 'Open', 'attr' => $a, 'dialog_attr' => $a]]), 2],
            'listing_customizer' => [static fn (array $a): string => $inc('_listing_customizer.html.twig', ['listing' => self::listing(), 'attr' => $a]), 1],
            'dialog' => [static fn (array $a): string => $inc('_dialog.html.twig', ['id' => 'd', 'title' => 'T', 'body' => '<p>b</p>', 'attr' => $a, 'title_attr' => $a, 'body_attr' => $a]), 3],
            'table' => [static fn (array $a): string => $inc('_table.html.twig', ['table' => new Table([new Column('name', 'Name')], [['name' => 'Alpha']], attributes: $a)]), 1],
            'detail_list' => [static fn (array $a): string => PageContractTwig::create()->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => [new DetailItem('Name', 'Alpha', attributes: $a, valueAttributes: $a), new DetailItem('Qty', 3)]]), 2],
            'page_status' => [static fn (array $a): string => $inc('_page_status.html.twig', ['status' => new PageStatus('Up', 'success', attributes: $a)]), 1],
            'action_link' => [static fn (array $a): string => $inc('_action.html.twig', ['action' => new Action('custom', 'Go', '/go', attributes: $a)]), 1],
            'action_post' => [static fn (array $a): string => $inc('_action.html.twig', ['action' => new Action('restore', 'Restore', '/r', method: 'POST', attributes: $a, form: ScaffoldFixtures::csrf('restore'))]), 1],
            'action_modal' => [static fn (array $a): string => $inc('_action.html.twig', ['action' => new Action('import', 'Import', attributes: $a, modalId: 'm')]), 1],
            'responsive_table' => [static fn (array $a): string => $tpl("{% embed '@NowoUiKitBundle/partials/_responsive_table.html.twig' with opts only %}{% block table %}<table></table>{% endblock %}{% endembed %}", ['opts' => ['dialog_id' => 'rt', 'attr' => $a]]), 1],
            'tour' => [static fn (array $a): string => $inc('_tour.html.twig', ['tour' => ['key' => 'k', 'steps' => [['title' => 'T', 'body' => 'B']]], 'attr' => $a]), 1],
            'corner_tab' => [static fn (array $a): string => $inc('_corner_tab.html.twig', ['label' => 'Help', 'attr' => $a]), 1],
            'edge_tab_link' => [static fn (array $a): string => $tpl($edge, ['opts' => ['href' => '/h', 'attr' => $a]]), 1],
            'edge_tab_button' => [static fn (array $a): string => $tpl($edge, ['opts' => ['button' => true, 'attr' => $a]]), 1],
            'edge_link_button' => [static fn (array $a): string => $tpl($edge, ['opts' => ['inline' => true, 'button' => true, 'attr' => $a]]), 1],
            'edge_tabs_launcher' => [static fn (array $a): string => $inc('_edge_tabs_launcher.html.twig', ['attr' => $a]), 1],
            'list_filters_submit' => [static fn (array $a): string => PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => self::listPage(new Action('filter', 'Apply', attributes: $a))]), 1],
        ];
        $templates = [];
        foreach (['list', 'show', 'form', 'settings', 'dashboard', 'confirm', 'wizard', 'auth'] as $scaffold) {
            $templates[$scaffold] = '@NowoUiKit/page/'.$scaffold.'.html.twig';
        }
        foreach (ScaffoldFixtures::all($templates) as $name => $fixture) {
            if ('list_empty' === $name) {
                continue;
            }
            $renders['page_'.$name] = [static fn (array $a): string => PageContractTwig::create()->render($fixture->template, ['page' => self::withAttributes($fixture->context['page'], $a)]), 1];
        }

        return $renders;
    }

    private static function listing(): object
    {
        $builder = new ListingCustomizerViewBuilder(new ListingRegistry([new InvoiceListing()]), new CookieListingPreferenceStore());

        return $builder->forRequest(InvoiceListing::KEY, new Request())['view'];
    }

    private static function listPage(Action $submit): ListPage
    {
        return new ListPage(
            new PageHeader('Items'),
            [new Column('name', 'Name')],
            [['name' => 'Alpha']],
            ScaffoldFixtures::form('filters', ['q' => SearchType::class], 'GET'),
            filtersSubmit: $submit,
        );
    }

    /**
     * A copy of a readonly scaffold page model with other root `attributes`.
     *
     * @param array<string, string> $attributes
     */
    private static function withAttributes(object $page, array $attributes): object
    {
        $class = new ReflectionClass($page);
        $copy = $class->newInstanceWithoutConstructor();
        $values = [];
        foreach ($class->getProperties() as $property) {
            if ($property->isInitialized($page)) {
                $values[$property->getName()] = $property->getValue($page);
            }
        }
        $values['attributes'] = $attributes;
        Closure::bind(static function (object $copy, array $values): void {
            foreach ($values as $name => $value) {
                $copy->{$name} = $value;
            }
        }, null, $page::class)($copy, $values);

        return $copy;
    }
}
