<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Theme;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use Nowo\UiKitBundle\Theme\FontPreload;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * 2.21 font preload (Beacon KIT #24): `nowo_ui_kit.font_preload`, {@see FontPreload} and `nowo_ui_font_preload()`.
 */
final class FontPreloadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-font-preload-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function testFontUrlsArePrintedWithTheirType(): void
    {
        $preload = new FontPreload(['/fonts/inter-400.woff2', '/fonts/old.woff?v=2', '/fonts/a.ttf', '/fonts/b.otf', '/fonts/c.eot', 'https://cdn.test/x".woff2']);

        self::assertTrue($preload->isEnabled());
        self::assertSame(
            '<link rel="preload" href="/fonts/inter-400.woff2" as="font" type="font/woff2" crossorigin>'
            .'<link rel="preload" href="/fonts/old.woff?v=2" as="font" type="font/woff" crossorigin>'
            .'<link rel="preload" href="/fonts/a.ttf" as="font" type="font/ttf" crossorigin>'
            .'<link rel="preload" href="/fonts/b.otf" as="font" type="font/otf" crossorigin>'
            .'<link rel="preload" href="/fonts/c.eot" as="font" crossorigin>'
            .'<link rel="preload" href="https://cdn.test/x&quot;.woff2" as="font" type="font/woff2" crossorigin>',
            $preload->tags(),
        );
        self::assertFalse((new FontPreload())->isEnabled());
        self::assertSame('', (new FontPreload())->tags());
        self::assertFalse((new FontPreload([], $this->dir.'/manifest.json'))->isEnabled(), 'a manifest needs prefixes');
    }

    public function testViteManifestFontsAreMemoizedPerBuild(): void
    {
        $manifest = $this->dir.'/manifest.json';
        $preload = new FontPreload(['/fonts/icons.woff2'], $manifest, 'assets/app.ts', ['montserrat-latin-400-normal', 'montserrat-latin-600-normal', 'missing'], '/build/');
        self::assertSame('<link rel="preload" href="/fonts/icons.woff2" as="font" type="font/woff2" crossorigin>', $preload->tags(), 'no build yet: the configured URLs only');

        file_put_contents($manifest, json_encode(['assets/app.ts' => ['file' => 'assets/app-1.js', 'assets' => [
            'assets/montserrat-latin-600-normal-B2.woff2',
            'assets/montserrat-latin-400-normal-A1.woff',
            'assets/montserrat-latin-400-normal-A1.woff2',
            'assets/logo-C3.svg',
            42,
        ]]], \JSON_THROW_ON_ERROR));
        $tags = '<link rel="preload" href="/fonts/icons.woff2" as="font" type="font/woff2" crossorigin>'
            .'<link rel="preload" href="/build/assets/montserrat-latin-400-normal-A1.woff" as="font" type="font/woff" crossorigin>'
            .'<link rel="preload" href="/build/assets/montserrat-latin-600-normal-B2.woff2" as="font" type="font/woff2" crossorigin>';
        self::assertSame($tags, $preload->tags());

        // same mtime + size: the memo answers even if the content changed in place
        $same = (string) file_get_contents($manifest);
        file_put_contents($manifest, str_replace('B2', 'Z9', $same));
        touch($manifest, (int) filemtime($manifest));
        clearstatcache();
        self::assertSame($tags, $preload->tags());

        // a new build (other size): rebuilt
        file_put_contents($manifest, json_encode(['assets/app.ts' => ['assets' => ['assets/montserrat-latin-600-normal-NEW1.woff2']]], \JSON_THROW_ON_ERROR));
        touch($manifest, time() + 5);
        self::assertStringContainsString('montserrat-latin-600-normal-NEW1.woff2', $preload->tags());
        self::assertStringNotContainsString('A1', $preload->tags());

        // broken JSON / missing entry: nothing from the manifest
        file_put_contents($manifest, '{broken');
        touch($manifest, time() + 10);
        self::assertSame('<link rel="preload" href="/fonts/icons.woff2" as="font" type="font/woff2" crossorigin>', $preload->tags());
        $other = new FontPreload([], $manifest, 'assets/admin.ts', ['montserrat']);
        file_put_contents($manifest, json_encode(['assets/app.ts' => ['assets' => ['assets/montserrat-x.woff2']]], \JSON_THROW_ON_ERROR));
        self::assertSame('', $other->tags());
    }

    public function testTwigFunctionAndLayouts(): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{{ nowo_ui_font_preload() }}']));
        $twig->addExtension(new ThemeExtension(new Theme(), fontPreload: new FontPreload(['/f.woff2'])));
        self::assertSame('<link rel="preload" href="/f.woff2" as="font" type="font/woff2" crossorigin>', $twig->render('t'));

        $none = new Environment(new ArrayLoader(['t' => '[{{ nowo_ui_font_preload() }}]']));
        $none->addExtension(new ThemeExtension(new Theme()));
        self::assertSame('[]', $none->render('t'));

        $layout = PageContractTwig::create(['p' => "{% extends '@NowoUiKit/layout/document.html.twig' %}"], fontPreload: new FontPreload(['/f.woff2']))->render('p');
        self::assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1"><link rel="preload" href="/f.woff2" as="font" type="font/woff2" crossorigin>', $layout);
        $error = PageContractTwig::create(['e' => "{% extends '@NowoUiKit/error/status.html.twig' %}"], fontPreload: new FontPreload(['/f.woff2']))->render('e', ['status_code' => 404]);
        self::assertStringContainsString('viewport-fit=cover"><link rel="preload" href="/f.woff2" as="font" type="font/woff2" crossorigin>', $error);
    }

    public function testConfigurationAndService(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertSame(['fonts' => [], 'manifest' => ['path' => null, 'entry' => 'assets/app.js', 'prefixes' => [], 'base' => '/build/']], $config['font_preload']);

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        self::assertFalse($container->hasDefinition('nowo_ui_kit.font_preload'));
        self::assertNull($container->getDefinition('nowo_ui_kit.theme_twig_extension')->getArgument('$fontPreload'));

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['font_preload' => ['manifest' => ['path' => '/app/public/build/.vite/manifest.json']]]], $container);
        self::assertFalse($container->hasDefinition('nowo_ui_kit.font_preload'), 'a manifest without prefixes preloads nothing');

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['font_preload' => [
            'fonts' => ['/fonts/a.woff2'],
            'manifest' => ['path' => '/m.json', 'entry' => 'assets/app.ts', 'prefixes' => ['inter-400'], 'base' => '/b/'],
        ]]], $container);
        self::assertSame([['/fonts/a.woff2'], '/m.json', 'assets/app.ts', ['inter-400'], '/b/'], $container->getDefinition('nowo_ui_kit.font_preload')->getArguments());
        $argument = $container->getDefinition('nowo_ui_kit.theme_twig_extension')->getArgument('$fontPreload');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('nowo_ui_kit.font_preload', (string) $argument);
    }
}
