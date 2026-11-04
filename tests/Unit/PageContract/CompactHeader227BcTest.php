<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\Shell\HeaderPresentationResolver;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.27 compact header is opt-in (`header_context` on the shells, `presentation` on the page header, the `page_actions`
 * partial): with the option left empty the shells, the page header, the split header actions, `_menu` hash items and
 * the `_scripts` output render byte-identical to UiKit 2.26. The fixtures under tests/Fixtures/bc-2.26 were rendered by
 * UiKit 2.26.0 (origin/main a75f286) from exactly these scenarios; no whitespace normalisation.
 *
 * Each scenario renders the 2.26 call and, where 2.27 adds an option whose "empty" value must change nothing, the same
 * call with that value too (`header_context: ''`, `header_context: '   '`, `presentation: 'standard'`).
 */
final class CompactHeader227BcTest extends TestCase
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
    public function testOutputWithoutTheNewOptionsIsAsIn226(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.26/'.$name.'.html';
        self::assertFileExists($file);
        $expected = (string) file_get_contents($file);
        self::assertSame($expected, self::renders()[$name](), $name.' must render exactly as UiKit 2.26 printed it');
        foreach (self::variants()[$name] ?? [] as $variant => $render) {
            self::assertSame($expected, $render(), $name.' ('.$variant.') must render exactly as UiKit 2.26 printed it');
        }
    }

    /**
     * Scenario name => render callback (2.26 API only).
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $renders = [];
        foreach (self::shellOptions() as $set => $options) {
            $renders['app_shell_open_'.$set] = static fn (): string => self::halves($options);
            $renders['app_shell_'.$set] = static fn (): string => self::twig()->render('shell', ['opts' => $options]);
            $renders['app_shell_blocks_'.$set] = static fn (): string => self::twig()->render('shell_blocks', ['opts' => $options]);
            $renders['legacy_shell_'.$set] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_shell.html.twig', self::legacyOptions($options));
        }
        $renders['page_header_partial'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => self::header()]);
        $renders['page_header_partial_split'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => self::header()->withSplitActions()]);
        $renders['page_header_contract'] = static fn (): string => self::twig()->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header(), 'extra_actions' => [Action::view('/orders/7/print', 'Print')]]);
        $renders['page_header_contract_split'] = static fn (): string => self::twig()->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header()->withSplitActions()]);
        $renders['page_header_function'] = static fn (): string => self::twig()->render('fn', ['header' => self::header()]);
        $renders['page_header_function_split'] = static fn (): string => self::twig()->render('fn', ['header' => self::header()->withSplitActions()]);
        $renders['page_header_without_actions'] = static fn (): string => self::twig()->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header(), 'header_actions' => false]);
        $renders['show_page_split'] = static fn (): string => self::twig()->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage(self::header()->withSplitActions(), [])]);
        $renders['header_actions'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_header_actions.html.twig', ['actions' => self::actions()]);
        $renders['header_actions_options'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_header_actions.html.twig', [
            'actions' => self::actions(),
            'more_label' => 'Order actions',
            'more_id' => 'order-more',
            'more_attr' => ['data-testid' => 'page-header-more'],
            'attr' => ['data-testid' => 'header-actions'],
            'extra' => '<button type="button">Watch</button>',
        ]);
        $renders['menu_hash_items'] = static function (): string {
            $twig = self::twig();
            $items = [
                ['label' => 'Profile', 'href' => '/profile', 'current' => true],
                ['label' => 'Billing', 'href' => '/billing', 'attr' => ['data-testid' => 'billing']],
                ['label' => 'Delete account', 'href' => '/delete', 'danger' => true],
            ];

            return $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => $items, 'id' => 'account'])
                .$twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => $items, 'id' => 'account-pop', 'popover' => true, 'icon' => 'more', 'display' => 'icon', 'square' => true])
                .$twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => $items, 'label' => 'Account', 'icon' => 'config', 'align' => 'start']);
        };
        $renders['scripts'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_scripts.html.twig');

        return $renders;
    }

    /**
     * 2.27 renders whose output must equal the 2.26 fixture of the same scenario.
     *
     * @return array<string, array<string, Closure(): string>>
     */
    public static function variants(): array
    {
        $variants = [];
        foreach (self::shellOptions() as $set => $options) {
            foreach (['empty' => '', 'blank' => "  \n "] as $variant => $context) {
                $with = $options + ['header_context' => $context];
                $variants['app_shell_open_'.$set][$variant] = static fn (): string => self::halves($with);
                $variants['app_shell_'.$set][$variant] = static fn (): string => self::twig()->render('shell', ['opts' => $with]);
                $variants['legacy_shell_'.$set][$variant] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_shell.html.twig', self::legacyOptions($with));
            }
            $variants['app_shell_blocks_'.$set]['blank block'] = static fn (): string => self::twig()->render('shell_blocks_context', ['opts' => $options]);
        }
        $standard = ['presentation' => 'standard'];
        $variants['page_header_partial']['standard'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => self::header()] + $standard);
        $variants['page_header_partial_split']['standard'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => self::header()->withSplitActions()] + $standard);
        $variants['page_header_contract']['standard'] = static fn (): string => self::twig()->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header(), 'extra_actions' => [Action::view('/orders/7/print', 'Print')]] + $standard);
        $variants['page_header_contract_split']['standard'] = static fn (): string => self::twig()->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header()->withSplitActions()] + $standard);
        $variants['page_header_function']['standard'] = static fn (): string => self::twig()->render('fn_standard', ['header' => self::header()]);
        $variants['page_header_function']['options hash'] = static fn (): string => self::twig()->render('fn_options', ['header' => self::header()]);
        $variants['page_header_function_split']['standard'] = static fn (): string => self::twig()->render('fn_standard', ['header' => self::header()->withSplitActions()]);
        // compact_actions: bar changes nothing in the standard presentation
        $variants['page_header_contract_split']['compact_actions bar'] = static fn (): string => self::twig(['presentation' => 'standard', 'compact_actions' => 'bar'])->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header()->withSplitActions()]);

        return array_filter($variants, static fn (string $name): bool => self::supports227(), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * Writes the fixtures (run once against the reference revision, never in CI).
     */
    public static function dumpFixtures(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        foreach (self::renders() as $name => $render) {
            file_put_contents($dir.'/'.$name.'.html', $render());
        }
    }

    /**
     * Whether the tree under test is 2.27 or later (the variants use 2.27 options); false while dumping on 2.26.
     */
    private static function supports227(): bool
    {
        return str_contains((string) file_get_contents(PageContractTwig::root().'/src/Resources/views/shell/_open.html.twig'), 'header_context');
    }

    /**
     * @param array{presentation: string, compact_actions: string}|array{} $header nowo_ui_kit.header config (2.27); ignored on 2.26
     */
    private static function twig(array $header = []): Environment
    {
        $request = Request::create('https://app.example/orders/7', 'GET');
        $request->attributes->set('_route', 'orders_show');
        $request->attributes->set('_route_params', []);

        $twig = PageContractTwig::create([
            'halves' => "{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id=\"main-content\" tabindex=\"-1\">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {footer_copyright: '© Acme'}) }}",
            'shell' => "{{ include(nowo_ui_partial('app_shell'), opts|merge({main: '<main id=\"main-content\">BODY</main>'})) }}",
            'shell_blocks' => "{% embed '@NowoUiKitBundle/shell/_shell.html.twig' with opts %}{% block nowo_ui_shell_brand %}<a href=\"/\">Acme</a>{% endblock %}{% block nowo_ui_shell_header_actions %}<a href=\"/search\">Search</a>{% endblock %}{% block nowo_ui_shell_main %}<main id=\"main-content\">BODY</main>{% endblock %}{% endembed %}",
            'shell_blocks_context' => "{% embed '@NowoUiKitBundle/shell/_shell.html.twig' with opts %}{% block nowo_ui_shell_brand %}<a href=\"/\">Acme</a>{% endblock %}{% block nowo_ui_shell_header_actions %}<a href=\"/search\">Search</a>{% endblock %}{% block nowo_ui_shell_header_context %}  \n  {% endblock %}{% block nowo_ui_shell_main %}<main id=\"main-content\">BODY</main>{% endblock %}{% endembed %}",
            'fn' => '{{ nowo_ui_page_header(header) }}',
            'fn_standard' => "{{ nowo_ui_page_header(header, presentation: 'standard') }}",
            'fn_options' => "{{ nowo_ui_page_header(header, {presentation: 'standard'}) }}",
        ], request: $request, breadcrumbKit: false, shell: ['kit_styles' => true, 'sidebar_cookie' => null, 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => null]);
        if ([] !== $header) {
            $twig->addGlobal('nowo_ui_header', new HeaderPresentationResolver(null, $header['presentation'], $header['compact_actions']));
        }

        return $twig;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function halves(array $options): string
    {
        return self::twig()->render('halves', ['opts' => $options]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function shellOptions(): array
    {
        return [
            'plain' => ['brand' => 'Acme', 'aside_items' => [['label' => 'Orders', 'href' => '/orders', 'current' => true]], 'aside_label' => 'Back office'],
            'rail' => ['brand_html' => '<a class="brand" href="/">Acme</a>', 'rail_mode' => true, 'rail' => true, 'header_end' => '<a href="/search">Search</a>', 'aside_items' => [['label' => 'Orders', 'href' => '/orders']]],
            'collapsed' => ['brand' => 'Acme', 'collapsed' => true, 'content_width' => 'full', 'aside_html' => '<nav>NAV</nav>', 'shell_attr' => ['data-testid' => 'shell']],
            'user_menu' => ['brand' => 'Acme', 'name' => 'Ada Lovelace', 'initials' => 'AL', 'email' => 'ada@example.com', 'avatar_src' => '/images/ada.svg', 'user_links' => [['label' => 'Profile', 'href' => '/profile']], 'logout_href' => '/logout', 'header_end' => '<a href="/help">Help</a>'],
        ];
    }

    /**
     * The legacy `shell` partial reads `sidebar_rail` / `sidebar_mode` instead of `rail_mode` / `rail`.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function legacyOptions(array $options): array
    {
        if (true === ($options['rail_mode'] ?? false)) {
            $options['sidebar_rail'] = ['storage' => 'nav', 'cookie' => 'nav'];
            $options['sidebar_mode'] = 'rail';
        }
        unset($options['rail_mode'], $options['rail'], $options['content_width'], $options['shell_attr']);

        return $options + ['main' => '<main id="main-content">BODY</main>'];
    }

    private static function header(): PageHeader
    {
        return new PageHeader(
            'Q-1042',
            'Quote for ACME',
            self::actions(),
            [new Crumb('Sales', '/sales'), new Crumb('Quotes', '/sales/quotes'), new Crumb('Q-1042')],
            status: new PageStatus('Draft', 'warning'),
        );
    }

    /**
     * @return list<Action>
     */
    private static function actions(): array
    {
        return [
            Action::edit('/quotes/7/edit')->asIcon(),
            Action::create('/quotes/new', 'New quote'),
            new Action('export', 'Export', '/quotes/7.pdf'),
            new Action('copy', 'Duplicate', '/quotes/7/copy', method: 'POST', csrfToken: 'tok-copy'),
            Action::delete('/quotes/7', 'tok-del', new Confirm('Delete Q-1042?', 'This cannot be undone.')),
            new Action('share', 'Share', '/quotes/7/share', visible: false),
        ];
    }
}
