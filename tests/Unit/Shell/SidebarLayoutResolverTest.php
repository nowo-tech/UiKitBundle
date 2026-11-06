<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Shell;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Shell\SidebarLayout;
use Nowo\UiKitBundle\Shell\SidebarLayoutResolver;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 2.32 sidebar layout: option, then request attribute (current, then main request), then config; invalid values never
 * break a page; the config node and the Twig extension argument.
 */
final class SidebarLayoutResolverTest extends TestCase
{
    public function testEnum(): void
    {
        self::assertSame(['below-header', 'full-height'], SidebarLayout::values());
        self::assertSame('_nowo_ui_sidebar_layout', SidebarLayout::ATTRIBUTE);
        self::assertSame(SidebarLayout::FullHeight, SidebarLayout::fromValue('full-height'));
        self::assertSame(SidebarLayout::BelowHeader, SidebarLayout::fromValue(SidebarLayout::BelowHeader));
        self::assertNull(SidebarLayout::fromValue('top'));
        self::assertNull(SidebarLayout::fromValue(null));
        self::assertNull(SidebarLayout::fromValue(1));
    }

    public function testResolutionOrder(): void
    {
        self::assertSame('below-header', (new SidebarLayoutResolver())->resolve());
        self::assertSame('full-height', (new SidebarLayoutResolver(null, 'full-height'))->resolve());
        self::assertSame('below-header', (new SidebarLayoutResolver(null, 'nonsense'))->resolve(), 'an unknown default means below-header');

        $stack = new RequestStack();
        $request = new Request();
        $stack->push($request);
        $resolver = new SidebarLayoutResolver($stack);
        self::assertSame('below-header', $resolver->resolve());
        self::assertFalse($resolver->isFullHeight());

        $request->attributes->set(SidebarLayout::ATTRIBUTE, 'full-height');
        self::assertSame('full-height', $resolver->resolve());
        self::assertTrue($resolver->isFullHeight());
        self::assertSame('below-header', $resolver->resolve('below-header'), 'the explicit option wins');
        self::assertSame('below-header', $resolver->resolve(SidebarLayout::BelowHeader));
        self::assertSame('full-height', $resolver->resolve('sideways'), 'an invalid option is ignored');
        self::assertSame('full-height', $resolver->resolve(new stdClass()));

        $request->attributes->set(SidebarLayout::ATTRIBUTE, SidebarLayout::FullHeight);
        self::assertSame('full-height', $resolver->resolve());

        $request->attributes->set(SidebarLayout::ATTRIBUTE, 'invalid');
        self::assertSame('full-height', (new SidebarLayoutResolver($stack, 'full-height'))->resolve(), 'an invalid attribute falls back to the config');
    }

    public function testSubRequestFollowsTheMainRequest(): void
    {
        $stack = new RequestStack();
        $main = new Request();
        $main->attributes->set(SidebarLayout::ATTRIBUTE, 'full-height');
        $stack->push($main);
        $stack->push(new Request());

        self::assertSame('full-height', (new SidebarLayoutResolver($stack))->resolve());

        $sub = new Request();
        $sub->attributes->set(SidebarLayout::ATTRIBUTE, 'below-header');
        $stack->push($sub);
        self::assertSame('below-header', (new SidebarLayoutResolver($stack, 'full-height'))->resolve(), 'the current request wins');
    }

    public function testConfiguration(): void
    {
        $processor = new Processor();
        $defaults = $processor->processConfiguration(new Configuration(), [[]]);
        self::assertSame('below-header', $defaults['shell']['sidebar_layout']);
        self::assertFalse($defaults['shell']['hover_expand']);
        self::assertSame(['name' => null, 'href' => null, 'icon_src' => null], $defaults['shell']['brand']);

        $config = $processor->processConfiguration(new Configuration(), [['shell' => ['sidebar_layout' => 'full-height', 'hover_expand' => true, 'brand' => ['name' => 'Acme', 'href' => '/', 'icon_src' => '/logo.svg']]]]);
        self::assertSame('full-height', $config['shell']['sidebar_layout']);
        self::assertTrue($config['shell']['hover_expand']);
        self::assertSame(['name' => 'Acme', 'href' => '/', 'icon_src' => '/logo.svg'], $config['shell']['brand']);

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['shell' => ['sidebar_layout' => 'full-height']]], $container);
        self::assertSame('full-height', $container->getDefinition('nowo_ui_kit.theme_twig_extension')->getArgument('$sidebarLayout'));

        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [['shell' => ['sidebar_layout' => 'top']]]);
    }
}
