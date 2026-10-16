<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Twig\RouteLinkExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class RouteLinkExtensionTest extends TestCase
{
    private const ROUTES = [
        'admin' => ['path' => 'manual/05.md#admin'],
        'admin_users' => ['path' => 'manual/05.md#users', 'testid' => null],
        'admin_resend' => ['path' => 'manual/10.md', 'testid' => 'manual-link-resend'],
        'help' => ['path' => '/help'],
        'ext' => ['path' => 'https://example.org/docs'],
    ];

    public function testLongestPrefixWinsAndBaseUrlIsPrepended(): void
    {
        $ext = new RouteLinkExtension(self::ROUTES, 'https://docs.example.org/', 'manual-link');

        self::assertSame(
            ['url' => 'https://docs.example.org/manual/05.md#users', 'path' => 'manual/05.md#users', 'testid' => 'manual-link', 'prefix' => 'admin_users'],
            $ext->link('admin_users_edit'),
        );
        self::assertSame('manual-link-resend', $ext->link('admin_resend')['testid'] ?? null);
        self::assertSame('admin', $ext->link('admin_groups')['prefix'] ?? null);
        self::assertSame('/help', $ext->link('help')['url'] ?? null);
        self::assertSame('https://example.org/docs', $ext->link('ext_page')['url'] ?? null);
        // "adminx" is not "admin" nor "admin_…".
        self::assertNull($ext->link('adminx'));
        self::assertNull($ext->link(''));
    }

    public function testCurrentRouteFromTheRequestStack(): void
    {
        $stack = new RequestStack();
        $ext = new RouteLinkExtension(self::ROUTES, '', null, $stack);
        self::assertNull($ext->link());

        $request = new Request();
        $request->attributes->set('_route', 'admin_users_index');
        $stack->push($request);
        self::assertSame('manual/05.md#users', $ext->link()['url'] ?? null);
        self::assertNull($ext->link()['testid'] ?? null);

        self::assertNull((new RouteLinkExtension())->link());
    }

    public function testTwigFunction(): void
    {
        $twig = new Environment(new ArrayLoader([
            't' => "{% set l = nowo_ui_route_link('admin_resend') %}{{ l.url }}|{{ l.testid }}|{{ nowo_ui_route_link('nope') is null ? 'none' }}",
        ]));
        $twig->addExtension(new RouteLinkExtension(self::ROUTES, 'https://d/'));

        self::assertSame('https://d/manual/10.md|manual-link-resend|none', $twig->render('t'));
    }

    public function testConfigurationAndService(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'route_links' => [
                'base_url' => 'https://d/',
                'default_testid' => 'manual-link',
                'routes' => ['admin' => 'manual/05.md', 'admin_resend' => ['path' => 'manual/10.md', 'testid' => 'x']],
            ],
        ]]);
        self::assertSame([
            'base_url' => 'https://d/',
            'default_testid' => 'manual-link',
            'routes' => [
                'admin' => ['path' => 'manual/05.md', 'testid' => null],
                'admin_resend' => ['path' => 'manual/10.md', 'testid' => 'x'],
            ],
        ], $config['route_links']);

        $defaults = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertSame(['base_url' => '', 'default_testid' => null, 'routes' => []], $defaults['route_links']);

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['route_links' => ['routes' => ['admin' => 'a.md']]]], $container);
        $definition = $container->getDefinition('nowo_ui_kit.route_link_twig_extension');
        self::assertSame(RouteLinkExtension::class, $definition->getClass());
        self::assertTrue($definition->hasTag('twig.extension'));
        self::assertSame(['admin' => ['path' => 'a.md', 'testid' => null]], $definition->getArgument(0));
        self::assertEquals(['base_url' => '', 'default_testid' => null, 'routes' => ['admin' => ['path' => 'a.md', 'testid' => null]]], $container->getParameter('nowo_ui_kit.route_links'));
    }

    public function testRejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['route_links' => ['routes' => ['admin' => '']]]]);
    }
}
