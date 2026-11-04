<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Shell\HeaderCrumbs;
use Nowo\UiKitBundle\Shell\HeaderPresentation;
use Nowo\UiKitBundle\Shell\HeaderPresentationResolver;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\TwigFunction;

/**
 * 2.27 compact header (contract .uic-work/uikit-compact-header/CONTRACT.md): the shells' header_context slot, the
 * header_context / header_crumbs / page_actions partials, the compact page-header presentation and its resolution,
 * nowo_ui_breadcrumb_kit_trail(), the config keys and the new tokens. Byte identity with the option left empty is in
 * CompactHeader227BcTest.
 */
final class CompactHeaderTest extends TestCase
{
    private const SHELL = "{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id=\"main-content\" tabindex=\"-1\">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {footer_html: false}) }}";

    // --- Presentation -------------------------------------------------------------------------------------------

    public function testPresentationEnum(): void
    {
        self::assertSame(['standard', 'compact'], HeaderPresentation::values());
        self::assertSame('_nowo_ui_header_presentation', HeaderPresentation::ATTRIBUTE);
        self::assertSame(HeaderPresentation::Compact, HeaderPresentation::fromRequestValue(HeaderPresentation::Compact));
        self::assertSame(HeaderPresentation::Compact, HeaderPresentation::fromRequestValue('compact'));
        self::assertNull(HeaderPresentation::fromRequestValue('tiny'));
        self::assertNull(HeaderPresentation::fromRequestValue(1));
        self::assertSame(HeaderPresentation::Standard, HeaderPresentation::fromOption(HeaderPresentation::Standard));
        self::assertSame(HeaderPresentation::Compact, HeaderPresentation::fromOption('compact'));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header presentation "tiny" (standard|compact).');
        HeaderPresentation::fromOption('tiny');
    }

    public function testPresentationResolvesOptionThenRequestAttributeThenConfig(): void
    {
        $stack = new RequestStack();
        $resolver = new HeaderPresentationResolver($stack, 'compact', 'bar');
        self::assertSame('compact', $resolver->resolve(), 'no request: the config');
        self::assertSame('bar', $resolver->compactActions());

        $stack->push(new Request(attributes: [HeaderPresentation::ATTRIBUTE => 'standard']));
        self::assertSame('standard', $resolver->resolve(), 'the request attribute beats the config');
        self::assertSame('compact', $resolver->resolve('compact'), 'the option beats the request attribute');
        self::assertSame('compact', $resolver->resolve(HeaderPresentation::Compact));
        self::assertTrue($resolver->isCompact('compact'));
        self::assertFalse($resolver->isCompact());

        // Worker: the next request on the same kernel carries nothing over.
        $stack->pop();
        $stack->push(new Request(attributes: [HeaderPresentation::ATTRIBUTE => HeaderPresentation::Compact]));
        self::assertSame('compact', (new HeaderPresentationResolver($stack))->resolve());
        $stack->pop();
        $stack->push(new Request());
        self::assertSame('standard', (new HeaderPresentationResolver($stack))->resolve());
        $stack->pop();
        $stack->push(new Request(attributes: [HeaderPresentation::ATTRIBUTE => 'nonsense']));
        self::assertSame('standard', (new HeaderPresentationResolver($stack))->resolve(), 'an invalid request value is ignored');
        self::assertSame('standard', (new HeaderPresentationResolver($stack, 'unknown'))->resolve(), 'an unknown config value means standard');
        self::assertSame('page', (new HeaderPresentationResolver(null, 'standard', 'nowhere'))->compactActions());

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve('nonsense');
    }

    public function testConfigKeysAreValidatedAndReachTheTwigExtension(): void
    {
        $processor = new Processor();
        self::assertSame(['presentation' => 'standard', 'compact_actions' => 'page'], $processor->processConfiguration(new Configuration(), [[]])['header']);
        self::assertSame(['presentation' => 'compact', 'compact_actions' => 'bar'], $processor->processConfiguration(new Configuration(), [['header' => ['presentation' => 'compact', 'compact_actions' => 'bar']]])['header']);
        foreach ([['presentation' => 'tiny'], ['compact_actions' => 'aside']] as $invalid) {
            try {
                $processor->processConfiguration(new Configuration(), [['header' => $invalid]]);
                self::fail('invalid header config accepted');
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        (new NowoUiKitExtension())->load([['header' => ['compact_actions' => 'bar']]], $container);
        self::assertEquals(['presentation' => 'standard', 'compact_actions' => 'bar'], $container->getDefinition('nowo_ui_kit.theme_twig_extension')->getArgument('$header'));
    }

    public function testTwigApiOfTheExtension(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request(attributes: [HeaderPresentation::ATTRIBUTE => 'compact']));
        $extension = new ThemeExtension(new Theme(), requestStack: $stack, header: ['presentation' => 'standard', 'compact_actions' => 'bar']);
        $names = array_map(static fn (TwigFunction $f): string => $f->getName(), $extension->getFunctions());
        self::assertContains('nowo_ui_header_presentation', $names);
        self::assertContains('nowo_ui_breadcrumb_kit_trail', $names);
        self::assertContains('nowo_ui_header_crumbs', $names);
        self::assertSame('compact', $extension->headerPresentation());
        self::assertSame('standard', $extension->headerPresentation('standard'));
        $global = $extension->getGlobals()['nowo_ui_header'];
        self::assertInstanceOf(HeaderPresentationResolver::class, $global);
        self::assertSame('bar', $global->compactActions());
        self::assertSame('standard', (new ThemeExtension(new Theme()))->headerPresentation());
    }

    // --- Shell slot ---------------------------------------------------------------------------------------------

    public function testShellHalvesRenderTheSlotBetweenStartAndEnd(): void
    {
        $theme = ['classes' => ['app_header_context' => 'app-bar-context'], 'attributes' => ['app_header_context' => ['data-tour' => 'bar']]];
        $html = PageContractTwig::create(['t' => self::SHELL], $theme)->render('t', ['opts' => [
            'brand' => 'Acme',
            'header_end' => '<a href="/help">Help</a>',
            'header_context' => '<b>CTX</b>',
            'show_user_menu' => false,
        ]]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell nowo-ui-shell is-content-width has-header-context" data-nowo-ui-shell>', $html);
        self::assertMatchesRegularExpression('#nowo-ui-header__start">.*?</div>\n        <div class="nowo-ui-app-shell__header-context app-bar-context nowo-ui-header__context" data-nowo-ui-header-context data-tour="bar">\s*<b>CTX</b>\s*</div>\n        <div class="nowo-ui-app-shell__header-end#s', $html);
        self::assertLessThan(strpos($html, 'nowo-ui-header__end'), strpos($html, 'data-nowo-ui-header-context'));

        $bare = PageContractTwig::create(['t' => self::SHELL])->render('t', ['opts' => ['brand' => 'Acme', 'header_context' => '<b>CTX</b>', 'kit_styles' => false]]);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__header-context" data-nowo-ui-header-context>', $bare);
        self::assertStringContainsString('is-content-width has-header-context"', $bare);
    }

    public function testShellBlockAndLegacyShell(): void
    {
        $twig = PageContractTwig::create([
            'block' => "{% embed '@NowoUiKitBundle/shell/_shell.html.twig' with {brand: 'Acme', show_user_menu: false} %}{% block nowo_ui_shell_header_context %}  <b>BLOCK</b>  {% endblock %}{% block nowo_ui_shell_main %}<main>BODY</main>{% endblock %}{% endembed %}",
            'option' => "{{ include(nowo_ui_partial('app_shell'), {brand: 'Acme', show_user_menu: false, header_context: '<b>BLOCK</b>', main: '<main>BODY</main>'}) }}",
        ]);
        $block = $twig->render('block');
        self::assertSame($block, $twig->render('option'), 'the block and the option render the same markup');
        self::assertStringContainsString("data-nowo-ui-header-context>\n            <b>BLOCK</b>\n        </div>", $block);
        self::assertStringContainsString(' has-header-context"', $block);

        $legacy = PageContractTwig::create(theme: ['classes' => ['app_header_context' => 'legacy-ctx']])->render('@NowoUiKitBundle/partials/_shell.html.twig', ['brand' => 'Acme', 'header_context' => '<b>L</b>', 'main' => 'BODY']);
        self::assertStringContainsString('has-header-context"', $legacy);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__header-context legacy-ctx nowo-ui-header__context" data-nowo-ui-header-context>', $legacy);
        self::assertLessThan(strpos($legacy, 'nowo-ui-header__end'), strpos($legacy, '<b>L</b>'));
    }

    // --- header_context partial ---------------------------------------------------------------------------------

    public function testHeaderContextOrderAndOmissions(): void
    {
        $twig = PageContractTwig::create(theme: ['classes' => ['header_context' => 'acme-ctx'], 'attributes' => ['header_context' => ['data-tour' => 'ctx']]]);
        $full = $twig->render('@NowoUiKitBundle/partials/_header_context.html.twig', [
            'actions' => [Action::edit('/q/7/edit'), Action::delete('/q/7', 'tok', new Confirm('Delete?'))],
            'home_href' => '/dashboard',
            'crumbs' => [new Crumb('Home', '/dashboard'), new Crumb('Quotes', '/quotes'), new Crumb('Q-1042')],
            'attr' => ['data-testid' => 'bar'],
        ]);
        self::assertStringStartsWith('<div class="nowo-ui-header-context acme-ctx" data-nowo-ui-header-context-inner data-tour="ctx" data-testid="bar">', $full);
        $trigger = strpos($full, 'data-nowo-ui-page-actions');
        $home = strpos($full, 'nowo-ui-header-context__home');
        $trail = strpos($full, 'data-nowo-ui-header-crumbs');
        self::assertNotFalse($trigger);
        self::assertTrue($trigger < $home && $home < $trail, 'trigger, home, trail');
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-btn--icon nowo-ui-header-context__home" href="/dashboard" aria-label="Home" title="Home"><i class="bi bi-house" aria-hidden="true"></i></a>', $full);
        self::assertStringContainsString('nowo-ui-menu--start', $full, 'the bar menu opens towards the content');
        self::assertStringNotContainsString('>Home</a>', $full, 'the home crumb is dropped (the home link stands for it)');
        self::assertStringContainsString('<dialog', $full, 'confirm dialogs render after the menu');
        self::assertGreaterThan(strpos($full, '</ul>'), strpos($full, '<dialog'));

        self::assertSame('', $twig->render('@NowoUiKitBundle/partials/_header_context.html.twig', ['crumbs' => []]), 'all three empty: nothing');
        $onlyHome = $twig->render('@NowoUiKitBundle/partials/_header_context.html.twig', ['crumbs' => [], 'home_href' => '/', 'home_label' => new Label('nav.home', 'NowoUiKitBundle'), 'home_icon' => 'chevron_left']);
        self::assertStringContainsString('bi bi-chevron-left', $onlyHome);
        self::assertStringNotContainsString('data-nowo-ui-page-actions', $onlyHome);
        self::assertStringNotContainsString('data-nowo-ui-header-crumbs', $onlyHome);
        $onlyActions = $twig->render('@NowoUiKitBundle/partials/_header_context.html.twig', ['crumbs' => [], 'action_items' => [['label' => 'Run', 'href' => '/run']], 'actions_label' => 'Monitor actions', 'actions_icon' => 'more', 'actions_id' => 'bar-actions', 'actions_attr' => ['data-controller' => 'page-actions'], 'actions_align' => 'end']);
        self::assertStringContainsString('id="bar-actions" data-nowo-ui-page-actions data-controller="page-actions"', $onlyActions);
        self::assertStringContainsString('aria-label="Monitor actions"', $onlyActions);
        self::assertStringContainsString('bi-three-dots-vertical', $onlyActions);
        self::assertStringContainsString('nowo-ui-menu--end', $onlyActions);
        self::assertStringNotContainsString('header-context__home', $onlyActions);
        $onlyTrail = $twig->render('@NowoUiKitBundle/partials/_header_context.html.twig', ['crumbs' => [new Crumb('Quotes')], 'crumbs_options' => ['id' => 'bar-trail', 'max_visible' => 2]]);
        self::assertStringContainsString('data-nowo-ui-header-crumbs', $onlyTrail);
        self::assertStringNotContainsString('data-nowo-ui-page-actions', $onlyTrail);
        $this->assertNoInlineCode($full.$onlyHome.$onlyActions.$onlyTrail);
    }

    // --- header_crumbs ------------------------------------------------------------------------------------------

    public function testHeaderCrumbsMarkup(): void
    {
        $html = $this->crumbs([
            'crumbs' => [new Crumb('Home', '/'), new Crumb('Sales', '/sales'), new Crumb('Customers', '/sales/customers'), new Crumb('ACME', '/sales/customers/acme'), new Crumb('Quotes', '/sales/quotes'), new Crumb('Q-1042', '/sales/quotes/1042')],
            'home_href' => '/?tab=1',
        ]);
        self::assertStringStartsWith('<nav class="nowo-ui-header-crumbs" aria-label="Breadcrumb" data-nowo-ui-header-crumbs data-collapsible="true">', $html);
        self::assertStringContainsString('<a class="nowo-ui-header-crumbs__up" href="/sales/quotes" data-nowo-ui-header-crumbs-up><span class="nowo-ui-header-crumbs__up-glyph" aria-hidden="true">‹</span><span class="visually-hidden">Up to Quotes</span></a>', $html);
        self::assertStringContainsString('<li class="nowo-ui-header-crumbs__item"><a class="nowo-ui-header-crumbs__link" href="/sales" title="Sales">Sales</a></li>', $html);
        self::assertStringContainsString('<li class="nowo-ui-header-crumbs__item nowo-ui-header-crumbs__item--more" data-nowo-ui-header-crumbs-more hidden>', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-header-crumbs__more" popovertarget="nowo-ui-header-crumbs-panel" aria-controls="nowo-ui-header-crumbs-panel" aria-expanded="false" aria-label="Show full path" title="Show full path" data-nowo-ui-popover-trigger><span aria-hidden="true">…</span></button>', $html);
        self::assertStringContainsString('<ul class="nowo-ui-header-crumbs__panel" id="nowo-ui-header-crumbs-panel" popover data-nowo-ui-popover-panel>', $html);
        self::assertStringContainsString('<li><a class="nowo-ui-header-crumbs__panel-link" href="/sales/customers">Customers</a></li>', $html);
        self::assertStringContainsString('<li class="nowo-ui-header-crumbs__item nowo-ui-header-crumbs__item--middle"><a class="nowo-ui-header-crumbs__link" href="/sales/customers/acme" title="ACME">ACME</a></li>', $html);
        self::assertStringContainsString('<li class="nowo-ui-header-crumbs__item nowo-ui-header-crumbs__item--current"><span class="nowo-ui-header-crumbs__current" aria-current="page" title="Q-1042">Q-1042</span></li>', $html);
        self::assertSame(1, substr_count($html, 'aria-current'), 'aria-current on the last level only');
        self::assertStringNotContainsString('href="/sales/quotes/1042"', $html, 'the current level is never a link');
        self::assertStringNotContainsString('>Home<', $html, 'drop_home_crumb: query ignored');
        // DOM order: first level, "…", middle levels, the rest
        self::assertTrue(strpos($html, '>Sales<') < strpos($html, '--more') && strpos($html, '--more') < strpos($html, 'title="Customers"'));
        $this->assertNoInlineCode($html);
    }

    public function testHeaderCrumbsEscapingTextLevelsIdsAndHashes(): void
    {
        $html = $this->crumbs([
            'crumbs' => [['label' => '<img src=x onerror=alert(1)>', 'href' => '/a'], ['label' => 'Archive'], ['label' => new Label('breadcrumbs.nav', 'NowoUiKitBundle'), 'href' => '/b'], ['label' => 'Last "one"', 'href' => '']],
            'max_visible' => 2,
            'id' => 'second-trail',
            'attr' => ['data-testid' => 'trail'],
            'drop_home_crumb' => false,
        ]);
        self::assertStringContainsString('title="&lt;img src=x onerror=alert(1)&gt;">&lt;img src=x onerror=alert(1)&gt;</a>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<span class="nowo-ui-header-crumbs__text" title="Archive">Archive</span>', $html, 'a level without link is a plain span');
        self::assertStringContainsString('<li><span class="nowo-ui-header-crumbs__panel-text">Archive</span></li>', $html);
        self::assertStringContainsString('title="Breadcrumb">Breadcrumb</a>', $html, 'labels are translated');
        self::assertStringContainsString('title="Last &quot;one&quot;">Last &quot;one&quot;</span>', $html);
        self::assertStringContainsString('popovertarget="second-trail-panel"', $html);
        self::assertStringContainsString('id="second-trail-panel"', $html);
        self::assertStringContainsString(' data-testid="trail">', $html);
        self::assertStringContainsString('href="/b" data-nowo-ui-header-crumbs-up', $html, 'up: the nearest linked ancestor');

        $flat = $this->crumbs(['crumbs' => [new Crumb('Sales'), new Crumb('Quotes')]]);
        self::assertStringNotContainsString('data-collapsible', $flat);
        self::assertStringNotContainsString('--more', $flat);
        self::assertStringNotContainsString('header-crumbs__up', $flat, 'no linked ancestor: no up link');
        self::assertSame('', $this->crumbs(['crumbs' => []]));
    }

    public function testHeaderCrumbsRejectsOnAttributesAndUnknownItems(): void
    {
        try {
            $this->crumbs(['crumbs' => [new Crumb('A')], 'attr' => ['onclick' => 'x()']]);
            self::fail('on* accepted');
        } catch (RuntimeError $e) {
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
        }
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessageMatches('/expects View.Crumb items/');
        $this->crumbs(['crumbs' => [42]]);
    }

    public function testTruncatedLabelsKeepTheFullTextForAssistiveTechnology(): void
    {
        $long = 'Q-1042 · Annual maintenance renewal for ACME Corporation, northern region';
        $ancestor = 'Customers of the northern region and partners';
        $html = $this->crumbs(['crumbs' => [new Crumb($ancestor, '/c'), new Crumb('Quotes', '/q'), new Crumb($long)], 'max_visible' => 2]);
        // The DOM text is the whole label (CSS truncates it visually), so the accessible name is complete; title repeats it.
        self::assertStringContainsString('<span class="nowo-ui-header-crumbs__current" aria-current="page" title="'.$long.'">'.$long.'</span>', $html);
        self::assertStringContainsString('title="'.$ancestor.'">'.$ancestor.'</a>', $html);
        self::assertStringContainsString('<li><a class="nowo-ui-header-crumbs__panel-link" href="/q">Quotes</a></li>', $html, 'collapsed levels keep their full text in the panel');
        self::assertStringNotContainsString('aria-hidden="true">'.$long, $html);
        self::assertStringNotContainsString('…</span></li>', $html, 'no server-side ellipsis');
        $model = (new ThemeExtension(new Theme()))->headerCrumbs(PageContractTwig::create(), [new Crumb($long)]);
        self::assertSame($long, $model->items[0]['label']);
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: list<int>}>
     */
    public static function collapseCases(): iterable
    {
        foreach ([2, 3, 4] as $max) {
            for ($levels = 1; $levels <= 7; ++$levels) {
                $middle = $levels > $max ? range(1, $levels - $max) : [];
                yield $levels.' levels, max '.$max => [$levels, $max, $middle];
            }
        }
    }

    /**
     * @param list<int> $middle
     */
    #[DataProvider('collapseCases')]
    public function testCollapseModel(int $levels, int $max, array $middle): void
    {
        $crumbs = [];
        for ($i = 0; $i < $levels; ++$i) {
            $crumbs[] = ['label' => 'L'.$i, 'href' => '/l'.$i];
        }
        $model = HeaderCrumbs::build($crumbs, null, true, $max);
        $marked = array_keys(array_filter($model->items, static fn (array $item): bool => $item['middle']));
        self::assertSame($middle, $marked);
        self::assertSame(array_map(static fn (int $i): string => 'L'.$i, $middle), array_column($model->panel, 'label'));
        self::assertSame([] !== $middle, $model->isCollapsible());
        self::assertTrue($model->items[$levels - 1]['current']);
        self::assertNull($model->items[$levels - 1]['href']);
        self::assertSame(1, \count(array_filter($model->items, static fn (array $item): bool => $item['current'])));
        self::assertSame($levels > 1 ? '/l'.($levels - 2) : null, $model->up['href'] ?? null);

        $html = $this->crumbs(['crumbs' => $crumbs, 'max_visible' => $max]);
        self::assertSame(\count($middle), substr_count($html, '--middle'));
        self::assertSame([] !== $middle ? 1 : 0, substr_count($html, 'data-collapsible="true"'));
    }

    public function testHomeCrumbDropAndModelEdges(): void
    {
        $home = [['label' => 'Home', 'href' => 'https://app.example/dashboard/?x=1'], ['label' => 'Quotes', 'href' => '/quotes']];
        self::assertSame(['Quotes'], array_column(HeaderCrumbs::build($home, '/dashboard')->items, 'label'));
        self::assertSame(['Home', 'Quotes'], array_column(HeaderCrumbs::build($home, '/dashboard', false)->items, 'label'));
        self::assertSame(['Home', 'Quotes'], array_column(HeaderCrumbs::build($home, '/other')->items, 'label'));
        self::assertSame(['Home'], array_column(HeaderCrumbs::build([$home[0]], '/dashboard')->items, 'label'), 'the only level is never dropped');
        self::assertSame(['Home', 'Quotes'], array_column(HeaderCrumbs::build([['label' => 'Home', 'href' => null], $home[1]], '/')->items, 'label'));
        self::assertSame(['Quotes'], array_column(HeaderCrumbs::build([['label' => 'Home', 'href' => 'https://app.example'], $home[1]], '/')->items, 'label'), 'no path = /');
        self::assertTrue((new HeaderCrumbs())->isEmpty());
        self::assertFalse((new HeaderCrumbs())->isCollapsible());
        self::assertNull(HeaderCrumbs::build([['label' => 'A', 'href' => null], ['label' => 'B', 'href' => '/b']])->up);
        $clamped = HeaderCrumbs::build([['label' => 'A', 'href' => null], ['label' => 'B', 'href' => null], ['label' => 'C', 'href' => null]], null, true, 1);
        self::assertSame([false, true, false], array_column($clamped->items, 'middle'), 'max_visible is at least 2');
    }

    public function testHeaderSourcesOwnKitAutoAndTheKitTrailFunction(): void
    {
        $node = static fn (string $label, ?string $url, bool $linked = true): object => new class($label, $url, $linked) {
            public function __construct(public string $label, public ?string $url, public bool $linkEnabled)
            {
            }
        };
        $calls = [];
        $kitTwig = static function (bool $flag, array $trail) use (&$calls): Environment {
            $twig = PageContractTwig::create(breadcrumbKit: $flag);
            $twig->addFunction(new TwigFunction('breadcrumb_trail', static function (?string $collection = null, string $contextKey = '') use ($trail, &$calls): mixed {
                $calls[] = [$collection, $contextKey];

                return $trail;
            }));

            return $twig;
        };
        $own = [new Crumb('Own A', '/own'), new Crumb('Own B')];
        $kit = [$node('Kit A', '/kit'), $node('Kit B', '/kit/b', false), $node('Kit C', null), 'not a node'];

        $twig = $kitTwig(true, $kit);
        $render = static fn (Environment $t, string $mode): string => $t->render('@NowoUiKitBundle/partials/_header_crumbs.html.twig', ['header' => new PageHeader('T', null, [], $own, 'admin', breadcrumbsMode: $mode), 'drop_home_crumb' => false]);
        self::assertStringContainsString('Own A', $render($twig, 'own'));
        self::assertStringNotContainsString('Kit A', $render($twig, 'own'));
        $kitHtml = $render($twig, 'kit');
        self::assertStringContainsString('href="/kit" title="Kit A"', $kitHtml);
        self::assertStringContainsString('<span class="nowo-ui-header-crumbs__text" title="Kit B">Kit B</span>', $kitHtml, 'linkEnabled: false → no href');
        self::assertStringContainsString('Kit C', $render($twig, 'auto'));
        self::assertSame(['admin', ''], $calls[0]);

        $empty = $kitTwig(true, []);
        self::assertStringContainsString('Own A', $render($empty, 'auto'), 'auto: an empty kit trail falls back to the header');
        self::assertSame('', $render($empty, 'kit'));
        $off = $kitTwig(false, $kit);
        self::assertStringContainsString('Own A', $render($off, 'auto'), 'breadcrumb_kit: false keeps the header trail');

        // No source: nowo_ui_breadcrumb_kit_trail(), which ignores breadcrumb_kit: false.
        self::assertStringContainsString('Kit A', $off->render('@NowoUiKitBundle/partials/_header_crumbs.html.twig'));
        $mapped = $off->createTemplate("{% for c in nowo_ui_breadcrumb_kit_trail('main', 'ctx') %}{{ c.label }}={{ c.href ?? '-' }};{% endfor %}")->render();
        self::assertSame('Kit A=/kit;Kit B=-;Kit C=-;', $mapped);
        self::assertSame(['main', 'ctx'], $calls[\count($calls) - 1]);
        $nodesAsCrumbs = $off->render('@NowoUiKitBundle/partials/_header_crumbs.html.twig', ['crumbs' => [$node('N1', '/n1'), $node('N2', '/n2')]]);
        self::assertStringContainsString('href="/n1" title="N1"', $nodesAsCrumbs);

        $odd = PageContractTwig::create();
        $odd->addFunction(new TwigFunction('breadcrumb_trail', static fn (): string => 'not a list'));
        self::assertSame([], (new ThemeExtension(new Theme()))->breadcrumbKitTrail($odd));
        $weird = PageContractTwig::create();
        $weird->addFunction(new TwigFunction('breadcrumb_trail', static fn (): array => [new class {
            /** @var list<string> */
            public array $label = ['x'];
        }]));
        self::assertSame('', (new ThemeExtension(new Theme()))->breadcrumbKitTrail($weird)[0]->label);
        self::assertSame([], (new ThemeExtension(new Theme()))->breadcrumbKitTrail(PageContractTwig::create()), 'no BreadcrumbKit: []');
        self::assertSame('', PageContractTwig::create()->render('@NowoUiKitBundle/partials/_header_crumbs.html.twig'));
        self::assertSame(3, \count((new ThemeExtension(new Theme()))->headerCrumbs($off, null, null, ['max_visible' => 'many'])->items));
    }

    // --- page_actions and _menu ---------------------------------------------------------------------------------

    public function testPageActionsPartial(): void
    {
        $twig = PageContractTwig::create(theme: ['classes' => ['page_actions' => 'acme-actions'], 'attributes' => ['page_actions' => ['data-tour' => 'actions']]]);
        self::assertSame('', $twig->render('@NowoUiKitBundle/partials/_page_actions.html.twig'));
        self::assertSame('', $twig->render('@NowoUiKitBundle/partials/_page_actions.html.twig', ['actions' => [new Action('share', 'Share', '/s', visible: false)]]), 'hidden actions only: nothing');

        $html = ltrim($twig->render('@NowoUiKitBundle/partials/_page_actions.html.twig', ['actions' => [
            new Action('export', 'Export', '/x.csv'),
            new Action('copy', 'Duplicate', '/copy', method: 'POST', csrfToken: 't'),
            Action::delete('/d', 't', new Confirm('Delete?')),
            new Action('custom', 'Details', '#', dialogId: 'details-dialog'),
            new Action('share', 'Share', '/s', visible: false),
        ]]));
        self::assertStringStartsWith('<div class="nowo-ui-menu nowo-ui-menu--end nowo-ui-menu--popover nowo-ui-page-actions acme-actions" data-nowo-ui-menu data-nowo-ui-popover-menu id="nowo-ui-page-actions" data-nowo-ui-page-actions data-tour="actions">', $html);
        self::assertStringContainsString('popovertarget="nowo-ui-page-actions-panel" aria-haspopup="menu" aria-expanded="false" data-nowo-ui-popover-trigger aria-label="Page actions" title="Page actions"><i class="bi bi-grid" aria-hidden="true"></i><span class="visually-hidden">Page actions</span></button>', $html);
        self::assertStringContainsString('<li><a class="nowo-ui-menu__item" href="/x.csv">Export</a></li>', $html);
        self::assertStringContainsString('action="/copy"', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-open="details-dialog"', $html);
        self::assertStringNotContainsString('Share', $html);
        self::assertSame(1, substr_count($html, '<dialog'));
        self::assertGreaterThan(strpos($html, '</ul>'), strpos($html, '<dialog'), 'confirm dialogs after the menu');

        // The same markup as the split header's "more" menu apart from the label, icon, id and root slot.
        $actions = [new Action('export', 'Export', '/x.csv'), Action::delete('/d', 't', new Confirm('Delete?'))];
        $split = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_header_actions.html.twig', ['actions' => $actions]);
        $bar = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_page_actions.html.twig', ['actions' => $actions, 'label' => 'More actions', 'icon' => 'more', 'id' => 'nowo-ui-header-more', 'attr' => ['data-nowo-ui-header-more' => true]]);
        $bar = str_replace([' nowo-ui-page-actions"', ' data-nowo-ui-page-actions'], ['"', ''], $bar);
        self::assertStringContainsString(trim($bar), $split);
    }

    public function testMenuHashItemsWithIconsAndButtons(): void
    {
        $twig = PageContractTwig::create();
        $html = $twig->render('@NowoUiKitBundle/partials/_page_actions.html.twig', ['items' => [
            ['label' => 'Open drawer', 'icon' => 'view', 'attr' => ['data-action' => 'page-actions#open', 'data-page-actions-opens-param' => '#drawer']],
            ['label' => 'Docs', 'href' => '/docs', 'icon' => 'book', 'current' => true],
            ['label' => 'Remove', 'danger' => true],
        ]]);
        self::assertStringContainsString('<li><button type="button" class="nowo-ui-menu__item nowo-ui-menu__item--icon" data-action="page-actions#open" data-page-actions-opens-param="#drawer" data-nowo-ui-menu-button><i class="bi bi-eye" aria-hidden="true"></i>Open drawer</button></li>', $html);
        self::assertStringContainsString('<li><a class="nowo-ui-menu__item is-current nowo-ui-menu__item--icon" href="/docs" aria-current="page"><i class="bi bi-book" aria-hidden="true"></i>Docs</a></li>', $html);
        self::assertStringContainsString('<li><button type="button" class="nowo-ui-menu__item nowo-ui-menu__item--danger" data-nowo-ui-menu-button>Remove</button></li>', $html);

        $none = PageContractTwig::create();
        $none->addGlobal('nowo_ui_kit_icon_set', 'none');
        self::assertStringContainsString('<li><a class="nowo-ui-menu__item" href="/docs">Docs</a></li>', $none->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => [['label' => 'Docs', 'href' => '/docs', 'icon' => 'book']]]), 'icon_set none: no glyph');

        $this->expectException(RuntimeError::class);
        $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => [['label' => 'X', 'attr' => ['onclick' => 'evil()']]]]);
    }

    // --- Compact page header ------------------------------------------------------------------------------------

    public function testCompactPageHeader(): void
    {
        $header = new PageHeader(
            'Quotes',
            'Every quote of the team',
            [Action::create('/quotes/new', 'New quote')],
            [new Crumb('Sales', '/sales'), new Crumb('Quotes')],
            back: new BackLink('/sales', 'Back'),
        );
        $twig = PageContractTwig::create(theme: ['classes' => ['page_header' => 'kit-admin-page-header']]);
        $html = $twig->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'presentation' => 'compact']);
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertMatchesRegularExpression('#<h1 class="[^"]*nowo-ui-page-header__title[^"]* visually-hidden">Quotes</h1>#', $html);
        self::assertStringContainsString('nowo-ui-page-header kit-admin-page-header nowo-ui-page-header--compact', str_replace(' d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', '', $html));
        self::assertStringContainsString('data-nowo-ui-page-header="compact" data-nowo-ui-region="header"', $html);
        self::assertStringNotContainsString('data-nowo-ui-page-header-empty', $html, 'the toolbar stays (compact_actions page)');
        self::assertStringNotContainsString('Every quote', $html);
        self::assertStringNotContainsString('aria-label="Breadcrumb"', $html);
        self::assertStringNotContainsString('href="/sales"', $html, 'no back link');
        self::assertStringContainsString('New quote', $html);
        $this->assertNoInlineCode($html);

        $bar = PageContractTwig::create(header: ['presentation' => 'compact', 'compact_actions' => 'bar']);
        $empty = $bar->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header]);
        self::assertStringNotContainsString('New quote', $empty, 'compact_actions bar: the actions leave the page');
        self::assertStringContainsString(' data-nowo-ui-page-header-empty', $empty);
        $extra = $bar->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'extra_actions' => [Action::view('/print', 'Print')]]);
        self::assertStringContainsString('Print', $extra, 'extra_actions stay');
        self::assertStringNotContainsString('New quote', $extra);
        self::assertStringNotContainsString('-empty', $extra);
        self::assertStringContainsString('New quote', $bar->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'presentation' => 'standard']), 'bar changes nothing in standard');

        $tabs = new PageHeader('Settings', null, [], tabs: new AreaNav([new AreaNavItem('General', '/g', true)]), status: new PageStatus('Draft', 'warning'));
        $withTabs = $bar->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $tabs]);
        self::assertStringContainsString('nowo-ui-page-header__nav', $withTabs, 'tabs stay');
        self::assertStringContainsString('Draft', $withTabs, 'the status badge stays');
        self::assertStringNotContainsString('-empty', $withTabs);
        self::assertStringNotContainsString('-empty', $bar->render('@NowoUiKit/page/_page_header.html.twig', ['header' => new PageHeader('S', null, [], status: new PageStatus('Up', 'success'))]));
        self::assertStringContainsString('-empty', $bar->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['title' => 'T', 'toolbar' => '']));
        self::assertStringNotContainsString('-empty', $bar->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['title' => 'T', 'toolbar' => '<b>x</b>']));

        // The request attribute selects compact for every kit page; the option still wins.
        $request = new Request(attributes: ['_route' => 'quotes', HeaderPresentation::ATTRIBUTE => 'compact']);
        $req = PageContractTwig::create(request: $request);
        self::assertStringContainsString('visually-hidden">Quotes</h1>', $req->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header]));
        self::assertStringNotContainsString('visually-hidden">Quotes</h1>', $req->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'presentation' => 'standard']));
        self::assertStringContainsString('visually-hidden">Quotes</h1>', $req->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage($header, [])]), 'scaffolds follow the request');
        $list = $req->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage($header)]);
        self::assertStringContainsString('visually-hidden">Quotes</h1>', $list);
        $fn = PageContractTwig::create(['f' => "{{ nowo_ui_page_header(header, {presentation: 'compact', bundle: 'X', extra_actions: extra, header_actions: false}) }}|{{ nowo_ui_page_header(header, presentation: 'compact') }}"]);
        $both = $fn->render('f', ['header' => $header, 'extra' => [Action::view('/p', 'Print')]]);
        self::assertSame(2, substr_count($both, 'visually-hidden">Quotes</h1>'));
        self::assertStringContainsString('Print', $both);
    }

    public function testHostOverridesReceiveThePresentation(): void
    {
        $override = "<header data-p=\"{{ presentation }}\" data-h=\"{{ title_hidden ? 'y' : 'n' }}\">{{ title }}|{{ intro ?? 'no intro' }}</header>";
        $twig = PageContractTwig::create(['host/_ph.html.twig' => $override], ['partials' => ['page_header' => 'host/_ph.html.twig']]);
        $header = new PageHeader('Quotes', 'Intro');
        self::assertSame('<header data-p="compact" data-h="y">Quotes|no intro</header>', $this->trimmed($twig, '@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'presentation' => 'compact']));
        self::assertSame('<header data-p="standard" data-h="n">Quotes|Intro</header>', $this->trimmed($twig, '@NowoUiKit/page/_page_header.html.twig', ['header' => $header]));
        $legacy = PageContractTwig::create(['host/_ph.html.twig' => '<header>{{ title }}|{{ intro ?? "none" }}</header>'], ['partials' => ['page_header' => 'host/_ph.html.twig']]);
        self::assertSame('<header>Quotes|none</header>', $this->trimmed($legacy, '@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'presentation' => 'compact']), 'a 1.x override receives intro: null');

        // Without UiKit's extension (no nowo_ui_header global) the partial still takes an explicit presentation.
        self::assertStringContainsString('visually-hidden', PageContractTwig::create()->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['title' => 'T', 'title_hidden' => true]));
    }

    // --- Assets, tokens, translations, slots ----------------------------------------------------------------------

    public function testTokensIconsSlotsAndScript(): void
    {
        self::assertContains('header-crumbs-panel-shadow', Theme::LIGHT_ONLY_TOKENS);
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        foreach (['--nowo-ui-header-context-gap', '--nowo-ui-header-crumbs-font-size', '--nowo-ui-header-crumbs-color', '--nowo-ui-header-crumbs-current-color', '--nowo-ui-header-crumbs-current-weight', '--nowo-ui-header-crumbs-separator', '--nowo-ui-header-crumbs-separator-rtl', '--nowo-ui-header-crumbs-item-max', '--nowo-ui-header-crumbs-current-min', '--nowo-ui-header-crumbs-focus-inset', '--nowo-ui-header-crumbs-panel-bg', '--nowo-ui-header-crumbs-panel-border', '--nowo-ui-header-crumbs-panel-shadow', '--nowo-ui-main-padding-top-compact'] as $token) {
            self::assertStringContainsString('var('.$token, $css, $token);
        }
        self::assertStringContainsString('@media (max-width: 30rem)', $css);
        self::assertNotContains('header-crumbs', ThemeSlots::SCRIPTS, 'opt-in, like tree and side-panel');
        self::assertFileExists(PageContractTwig::root().'/src/Resources/public/js/nowo-ui-header-crumbs.js');
        foreach (['header_context', 'header_crumbs', 'page_actions'] as $partial) {
            self::assertSame('@NowoUiKitBundle/partials/_'.$partial.'.html.twig', ThemeSlots::PARTIALS[$partial]);
        }
        foreach (['app_header_context', 'header_context', 'header_context_home', 'header_crumbs', 'header_crumbs_list', 'header_crumbs_item', 'header_crumbs_link', 'header_crumbs_current', 'header_crumbs_up', 'header_crumbs_more', 'header_crumbs_panel', 'header_crumbs_panel_link', 'page_actions'] as $slot) {
            self::assertTrue(ThemeSlots::has($slot), $slot);
        }
        $icons = PageContractTwig::create(['i' => "{% for n in ['home', 'grid', 'chevron_left'] %}{{ include('@NowoUiKitBundle/components/_icon.html.twig', {name: n}) }}{% endfor %}"]);
        $html = $icons->render('i');
        self::assertStringContainsString('bi bi-house', $html);
        self::assertStringContainsString('bi bi-grid', $html);
        self::assertStringContainsString('bi bi-chevron-left', $html);
        foreach (['tabler-icons' => 'ti ti-home', 'ux_icon' => 'bi:house', 'svg_inline' => 'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z'] as $set => $expected) {
            $twig = PageContractTwig::create(['i' => "{{ include('@NowoUiKitBundle/components/_icon.html.twig', {name: 'home'}) }}"]);
            $twig->addGlobal('nowo_ui_kit_icon_set', $set);
            if ('ux_icon' === $set) {
                $twig->addFunction(new TwigFunction('ux_icon', static fn (string $name): string => $name));
            }
            self::assertStringContainsString($expected, $twig->render('i'), $set);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function trimmed(Environment $twig, string $template, array $context): string
    {
        return trim($twig->render($template, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function crumbs(array $context): string
    {
        return PageContractTwig::create()->render('@NowoUiKitBundle/partials/_header_crumbs.html.twig', $context);
    }

    private function assertNoInlineCode(string $html): void
    {
        self::assertSame(0, preg_match('/\sstyle=|<style|<script|\son[a-z]+=/i', $html), 'CSP: no inline style, script or handler');
    }
}
