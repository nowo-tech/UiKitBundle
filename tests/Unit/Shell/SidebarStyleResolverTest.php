<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Shell;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Shell\SidebarStyle;
use Nowo\UiKitBundle\Shell\SidebarStyleResolver;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 2.52 sidebar style: option, then request attribute (current, then main request), then config; invalid values never
 * break a page; the config node and the Twig extension argument; one resolver serves many requests (FrankenPHP worker,
 * kernel reset false) without carrying a user's value into the next request.
 */
final class SidebarStyleResolverTest extends TestCase
{
    public function testEnum(): void
    {
        self::assertSame(['classic', 'inset'], SidebarStyle::values());
        self::assertSame('_nowo_ui_sidebar_style', SidebarStyle::ATTRIBUTE);
        self::assertSame(SidebarStyle::Inset, SidebarStyle::fromValue('inset'));
        self::assertSame(SidebarStyle::Classic, SidebarStyle::fromValue(SidebarStyle::Classic));
        self::assertNull(SidebarStyle::fromValue('Inset'));
        self::assertNull(SidebarStyle::fromValue(null));
        self::assertNull(SidebarStyle::fromValue(1));
    }

    public function testResolutionOrder(): void
    {
        self::assertSame('classic', (new SidebarStyleResolver())->resolve());
        self::assertSame('inset', (new SidebarStyleResolver(null, 'inset'))->resolve());
        self::assertSame('classic', (new SidebarStyleResolver(null, 'nonsense'))->resolve(), 'an unknown default means classic');

        $stack = new RequestStack();
        $request = new Request();
        $stack->push($request);
        $resolver = new SidebarStyleResolver($stack);
        self::assertSame('classic', $resolver->resolve());
        self::assertFalse($resolver->isInset());

        $request->attributes->set(SidebarStyle::ATTRIBUTE, 'inset');
        self::assertSame('inset', $resolver->resolve());
        self::assertTrue($resolver->isInset());
        self::assertSame('classic', $resolver->resolve('classic'), 'the explicit option wins');
        self::assertSame('classic', $resolver->resolve(SidebarStyle::Classic));
        self::assertSame('inset', $resolver->resolve('floating'), 'an invalid option is ignored');
        self::assertSame('inset', $resolver->resolve(new stdClass()));

        $request->attributes->set(SidebarStyle::ATTRIBUTE, SidebarStyle::Inset);
        self::assertSame('inset', $resolver->resolve());

        $request->attributes->set(SidebarStyle::ATTRIBUTE, 'invalid');
        self::assertSame('inset', (new SidebarStyleResolver($stack, 'inset'))->resolve(), 'an invalid attribute falls back to the config');
        self::assertSame('classic', (new SidebarStyleResolver($stack))->resolve());
    }

    public function testSubRequestFollowsTheMainRequest(): void
    {
        $stack = new RequestStack();
        $main = new Request();
        $main->attributes->set(SidebarStyle::ATTRIBUTE, 'inset');
        $stack->push($main);
        $stack->push(new Request());

        self::assertSame('inset', (new SidebarStyleResolver($stack))->resolve());

        $sub = new Request();
        $sub->attributes->set(SidebarStyle::ATTRIBUTE, 'classic');
        $stack->push($sub);
        self::assertSame('classic', (new SidebarStyleResolver($stack, 'inset'))->resolve(), 'the current request wins');
    }

    /**
     * Worker mode: the same resolver instance serves alternating users; each request sees only its own preference and a
     * request without one falls back to the config, never to the previous user's value. 2.52.1 (audit RK-L2): the order
     * is classic → inset → none, so a request without a preference always follows an inset one and an inset leak would
     * show; the inset-config resolver runs the mirror order (inset config, a classic user, then none).
     */
    public function testOneInstanceAcrossManyRequestsKeepsNoState(): void
    {
        foreach (['classic' => ['classic', 'inset'], 'inset' => ['inset', 'classic']] as $config => [$first, $preference]) {
            $stack = new RequestStack();
            $resolver = new SidebarStyleResolver($stack, $config);
            for ($i = 0; $i < 1000; ++$i) {
                $request = new Request();
                $expected = $config;
                if (0 === $i % 3) {
                    $request->attributes->set(SidebarStyle::ATTRIBUTE, $first);
                    $expected = $first;
                } elseif (1 === $i % 3) {
                    $request->attributes->set(SidebarStyle::ATTRIBUTE, $preference);
                    $expected = $preference;
                }
                $stack->push($request);
                self::assertSame($expected, $resolver->resolve(), 'request '.$i.' (config '.$config.')');
                $stack->pop();
            }
            self::assertNull($stack->getCurrentRequest());
            self::assertSame($config, $resolver->resolve(), 'no request: the config default');
        }
    }

    public function testConfiguration(): void
    {
        $processor = new Processor();
        $defaults = $processor->processConfiguration(new Configuration(), [[]]);
        self::assertSame('classic', $defaults['shell']['sidebar_style']);

        $config = $processor->processConfiguration(new Configuration(), [['shell' => ['sidebar_style' => 'inset']]]);
        self::assertSame('inset', $config['shell']['sidebar_style']);

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['shell' => ['sidebar_style' => 'inset']]], $container);
        self::assertSame('inset', $container->getDefinition('nowo_ui_kit.theme_twig_extension')->getArgument('$sidebarStyle'));

        $defaultContainer = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $defaultContainer);
        self::assertSame('classic', $defaultContainer->getDefinition('nowo_ui_kit.theme_twig_extension')->getArgument('$sidebarStyle'));

        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [['shell' => ['sidebar_style' => 'floating']]]);
    }
}
