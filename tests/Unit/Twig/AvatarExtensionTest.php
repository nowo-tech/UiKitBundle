<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Twig\AvatarExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AvatarExtensionTest extends TestCase
{
    public function testTheFilterAppliesThePolicyAndStaysEscaped(): void
    {
        $twig = new Environment(new ArrayLoader([
            't' => '[{{ url|nowo_ui_avatar_url }}]',
            'null' => '{{ url|nowo_ui_avatar_url is null ? "null" : "url" }}',
        ]));
        $twig->addExtension(new AvatarExtension());

        self::assertSame('[/files/a/s64?x=1&amp;y=2]', $twig->render('t', ['url' => '/files/a/s64?x=1&y=2']));
        self::assertSame('null', $twig->render('null', ['url' => '/files/a/s64?x=1&y="2"']));
        self::assertSame('[https://cdn.example.com/a.webp]', $twig->render('t', ['url' => 'https://cdn.example.com/a.webp']));
        self::assertSame('null', $twig->render('null', ['url' => 'data:image/png;base64,AAAA']));
        self::assertSame('null', $twig->render('null', ['url' => null]));
        self::assertSame('null', $twig->render('null', ['url' => ['/a']]));
    }

    public function testTheExtensionIsRegistered(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([], $container);

        $definition = $container->getDefinition('nowo_ui_kit.avatar_twig_extension');
        self::assertSame(AvatarExtension::class, $definition->getClass());
        self::assertTrue($definition->hasTag('twig.extension'));
    }
}
