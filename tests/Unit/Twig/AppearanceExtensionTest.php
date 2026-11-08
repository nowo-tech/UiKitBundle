<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\Twig\AppearanceExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AppearanceExtensionTest extends TestCase
{
    public function testFunctionsRenderAppearanceStyleAndPresets(): void
    {
        $requestStack = new RequestStack();
        $twig = $this->twig([
            'style' => '{{ nowo_ui_appearance_style() }}',
            'explicit' => '{{ nowo_ui_appearance_style(\'a"b\') }}',
            'css' => '{{ nowo_ui_appearance_css() }}',
            'brand' => '{{ nowo_ui_appearance().brandName }}|{{ nowo_ui_appearance().light.accent }}',
            'presets' => '{{ nowo_ui_appearance_presets()|length }}|{% for p in nowo_ui_appearance_presets(\'dark\') %}{{ p.id }},{% endfor %}',
        ], $requestStack);

        $css = $twig->render('css');
        self::assertStringStartsWith(":root {\n  --nowo-ui-radius:", $css);
        self::assertSame("<style data-nowo-ui-appearance>\n".$css."\n</style>", $twig->render('style'));
        self::assertStringStartsWith('<style nonce="a&quot;b" data-nowo-ui-appearance>', $twig->render('explicit'));

        $request = new Request();
        $request->attributes->set('csp_nonce', 'n0nce');
        $requestStack->push($request);
        self::assertStringStartsWith('<style nonce="n0nce" data-nowo-ui-appearance>', $twig->render('style'));

        self::assertSame('Acme|#0e7490', $twig->render('brand'));
        self::assertSame('7|midnight,obsidian,aurora,ember,', $twig->render('presets'));
    }

    public function testAThrowingProviderGivesTheDefaults(): void
    {
        $registry = new AppearancePresetRegistry();
        $throwing = new class implements AppearanceProviderInterface {
            public function getAppearance(): Appearance
            {
                throw new RuntimeException('down');
            }
        };
        $config = new ConfigAppearanceProvider($registry, ['brand_name' => 'Acme']);
        $twig = new Environment(new ArrayLoader(['t' => '{{ nowo_ui_appearance().brandName }}|{{ nowo_ui_appearance_css()|length > 0 ? "css" }}']));
        $twig->addExtension(new AppearanceExtension($throwing, new AppearanceCssEmitter(), $registry, null, $config));
        self::assertSame('Acme|css', $twig->render('t'));

        $bare = new Environment(new ArrayLoader(['t' => '[{{ nowo_ui_appearance().brandName }}]{{ nowo_ui_appearance_style() starts with "<style" ? "ok" }}']));
        $bare->addExtension(new AppearanceExtension($throwing, new AppearanceCssEmitter(), $registry));
        self::assertSame('[]ok', $bare->render('t'), 'no config provider given: the built-in defaults');
    }

    public function testWorksWithoutRequestStack(): void
    {
        self::assertStringStartsWith('<style data-nowo-ui-appearance>', $this->twig(['s' => '{{ nowo_ui_appearance_style() }}'])->render('s'));
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates, ?RequestStack $requestStack = null): Environment
    {
        $registry = new AppearancePresetRegistry();
        $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html']);
        $twig->addExtension(new AppearanceExtension(
            new ConfigAppearanceProvider($registry, ['brand_name' => 'Acme', 'preset' => 'ocean']),
            new AppearanceCssEmitter(),
            $registry,
            $requestStack,
        ));

        return $twig;
    }
}
