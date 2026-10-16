<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * BC: the 2.0 partials render 1.x inputs exactly as UiKit 1.17.4 did (tests/Fixtures/legacy-1.17 are verbatim
 * copies), both in a bare Twig environment (bundle tests that render UiKit partials without the ThemeExtension)
 * and with the ThemeExtension and an empty theme. Whitespace is normalised.
 */
final class LegacyPartialsBcTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function legacyInputs(): iterable
    {
        foreach (['bootstrap5', 'tailwind', 'custom'] as $fw) {
            yield 'pagination '.$fw => ['_pagination', ['pagination' => ['page' => 4, 'per_page' => 10, 'total' => 95, 'total_pages' => 10], 'item_count' => 10, 'route' => 'items', 'route_params' => ['org' => 1], 'query' => ['q' => 'x']], $fw];
            yield 'row actions '.$fw => ['_row_actions', ['actions' => [
                ['kind' => 'edit', 'href' => '/e'],
                ['kind' => 'delete', 'href' => '/d', 'method' => 'post', 'csrf_token' => 't', 'confirm_message' => 'Sure?', 'attr' => ['data-x' => '1']],
                ['kind' => 'delete', 'confirm_id' => 'c1'],
                ['kind' => 'view', 'href' => '/v', 'confirm_id' => 'c2', 'disabled' => true],
                ['kind' => 'config', 'modal_id' => 'm1', 'label' => 'Settings', 'variant' => 'primary', 'tag' => 'button', 'attr' => ['data-y' => '2']],
                ['kind' => 'share', 'href' => '/s', 'modal_id' => 'm2', 'attr' => ['data-z' => '3']],
                ['kind' => 'copy', 'visible' => false],
            ], 'display' => 'icon_text'], $fw];
        }
        yield 'pagination empty' => ['_pagination', ['pagination' => ['page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 0], 'item_count' => 0, 'route' => 'items'], 'bootstrap5'];
        yield 'pagination single page' => ['_pagination', ['pagination' => ['page' => 1, 'total' => 3], 'item_count' => 3, 'route' => 'items', 'trans_domain' => 'admin', 'nav_label' => 'nav.x'], 'bootstrap5'];
        yield 'toasts' => ['_toasts', ['flashes' => ['success' => ['Saved'], 'error' => [['title' => 'Oops', 'message' => 'Broken']], 'notice' => ['FYI'], 'warning' => [['title' => 'Careful']]]], 'bootstrap5'];
        yield 'toasts none' => ['_toasts', ['flashes' => []], 'bootstrap5'];
        yield 'flashes' => ['_flashes', [], 'bootstrap5'];
        yield 'page header' => ['_page_header', ['title' => 'Items', 'intro' => 'All of them', 'toolbar' => '<a href="/n">New</a>'], 'bootstrap5'];
        yield 'page header bare' => ['_page_header', ['title' => 'Items'], 'tailwind'];
        yield 'confirm' => ['_confirm', ['id' => 'c1', 'title' => 'Delete?', 'body' => '<p>Gone</p>'], 'bootstrap5'];
        yield 'confirm footer' => ['_confirm', ['id' => 'c2', 'title' => 'Delete?', 'footer' => '<button>x</button>', 'cancel_label' => 'No'], 'custom'];
        yield 'empty' => ['_empty', [], 'bootstrap5'];
        yield 'empty message' => ['_empty', ['message' => 'Nothing <here>'], 'tailwind'];
        yield 'burger' => ['_burger', [], 'bootstrap5'];
        yield 'burger rail' => ['_burger', ['rail' => true, 'controls' => 'side', 'expanded' => false, 'label' => 'Menu', 'label_hide' => 'Hide'], 'tailwind'];
        yield 'shell' => ['_shell', ['brand' => 'Acme', 'name' => 'Ann', 'initials' => 'A', 'aside_items' => [], 'footer_copyright' => '(c)', 'main' => '<p>main</p>'], 'bootstrap5'];
        foreach (['bootstrap5', 'bootstrap4', 'tailwind', 'foundation', 'custom'] as $fw) {
            yield 'card '.$fw => ['_card', ['title' => 'Stats <b>', 'intro' => 'Last 7 days', 'body' => '<p>42</p>', 'footer' => '<a href="/more">More</a>', 'toolbar' => '<button>x</button>'], $fw];
        }
        yield 'card body only' => ['_card', ['body' => '<p>Only</p>'], 'bootstrap5'];
        yield 'card empty body' => ['_card', ['body' => '', 'title' => 'T'], 'tailwind'];
        yield 'card framework arg' => ['_card', ['title' => 'T', 'intro' => '', 'toolbar' => '', 'footer' => '', 'framework' => 'tailwind'], 'bootstrap5'];
        yield 'card nothing' => ['_card', [], 'custom'];
        foreach (['bootstrap5', 'tailwind', 'foundation', 'custom'] as $fw) {
            yield 'tabs '.$fw => ['_tabs', ['items' => [['label' => 'General', 'href' => '/g'], ['label' => 'Mail <b>', 'href' => '/m', 'current' => true, 'attr' => ['data-x' => '1']]], 'aria_label' => 'Sections'], $fw];
        }
        yield 'tabs default label' => ['_tabs', ['items' => [], 'framework' => 'tailwind'], 'bootstrap5'];
        yield 'shell rail' => ['_shell', ['brand_html' => '<b>A</b>', 'sidebar_rail' => ['storage' => 's', 'cookie' => 'c', 'hidden_value' => 'h', 'default' => 'rail'], 'sidebar_mode' => 'collapsed', 'show_user_menu' => false, 'header_end' => '<i>e</i>'], 'custom'];
    }

    /**
     * @param array<string, mixed> $vars
     */
    #[DataProvider('legacyInputs')]
    public function testLegacyInputsRenderAsIn117(string $partial, array $vars, string $framework): void
    {
        foreach ([false, true] as $withTheme) {
            $twig = $this->twig($framework, $withTheme);
            $legacy = $this->normalize($twig->render('@Legacy/'.$partial.'.html.twig', $vars));
            $current = $this->normalize($twig->render('@NowoUiKitBundle/partials/'.$partial.'.html.twig', $vars));
            self::assertSame($legacy, $current, $partial.($withTheme ? ' (ThemeExtension)' : ' (bare)'));
        }
    }

    private function twig(string $framework, bool $withTheme): Environment
    {
        $root = PageContractTwig::root();
        $fs = new FilesystemLoader();
        $fs->addPath($root.'/src/Resources/views', 'NowoUiKitBundle');
        $fs->addPath($root.'/tests/Fixtures/legacy-1.17', 'Legacy');
        $twig = new Environment(new ChainLoader([new ArrayLoader([]), $fs]), ['strict_variables' => false]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $name, array $params = []): string => '/'.$name.'?'.http_build_query($params)));
        $twig->addGlobal('nowo_ui_kit_css_framework', $framework);
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');
        $twig->addGlobal('app', ['request' => new Request([], [], ['_route' => 'items_index', '_route_params' => []]), 'flashes' => ['success' => ['Saved'], 'danger' => ['Failed'], 'notice' => ['Note']]]);
        if ($withTheme) {
            $twig->addExtension(new ThemeExtension(new Theme($framework)));
        }

        return $twig;
    }

    private function normalize(string $html): string
    {
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('/\s*(<|>)\s*/', '$1', $html);

        return trim((string) preg_replace('/\s+(\/?>)/', '$1', $html));
    }
}
