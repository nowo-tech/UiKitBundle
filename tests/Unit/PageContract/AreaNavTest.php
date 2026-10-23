<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\Navigation\AreaNavRegistry;
use Nowo\UiKitBundle\Twig\AreaNavExtension;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Area navigation (2.1): PageHeader::tabs rendered by the page_header partial through the tabs partial, the provider
 * registry, the Twig function and the DI wiring.
 */
final class AreaNavTest extends TestCase
{
    public function testPageHeaderRendersTheAreaNavThroughTheTabsPartial(): void
    {
        $nav = new AreaNav([
            new AreaNavItem('Consents', '/consents', true),
            new AreaNavItem(new Label('tabs.nav', 'NowoUiKitBundle'), '/categories', attributes: ['data-x' => '1']),
        ], 'Cookie consent', ['data-turbo' => 'false']);
        $twig = PageContractTwig::create([], ['classes' => ['tabs' => 'app-tabs'], 'attributes' => ['tabs' => ['data-host' => '1']]]);

        $html = $twig->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage(new PageHeader('Consents', null, [Action::create('/new')], [], null, $nav), [])]);

        self::assertMatchesRegularExpression('#<div class="nowo-ui-page-header__nav">\s*<nav class="nowo-ui-tabs nav nav-tabs mb-3 app-tabs" aria-label="Cookie consent" data-host="1" data-turbo="false" data-nowo-ui-region="area-nav">#', $html);
        self::assertMatchesRegularExpression('#href="/consents"\s+aria-current="page"#', $html);
        self::assertStringContainsString('>Sections</a>', $html);
        self::assertStringContainsString('data-x="1"', $html);
        // the area nav sits after the toolbar, inside the header root
        self::assertLessThan(strpos($html, 'nowo-ui-page-header__nav'), strpos($html, 'nowo-ui-action--create'));
    }

    public function testPageHeaderRendersTheBackLinkAboveTheTitleAndKeepsItOnCopies(): void
    {
        $twig = PageContractTwig::create([], ['classes' => ['back_link' => 'app-back']]);
        $header = new PageHeader('Project', null, [Action::create('/new')], back: new BackLink('/projects', 'Back to projects', 'project-back'));

        $html = $twig->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage($header, [])]);

        self::assertMatchesRegularExpression('#<a href="/projects" class="nowo-ui-back-link [^"]*app-back" data-testid="project-back">#', $html);
        self::assertLessThan(strpos($html, '<h1'), strpos($html, 'nowo-ui-back-link'));
        self::assertSame($header->back, $header->withActions([])->back);
        self::assertSame($header->back, $header->withTabs(null)->back);
    }

    public function testDefaultAriaLabelLabelObjectsAndPartialOverride(): void
    {
        $twig = PageContractTwig::create(['host/_tabs.html.twig' => '<nav data-host-tabs="{{ aria_label }}">{% for i in items %}{{ i.label }}{{ i.current ? "*" }};{% endfor %}</nav>'], ['partials' => ['tabs' => 'host/_tabs.html.twig']]);
        $header = new PageHeader('X', tabs: new AreaNav([new AreaNavItem('A', '/a'), new AreaNavItem('B', '/b', true)], new Label('tabs.nav', 'NowoUiKitBundle')));

        $html = $twig->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => $header]);

        self::assertStringContainsString('<nav data-host-tabs="Sections">A;B*;</nav>', $html);
        $plain = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => new PageHeader('X', tabs: new AreaNav([new AreaNavItem('A', '/a')]))]);
        self::assertStringContainsString('aria-label="Sections"', $plain);
        $empty = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_page_header.html.twig', ['header' => new PageHeader('X', tabs: new AreaNav([]))]);
        self::assertStringNotContainsString('nowo-ui-page-header__nav', $empty);
    }

    public function testViewModels(): void
    {
        $nav = new AreaNav([new AreaNavItem('A', '/a', true), new AreaNavItem('B', '/b')]);
        self::assertFalse($nav->isEmpty());
        self::assertSame('/a', $nav->active()?->href);
        $moved = $nav->withActive('/b');
        self::assertSame('/b', $moved->active()?->href);
        self::assertNull($nav->withActive('/none')->active());
        self::assertTrue((new AreaNav([]))->isEmpty());

        $header = (new PageHeader('T', null, [Action::create('/n')]))->withTabs($nav);
        self::assertSame($nav, $header->tabs);
        self::assertSame($nav, $header->withActions([])->tabs);
        self::assertSame([], $header->withActions([])->actions);
    }

    public function testInvalidItemsAndAttributesAreRefused(): void
    {
        try {
            new AreaNav(['x']);
            self::fail('items');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('AreaNav items', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        new AreaNavItem('A', '/a', attributes: ['onclick' => 'x()']);
    }

    public function testRegistryFirstProviderWinsAndMarksTheCurrentItem(): void
    {
        $none = new class implements AreaNavProviderInterface {
            public function areaNav(string $area, array $context = []): ?AreaNav
            {
                return null;
            }
        };
        $cookie = new class implements AreaNavProviderInterface {
            public function areaNav(string $area, array $context = []): ?AreaNav
            {
                return 'cookie_consent' === $area ? new AreaNav([new AreaNavItem('Consents', '/c', true), new AreaNavItem('Settings', '/s')]) : null;
            }
        };
        $registry = new AreaNavRegistry([$none, $cookie]);

        self::assertNull($registry->get('other'));
        self::assertFalse($registry->has('other'));
        self::assertTrue($registry->has('cookie_consent'));
        self::assertSame('/c', $registry->get('cookie_consent')?->active()?->href);
        self::assertSame('/s', $registry->get('cookie_consent', ['current' => '/s'])?->active()?->href);

        $extension = new AreaNavExtension($registry);
        self::assertSame('nowo_ui_area_nav', $extension->getFunctions()[0]->getName());
        self::assertSame('/s', $extension->areaNav('cookie_consent', ['current' => '/s'])?->active()?->href);
        self::assertNull($extension->areaNav('x'));
    }

    public function testServicesAreWired(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        $registry = $container->getDefinition('nowo_ui_kit.area_nav_registry');
        self::assertSame(AreaNavRegistry::class, $registry->getClass());
        $argument = $registry->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $argument);
        self::assertSame(AreaNavProviderInterface::TAG, $argument->getTag());
        self::assertSame('nowo_ui_kit.area_nav_registry', (string) $container->getAlias(AreaNavRegistry::class));
        self::assertTrue($container->getDefinition('nowo_ui_kit.area_nav_twig_extension')->hasTag('twig.extension'));
        self::assertArrayHasKey(AreaNavProviderInterface::class, $container->getAutoconfiguredInstanceof());
    }
}
