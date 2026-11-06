<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Action;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * `action_icons` / `action_variants` are opt-in: without them every kind that existed before renders byte-identical.
 * The fixtures under tests/Fixtures/action-icons were rendered by UiKit 2.33.1 (origin/main 1ee4309) from exactly these
 * scenarios; no whitespace normalisation. No global is set here, as in a host that changed nothing.
 */
final class ActionIconsBcTest extends TestCase
{
    /** The kinds of components/_icon.html.twig in 2.33.1. */
    public const KINDS_2_33 = [
        'edit', 'delete', 'view', 'copy', 'create', 'save', 'cancel', 'restore', 'share', 'config', 'export', 'filter',
        'prev', 'next', 'close', 'filter_off', 'scroll_horizontal', 'rows', 'chevron_up', 'chevron_down', 'chevron_right',
        'book', 'sliders', 'grip', 'stack', 'tour', 'sort', 'sort_asc', 'sort_desc', 'more', 'home', 'grid', 'chevron_left',
    ];

    /** The row-action kinds of the 2.33.1 docs. */
    private const ROW_KINDS_2_33 = ['view', 'edit', 'delete', 'copy', 'create', 'save', 'cancel', 'restore', 'share', 'config', 'export', 'filter', 'prev', 'next', 'close'];

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
    public function testOutputWithoutTheOptionsIsAsIn233(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/action-icons/'.$name.'.html';
        self::assertFileExists($file);
        self::assertSame(file_get_contents($file), self::renders()[$name](), $name.' must render exactly as UiKit 2.33.1 printed it');
    }

    /**
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $renders = [];
        foreach (['bootstrap-icons', 'tabler-icons', 'ux_icon', 'svg_inline', 'none'] as $set) {
            $renders['icon_'.$set] = static function () use ($set): string {
                $twig = self::twig($set);
                $out = '';
                foreach ([...self::KINDS_2_33, 'pencil', 'tabler:x', 'bi:x'] as $name) {
                    $out .= $name.'='.$twig->render('@NowoUiKitBundle/components/_icon.html.twig', ['name' => $name, 'label' => 'L'])."\n";
                }

                return $out;
            };
        }
        $hashes = array_map(static fn (string $kind): array => ['kind' => $kind, 'href' => '/'.$kind], self::ROW_KINDS_2_33);
        foreach (['bootstrap5', 'tailwind', 'foundation'] as $fw) {
            foreach (['icon', 'text', 'icon_text'] as $display) {
                $renders['row_hashes_'.$fw.'_'.$display] = static fn (): string => self::twig('bootstrap-icons', $fw)->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => $hashes, 'display' => $display]);
            }
            $renders['row_objects_'.$fw] = static fn (): string => self::twig('bootstrap-icons', $fw)->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => self::objects(), 'display' => 'icon_text', 'confirm_external' => true]);
            $renders['toolbar_'.$fw] = static function () use ($fw): string {
                $twig = self::twig('bootstrap-icons', $fw);
                $out = '';
                foreach (self::objects() as $action) {
                    $out .= $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => $action, 'confirm_external' => true]);
                }

                return $out;
            };
            $renders['menu_items_'.$fw] = static fn (): string => self::twig('bootstrap-icons', $fw)->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => self::objects(), 'id' => 'm', 'label' => 'More', 'menu_items' => true, 'confirm_external' => true]);
            $renders['row_hash_menu_'.$fw] = static fn (): string => self::twig('bootstrap-icons', $fw)->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [['kind' => 'edit', 'href' => '/e'], ['kind' => 'delete', 'href' => '/d']], 'menu' => true, 'popover' => false]);
            $renders['filter_actions_'.$fw] = static fn (): string => self::twig('bootstrap-icons', $fw)->render('@NowoUiKitBundle/partials/_filter_actions.html.twig', ['submit_icon' => 'filter']);
        }

        return $renders;
    }

    /**
     * Writes the fixtures (run once against the reference revision, never in CI).
     */
    public static function dumpFixtures(string $dir): void
    {
        foreach (self::renders() as $name => $render) {
            file_put_contents($dir.'/'.$name.'.html', $render());
        }
    }

    /**
     * @return list<Action>
     */
    private static function objects(): array
    {
        return [
            new Action('edit', 'Edit', '/e'),
            new Action('view', 'View', '/v', icon: 'eye'),
            Action::create('/c', 'New'),
            new Action('create', 'New too', '/c2'),
            Action::delete('/d', 'tok', label: 'Delete'),
        ];
    }

    /**
     * @param array<string, mixed> $globals
     */
    public static function twig(string $iconSet, string $framework = 'bootstrap5', array $globals = []): Environment
    {
        $twig = PageContractTwig::create([], [], $framework);
        $twig->addGlobal('nowo_ui_kit_icon_set', $iconSet);
        foreach ($globals as $name => $value) {
            $twig->addGlobal($name, $value);
        }
        // symfony/ux-icons is optional and not a dev dependency: the name that reaches ux_icon() is what is asserted.
        $twig->addFunction(new TwigFunction('ux_icon', static fn (string $name): string => '<ux-icon name="'.$name.'"></ux-icon>', ['is_safe' => ['html']]));

        return $twig;
    }
}
