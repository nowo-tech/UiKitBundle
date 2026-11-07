<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Twig\ActionIconsExtension;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

/**
 * `nowo_ui_kit.action_icons` / `action_variants` and the kinds of the "one icon per action" table.
 */
final class ActionIconsTest extends TestCase
{
    private const ICON = '@NowoUiKitBundle/components/_icon.html.twig';
    private const ROW = '@NowoUiKitBundle/partials/_row_actions.html.twig';
    private const ACTION = '@NowoUiKitBundle/partials/_action.html.twig';
    private const MENU = '@NowoUiKitBundle/partials/_menu.html.twig';

    /** Kind => default in ux_icon / bootstrap-icons (without prefix), tabler-icons (without prefix). */
    private const NEW_KINDS = [
        'extend' => ['calendar-plus', 'clock-plus'],
        'revoke' => ['person-x', 'user-off'],
        'refresh' => ['arrow-clockwise', 'refresh'],
        'archive' => ['archive', 'archive'],
        'pause' => ['pause', 'player-pause'],
        'resume' => ['play', 'player-play'],
        'download' => ['download', 'download'],
        'import' => ['upload', 'upload'],
        'send' => ['send', 'send'],
        'sign_in' => ['box-arrow-in-right', 'login'],
        'sign_out' => ['box-arrow-right', 'logout'],
        'reset' => ['arrow-counterclockwise', 'arrow-back-up'],
        'mark_done' => ['check-lg', 'check'],
        'mark_all_done' => ['check-all', 'checks'],
        'move' => ['arrow-left-right', 'arrows-exchange'],
        'enable' => ['power', 'power'],
        'disable' => ['power', 'power'],
        'hide' => ['eye-slash', 'eye-off'],
        'run' => ['play', 'player-play'],
        'retry' => ['arrow-clockwise', 'refresh'],
        'stop' => ['stop', 'player-stop'],
    ];

    /**
     * @param array<string, string> $icons
     * @param array<string, string> $variants
     */
    private function twig(string $set = 'bootstrap-icons', array $icons = [], array $variants = [], string $framework = 'bootstrap5'): Environment
    {
        return ActionIconsBcTest::twig($set, $framework, ['nowo_ui_kit_action_icons' => $icons, 'nowo_ui_kit_action_variants' => $variants]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function newKinds(): iterable
    {
        foreach (self::NEW_KINDS as $kind => [$bi, $tabler]) {
            yield $kind => [$kind, $bi, $tabler];
        }
    }

    #[DataProvider('newKinds')]
    public function testNewKindHasADefaultInEveryIconSet(string $kind, string $bi, string $tabler): void
    {
        $vars = ['name' => $kind, 'label' => 'The label'];

        self::assertStringContainsString('name="bi:'.$bi.'"', ActionIconsBcTest::twig('ux_icon')->render(self::ICON, $vars));
        self::assertStringContainsString('class="ti ti-'.$tabler.'"', ActionIconsBcTest::twig('tabler-icons')->render(self::ICON, $vars));
        self::assertStringContainsString('class="bi bi-'.$bi.'"', ActionIconsBcTest::twig('bootstrap-icons')->render(self::ICON, $vars));
        self::assertStringContainsString('The label', ActionIconsBcTest::twig('none')->render(self::ICON, $vars));
        // An unknown name draws the `view` eye under svg_inline: a kind must have its own path.
        $svg = ActionIconsBcTest::twig('svg_inline');
        self::assertNotSame($svg->render(self::ICON, ['name' => 'view']), $svg->render(self::ICON, $vars));
    }

    #[DataProvider('newKinds')]
    public function testNewKindHasALabelInEveryLocale(string $kind): void
    {
        foreach (['en', 'es', 'fr', 'de', 'it', 'pt', 'nl'] as $locale) {
            $catalogue = Yaml::parseFile(PageContractTwig::root().'/src/Resources/translations/NowoUiKitBundle.'.$locale.'.yaml');
            self::assertIsArray($catalogue);
            self::assertNotEmpty($catalogue['action.'.$kind] ?? null, $kind.' in '.$locale);
        }
    }

    public function testNewKindsAreActionKinds(): void
    {
        foreach (array_keys(self::NEW_KINDS) as $kind) {
            self::assertSame($kind, (new Action($kind, 'L', '/x'))->kind);
        }
    }

    public function testKindListMatchesEveryMapOfTheTemplate(): void
    {
        $source = (string) file_get_contents(PageContractTwig::root().'/src/Resources/views/components/_icon.html.twig');
        self::assertSame(4, preg_match_all('/\{% set (?:ux_map|map|paths) = \{(.*?)\} %\}/s', $source, $blocks));
        foreach ($blocks[1] as $block) {
            preg_match_all('/^\s+(\w+): /m', $block, $keys);
            self::assertSame(ActionIconsExtension::ICON_KINDS, $keys[1]);
        }
        self::assertContains('custom', ActionIconsExtension::kinds());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function overrides(): iterable
    {
        yield 'ux_icon, prefix of the kit' => ['ux_icon', 'tabler:trash', 'name="tabler:trash"'];
        yield 'ux_icon, any other prefix' => ['ux_icon', 'lucide:trash-2', 'name="lucide:trash-2"'];
        yield 'ux_icon, local icon' => ['ux_icon', 'my-trash', 'name="my-trash"'];
        yield 'tabler-icons, own prefix' => ['tabler-icons', 'tabler:trash-x', 'class="ti ti-trash-x"'];
        yield 'tabler-icons, bare' => ['tabler-icons', 'trash-x', 'class="ti ti-trash-x"'];
        yield 'tabler-icons, other prefix: kit default' => ['tabler-icons', 'bi:trash3', 'class="ti ti-trash"'];
        yield 'bootstrap-icons, own prefix' => ['bootstrap-icons', 'bi:trash3', 'class="bi bi-trash3"'];
        yield 'bootstrap-icons, bare' => ['bootstrap-icons', 'trash3', 'class="bi bi-trash3"'];
        yield 'bootstrap-icons, other prefix: kit default' => ['bootstrap-icons', 'tabler:trash', 'class="bi bi-trash"'];
        yield 'none: label' => ['none', 'tabler:trash', 'nowo-ui-icon--text'];
    }

    #[DataProvider('overrides')]
    public function testOverrideUnderEachIconSet(string $set, string $icon, string $expected): void
    {
        $html = $this->twig($set, ['delete' => $icon])->render(self::ICON, ['name' => 'delete', 'label' => 'L']);

        self::assertStringContainsString($expected, $html);
    }

    public function testSvgInlineKeepsItsPathWhateverTheName(): void
    {
        self::assertSame(
            ActionIconsBcTest::twig('svg_inline')->render(self::ICON, ['name' => 'delete']),
            $this->twig('svg_inline', ['delete' => 'tabler:trash'])->render(self::ICON, ['name' => 'delete']),
        );
    }

    public function testOverrideReachesRowActionsAndChrome(): void
    {
        $twig = $this->twig('ux_icon', ['edit' => 'tabler:pencil', 'prev' => 'tabler:arrow-left']);

        $row = $twig->render(self::ROW, ['actions' => [['kind' => 'edit', 'href' => '/e']]]);
        self::assertStringContainsString('name="tabler:pencil"', $row);
        $chrome = $twig->render('@NowoUiKitBundle/partials/_back_link.html.twig', ['href' => '/b', 'label' => 'Back']);
        self::assertStringContainsString('name="tabler:arrow-left"', $chrome);
    }

    public function testExplicitIconOnAnActionWins(): void
    {
        $twig = $this->twig('ux_icon', ['edit' => 'tabler:pencil']);

        self::assertStringContainsString('name="tabler:eye"', $twig->render(self::ROW, ['actions' => [['kind' => 'edit', 'href' => '/e', 'icon' => 'tabler:eye']]]));
        $object = $twig->render(self::ACTION, ['action' => new Action('edit', 'L', '/e', icon: 'tabler:eye')]);
        self::assertStringContainsString('name="tabler:eye"', $object);
        self::assertStringNotContainsString('tabler:pencil', $object);
    }

    public function testToolbarActionDrawsItsKindIconOnlyWhenOptedIn(): void
    {
        $action = new Action('edit', 'Edit it', '/e');

        $off = $this->twig('ux_icon')->render(self::ACTION, ['action' => $action]);
        self::assertStringNotContainsString('<ux-icon', $off);

        $otherKind = $this->twig('ux_icon', ['view' => 'tabler:eye'])->render(self::ACTION, ['action' => $action]);
        self::assertSame($off, $otherKind);

        $on = $this->twig('ux_icon', ['edit' => 'tabler:pencil'])->render(self::ACTION, ['action' => $action]);
        self::assertStringContainsString('name="tabler:pencil"', $on);
        self::assertStringContainsString('Edit it', $on);

        $none = $this->twig('none', ['edit' => 'tabler:pencil'])->render(self::ACTION, ['action' => $action]);
        self::assertStringNotContainsString('nowo-ui-icon--text', $none);
    }

    public function testExplicitToolbarDisplayStillWins(): void
    {
        $twig = $this->twig('ux_icon', ['edit' => 'tabler:pencil']);

        self::assertStringNotContainsString('<ux-icon', $twig->render(self::ACTION, ['action' => new Action('edit', 'L', '/e'), 'display' => 'text']));
        self::assertStringNotContainsString('<ux-icon', $twig->render(self::ACTION, ['action' => new Action('edit', 'L', '/e', display: 'text')]));
    }

    public function testMenuItemDrawsItsKindIconOnlyWhenOptedIn(): void
    {
        $vars = ['items' => [Action::delete('/d', 'tok', label: 'Delete'), new Action('view', 'See', '/v', icon: 'tabler:eye')], 'id' => 'm', 'label' => 'More', 'menu_items' => true, 'confirm_external' => true];

        $off = $this->twig('ux_icon')->render(self::MENU, $vars);
        self::assertStringNotContainsString('tabler:trash', $off);
        self::assertStringNotContainsString('tabler:eye', $off, 'without the option a menu item stays text, even with its own icon');

        $on = $this->twig('ux_icon', ['delete' => 'tabler:trash'])->render(self::MENU, $vars);
        self::assertStringContainsString('name="tabler:trash"', $on);
        self::assertStringContainsString('name="tabler:eye"', $on);
        self::assertStringContainsString('nowo-ui-menu__item--danger', $on);
        // A plain GET action has its own link branch in the menu.
        $link = $this->twig('ux_icon', ['edit' => 'tabler:pencil'])->render(self::MENU, ['items' => [new Action('edit', 'Edit', '/e')], 'id' => 'm', 'label' => 'More', 'menu_items' => true]);
        self::assertStringContainsString('name="tabler:pencil"', $link);
        self::assertStringNotContainsString('tabler:pencil', $this->twig('ux_icon')->render(self::MENU, ['items' => [new Action('edit', 'Edit', '/e')], 'id' => 'm', 'label' => 'More', 'menu_items' => true]));

        $none = $this->twig('none', ['delete' => 'tabler:trash'])->render(self::MENU, $vars);
        self::assertStringNotContainsString('tabler:trash', $none);
        self::assertStringNotContainsString('nowo-ui-icon--text', $none, 'icon_set none keeps the text-only item');
    }

    public function testRowHashMenuDrawsItsKindIconOnlyWhenOptedIn(): void
    {
        $vars = ['actions' => [['kind' => 'delete', 'href' => '/d']], 'menu' => true, 'popover' => false];

        self::assertStringNotContainsString('tabler:trash', $this->twig('ux_icon')->render(self::ROW, $vars));
        self::assertStringContainsString('name="tabler:trash"', $this->twig('ux_icon', ['delete' => 'tabler:trash'])->render(self::ROW, $vars));
    }

    public function testMenuHashKeepsFreeRefreshNameUntilOptedIn(): void
    {
        $vars = ['items' => [['label' => 'Reload', 'icon' => 'refresh', 'action' => 'list#refresh']], 'id' => 'm', 'label' => 'More'];

        self::assertStringContainsString('bi bi-refresh', $this->twig()->render(self::MENU, $vars));
        self::assertStringContainsString('bi bi-arrow-clockwise', $this->twig()->render(self::ICON, ['name' => 'refresh']));
        self::assertStringContainsString('bi bi-arrow-clockwise', $this->twig(icons: ['refresh' => 'tabler:refresh'])->render(self::MENU, $vars));
        self::assertStringContainsString('bi bi-arrow-repeat', $this->twig(icons: ['refresh' => 'bi:arrow-repeat'])->render(self::MENU, $vars));
    }

    public function testVariantMapAppliesWhereNoVariantIsExplicit(): void
    {
        $twig = $this->twig(variants: ['create' => 'secondary', 'view' => 'danger']);

        $hash = $twig->render(self::ROW, ['actions' => [['kind' => 'create', 'href' => '/c']]]);
        self::assertStringContainsString('btn-outline-secondary', $hash);
        self::assertStringNotContainsString('btn-primary', $hash);

        self::assertStringContainsString('btn-primary', $twig->render(self::ROW, ['actions' => [['kind' => 'create', 'href' => '/c', 'variant' => 'primary']]]), 'explicit variant wins');
        self::assertStringContainsString('btn-outline-danger', $twig->render(self::ROW, ['actions' => [['kind' => 'view', 'href' => '/v']]]));

        self::assertStringContainsString('btn-outline-secondary', $twig->render(self::ACTION, ['action' => new Action('create', 'L', '/c')]));
        self::assertStringContainsString('btn-outline-secondary', $twig->render(self::ROW, ['actions' => [new Action('create', 'L', '/c')]]));
        // The page's main action: the factory sets primary explicitly.
        self::assertStringContainsString('btn-primary', $twig->render(self::ACTION, ['action' => Action::create('/c', 'L')]));
    }

    public function testRowSlotFollowsTheVariantMap(): void
    {
        $theme = ['classes' => ['action_secondary' => 'host-secondary', 'action_primary' => 'host-primary']];
        $twig = PageContractTwig::create([], $theme);
        $twig->addGlobal('nowo_ui_kit_action_variants', ['create' => 'secondary']);

        $html = $twig->render(self::ROW, ['actions' => [['kind' => 'create', 'href' => '/c']]]);
        self::assertStringContainsString('host-secondary', $html);
        self::assertStringNotContainsString('host-primary', $html);
    }

    public function testRowHashMenuFollowsTheVariantMap(): void
    {
        $vars = ['actions' => [['kind' => 'revoke', 'href' => '/r']], 'menu' => true, 'popover' => false];

        self::assertStringNotContainsString('nowo-ui-menu__item--danger', $this->twig()->render(self::ROW, $vars));
        self::assertStringContainsString('nowo-ui-menu__item--danger', $this->twig(variants: ['revoke' => 'danger'])->render(self::ROW, $vars));
        $explicit = ['actions' => [['kind' => 'revoke', 'href' => '/r', 'variant' => 'secondary']], 'menu' => true, 'popover' => false];
        self::assertStringNotContainsString('nowo-ui-menu__item--danger', $this->twig(variants: ['revoke' => 'danger'])->render(self::ROW, $explicit));
    }

    public function testMenuGetActionDangerClassFollowsTheVariantMap(): void
    {
        $vars = ['items' => [new Action('revoke', 'Revoke', '/r')], 'id' => 'm', 'label' => 'More', 'menu_items' => true];

        self::assertStringNotContainsString('nowo-ui-menu__item--danger', $this->twig()->render(self::MENU, $vars));
        self::assertStringContainsString('nowo-ui-menu__item--danger', $this->twig(variants: ['revoke' => 'danger'])->render(self::MENU, $vars));
        $explicit = ['items' => [new Action('revoke', 'Revoke', '/r', variant: 'secondary')], 'id' => 'm', 'label' => 'More', 'menu_items' => true];
        self::assertStringNotContainsString('nowo-ui-menu__item--danger', $this->twig(variants: ['revoke' => 'danger'])->render(self::MENU, $explicit));
    }

    public function testListFiltersSubmitFollowsTheVariantMap(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('f')->add('q', TextType::class)->getForm()->createView();
        $page = new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], $form);
        $partial = '@NowoUiKit/page/_list_filters.html.twig';
        $submit = static fn (string $html): string => (string) preg_replace('/.*(<button type="submit"[^>]*>).*/s', '$1', $html);

        self::assertStringContainsString('btn-primary', $submit($this->twig()->render($partial, ['page' => $page])));
        $mapped = $submit($this->twig(variants: ['filter' => 'secondary'])->render($partial, ['page' => $page]));
        self::assertStringContainsString('btn-outline-secondary', $mapped);
        self::assertStringNotContainsString('btn-primary', $mapped);

        $explicit = new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], $form, filtersSubmit: new Action('filter', 'Go', variant: 'primary'));
        self::assertStringContainsString('btn-primary', $submit($this->twig(variants: ['filter' => 'secondary'])->render($partial, ['page' => $explicit])));
    }

    public function testFilterSubmitFollowsTheVariantMap(): void
    {
        $partial = '@NowoUiKitBundle/partials/_filter_actions.html.twig';

        self::assertStringContainsString('btn-primary', $this->twig()->render($partial, ['submit_icon' => 'filter']));
        $html = $this->twig(variants: ['filter' => 'secondary'])->render($partial, ['submit_icon' => 'filter']);
        self::assertStringContainsString('btn-outline-secondary', $html);
        self::assertStringNotContainsString('btn-primary', $html);
        self::assertStringContainsString('host', $this->twig(variants: ['filter' => 'secondary'])->render($partial, ['submit_class' => 'host']));
    }
}
