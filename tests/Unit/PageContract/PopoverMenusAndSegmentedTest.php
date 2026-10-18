<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * 2.4: popover menus (`popover` on the menu / kebab / row_actions partials, theme.popover_menus), the `segmented`
 * partial and the per-page choices of the pagination partial (Pagination::fromTotal()).
 */
final class PopoverMenusAndSegmentedTest extends TestCase
{
    public function testMenuPopoverModeUsesANativePopoverPanel(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('menu'), {id: 'm', label: 'Row', items: items, popover: true}, false) }}", ['items' => [['label' => 'Open', 'href' => '/o']]]);
        $c = new Crawler($html);

        $root = $c->filter('div[data-nowo-ui-popover-menu]');
        self::assertCount(1, $root);
        self::assertSame('m', $root->attr('id'));
        self::assertStringContainsString('nowo-ui-menu--popover', (string) $root->attr('class'));
        $toggle = $c->filter('button[data-nowo-ui-popover-trigger]');
        self::assertSame(['m-panel', 'menu', 'false', 'button'], [$toggle->attr('popovertarget'), $toggle->attr('aria-haspopup'), $toggle->attr('aria-expanded'), $toggle->attr('type')]);
        self::assertSame('Row', trim($toggle->text()));
        $panel = $c->filter('ul#m-panel[popover][data-nowo-ui-popover-panel]');
        self::assertCount(1, $panel);
        self::assertSame('/o', $panel->filter('a.nowo-ui-menu__item')->attr('href'));
        self::assertCount(0, $c->filter('details'));

        // Free content panel, random id without `id`, theme flag as the default, explicit false wins.
        $content = new Crawler($this->render("{{ include(nowo_ui_partial('menu'), {content: '<p>x</p>'}, false) }}", [], ['popover_menus' => true]));
        $panelId = (string) $content->filter('.nowo-ui-popover[popover]')->attr('id');
        self::assertMatchesRegularExpression('/^nowo-ui-menu-\d+-panel$/', $panelId);
        self::assertSame($panelId, $content->filter('[data-nowo-ui-popover-trigger]')->attr('popovertarget'));

        $details = $this->render("{{ include(nowo_ui_partial('menu'), {items: [], popover: false}, false) }}", [], ['popover_menus' => true]);
        self::assertStringContainsString('<details', $details);
        self::assertStringNotContainsString('popover', $details);

        self::assertStringContainsString('<details', $this->render("{{ include(nowo_ui_partial('menu'), {items: []}, false) }}"));
    }

    public function testKebabPopoverMode(): void
    {
        $tpl = "{% include '@NowoUiKitBundle/partials/_kebab.html.twig' with {items: [{label: 'Edit', href: '/e'}], id: id, popover: popover} %}";
        $c = new Crawler($this->render($tpl, ['id' => 'k1', 'popover' => true]));
        self::assertSame('k1', $c->filter('button.nowo-ui-kebab__summary[data-nowo-ui-popover-trigger]')->attr('popovertarget'));
        self::assertSame('/e', $c->filter('#k1[popover][data-nowo-ui-popover-panel] a.nowo-ui-kebab__item')->attr('href'));

        $random = new Crawler($this->render($tpl, ['id' => null, 'popover' => null], ['popover_menus' => true]));
        self::assertMatchesRegularExpression('/^nowo-ui-kebab-\d+$/', (string) $random->filter('.nowo-ui-kebab__menu[popover]')->attr('id'));

        $details = new Crawler($this->render($tpl, ['id' => null, 'popover' => null]));
        self::assertCount(1, $details->filter('details.nowo-ui-kebab__details .nowo-ui-kebab__menu a'));
        self::assertCount(0, $details->filter('[popover]'));
    }

    public function testRowActionsMenuMode(): void
    {
        $csrf = PageContractTwig::forms()->createNamedBuilder('del', \Symfony\Component\Form\Extension\Core\Type\FormType::class)->getForm()->createView();
        $actions = [
            Action::create('/items/1', 'Open'),
            ['view' => Action::delete('/items/1/delete', null, new Confirm('Delete?'), 'Delete', $csrf)],
            ['kind' => 'edit', 'href' => '/items/1/edit', 'attr' => ['data-x' => 'y']],
            ['kind' => 'delete', 'label' => 'Drop', 'href' => '/items/1/drop'],
            ['kind' => 'copy', 'href' => '/items/1/copy', 'method' => 'POST', 'csrf_token' => 't'],
            ['kind' => 'view', 'href' => '/hidden', 'visible' => false],
        ];
        $html = $this->render("{{ include(nowo_ui_partial('row_actions'), {actions: actions, menu: true, menu_label: 'Actions', popover: true}, false) }}", ['actions' => $actions]);
        $c = new Crawler($html);

        self::assertStringContainsString('nowo-ui-row-actions--menu', (string) $c->filter('.nowo-ui-row-actions')->attr('class'));
        self::assertSame('Actions', trim($c->filter('[data-nowo-ui-popover-trigger]')->text()));
        $panel = $c->filter('[data-nowo-ui-popover-panel]');
        self::assertSame(['/items/1', '/items/1/edit', '/items/1/drop'], $panel->filter('a.nowo-ui-menu__item')->each(static fn (Crawler $a): string => (string) $a->attr('href')));
        self::assertSame('y', $panel->filter('a[href="/items/1/edit"]')->attr('data-x'));
        self::assertStringContainsString('nowo-ui-menu__item--danger', (string) $panel->filter('a[href="/items/1/drop"]')->attr('class'));
        // The View\Action delete keeps its confirm dialog; the 1.x POST hash and the hidden one are not in the menu.
        self::assertCount(1, $panel->filter('.nowo-ui-menu__entry [data-nowo-confirm-open]'));
        self::assertCount(1, $panel->filter('dialog'));
        self::assertStringNotContainsString('/items/1/copy', $html);
        self::assertStringNotContainsString('/hidden', $html);

        $external = $this->render("{{ include(nowo_ui_partial('row_actions'), {actions: actions, menu: true, confirm_external: true}, false) }}", ['actions' => [$actions[1]]]);
        self::assertStringNotContainsString('<dialog', $external);
        self::assertStringContainsString('<details', $external);

        // Default: the button cluster, unchanged.
        self::assertStringNotContainsString('nowo-ui-row-actions--menu', $this->render("{{ include(nowo_ui_partial('row_actions'), {actions: actions}, false) }}", ['actions' => [$actions[0]]]));
    }

    public function testSegmentedWithAPlainCheckbox(): void
    {
        $tpl = "{{ include(nowo_ui_partial('segmented'), {name: 'involved', id: 'inv', checked: checked, label: 'Scope', options: [{label: 'All', checked: false, title: 'Every issue'}, {label: 'Involved', checked: true, attr: {'data-testid': 'inv'}}], input_attr: {'data-model': 'on(change)|involved'}}, false) }}";
        $c = new Crawler($this->render($tpl, ['checked' => true]));

        $group = $c->filter('div[role="radiogroup"][data-nowo-ui-segmented]');
        self::assertSame(['nowo-ui-segmented', 'Scope'], [$group->attr('class'), $group->attr('aria-label')]);
        $options = $c->filter('button[role="radio"][data-nowo-ui-segmented-option]');
        self::assertSame(['false', 'true'], $options->each(static fn (Crawler $o): string => (string) $o->attr('aria-checked')));
        self::assertSame(['-1', '0'], $options->each(static fn (Crawler $o): string => (string) $o->attr('tabindex')));
        self::assertSame(['false', 'true'], $options->each(static fn (Crawler $o): string => (string) $o->attr('data-nowo-ui-segmented-checked')));
        self::assertSame('nowo-ui-segmented__option is-active', $options->eq(1)->attr('class'));
        self::assertSame(['Every issue', 'inv'], [$options->eq(0)->attr('title'), $options->eq(1)->attr('data-testid')]);
        $input = $c->filter('.nowo-ui-segmented__field input[type="checkbox"][data-nowo-ui-segmented-input]');
        self::assertSame(['involved', '1', 'inv', 'checked', '-1', 'on(change)|involved'], [$input->attr('name'), $input->attr('value'), $input->attr('id'), null !== $input->attr('checked') ? 'checked' : null, $input->attr('tabindex'), $input->attr('data-model')]);

        $off = new Crawler($this->render($tpl, ['checked' => false]));
        self::assertSame(['true', 'false'], $off->filter('[data-nowo-ui-segmented-option]')->each(static fn (Crawler $o): string => (string) $o->attr('aria-checked')));
        self::assertNull($off->filter('input')->attr('checked'));
    }

    public function testSegmentedWithACheckboxFormField(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('prefs')->add('involved', CheckboxType::class, ['data' => true, 'attr' => ['data-keep' => 'k']])->getForm()->createView();
        $html = $this->render("<p id=\"lbl\">Scope</p>{{ include(nowo_ui_partial('segmented'), {field: form.involved, labelled_by: 'lbl', options: [{label: 'All', checked: false}, {label: 'Involved', checked: true}], bundle: 'NowoXBundle'}, false) }}", ['form' => $form], [
            'classes' => ['segmented' => 'app-seg', 'segmented_option' => 'app-seg-option'],
        ]);
        $c = new Crawler($html);

        self::assertSame('lbl', $c->filter('[data-nowo-ui-segmented]')->attr('aria-labelledby'));
        self::assertNull($c->filter('[data-nowo-ui-segmented]')->attr('aria-label'));
        self::assertSame('nowo-ui-segmented app-seg', $c->filter('[data-nowo-ui-segmented]')->attr('class'));
        self::assertSame('true', $c->filter('.app-seg-option.is-active')->attr('data-nowo-ui-segmented-checked'));
        $input = $c->filter('input#prefs_involved');
        self::assertSame(['prefs[involved]', 'checked', 'k'], [$input->attr('name'), $input->attr('checked'), $input->attr('data-keep')]);
        self::assertNotNull($input->attr('data-nowo-ui-segmented-input'));
        self::assertTrue($form['involved']->isRendered());
    }

    public function testPaginationPerPageChoices(): void
    {
        $tpl = "{{ include(nowo_ui_partial('pagination'), vars, false) }}";
        $pagination = Pagination::fromTotal(120, 2, 25, route: 'items', query: ['q' => 'x']);

        $c = new Crawler($this->render($tpl, ['vars' => ['pagination' => $pagination, 'per_page_selector' => true]]));
        $choices = $c->filter('.nowo-ui-pagination__per-page');
        self::assertSame('Per page', trim($choices->filter('.nowo-ui-pagination__per-page-label')->text()));
        self::assertSame(['/items?q=x&per_page=10&page=1', '/items?q=x&per_page=50&page=1', '/items?q=x&per_page=100&page=1'], $choices->filter('a')->each(static fn (Crawler $a): string => (string) $a->attr('href')));
        self::assertSame('25', trim($choices->filter('[aria-current="true"]')->text()));

        $explicit = new Crawler($this->render($tpl, ['vars' => ['pagination' => ['page' => 1, 'per_page' => 20, 'total' => 50, 'total_pages' => 3], 'item_count' => 20, 'route' => 'r', 'per_page_choices' => [20, 40]]]));
        self::assertSame(['/r?per_page=40&page=1'], $explicit->filter('.nowo-ui-pagination__per-page a')->each(static fn (Crawler $a): string => (string) $a->attr('href')));

        // Default: no choices, also for a fromTotal() pagination.
        self::assertStringNotContainsString('nowo-ui-pagination__per-page', $this->render($tpl, ['vars' => ['pagination' => $pagination]]));
    }

    public function testThemeFlag(): void
    {
        self::assertFalse(Theme::fromConfig('bootstrap5', [])->popoverMenus());
        self::assertTrue(Theme::fromConfig('bootstrap5', ['popover_menus' => true])->popoverMenus());
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
