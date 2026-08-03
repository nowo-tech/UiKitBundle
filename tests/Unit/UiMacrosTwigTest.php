<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

use function dirname;
use function file_get_contents;

final class UiMacrosTwigTest extends TestCase
{
    public function testBundleRegistersTwigPathsPass(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitBundle())->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        $found  = false;
        foreach ($passes as $pass) {
            if ($pass instanceof \Nowo\UiKitBundle\DependencyInjection\Compiler\TwigPathsPass) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found);
    }

    public function testBtnMacroEmitsSemanticAndBootstrapClasses(): void
    {
        $html = $this->renderMacro("{{ ui.btn('primary') }}", 'bootstrap5');
        self::assertStringContainsString('nowo-ui-btn', $html);
        self::assertStringContainsString('nowo-ui-btn--primary', $html);
        self::assertStringContainsString('btn-primary', $html);
    }

    public function testBtnMacroCustomEmitsOnlySemanticClasses(): void
    {
        $html = $this->renderMacro("{{ ui.btn('primary') }}", 'custom');
        self::assertStringContainsString('nowo-ui-btn', $html);
        self::assertStringContainsString('nowo-ui-btn--primary', $html);
        self::assertStringNotContainsString('btn-primary', $html);
    }

    public function testBtnMacroAcceptsFrameworkOverride(): void
    {
        $html = $this->renderMacro("{{ ui.btn('primary', null, 'tailwind') }}", 'bootstrap5');
        self::assertStringContainsString('bg-blue-600', $html);
        self::assertStringNotContainsString('btn-primary', $html);
    }

    public function testPublicCssAndJsExist(): void
    {
        $base = dirname(__DIR__, 2) . '/src/Resources/public';
        self::assertFileExists($base . '/css/nowo-ui.css');
        self::assertFileExists($base . '/js/nowo-ui-modal.js');
        self::assertFileExists($base . '/js/nowo-ui-shell.js');
        self::assertStringContainsString('--nowo-ui-primary', (string) file_get_contents($base . '/css/nowo-ui.css'));
        self::assertStringContainsString('nowo-ui-aside', (string) file_get_contents($base . '/css/nowo-ui.css'));
        self::assertStringContainsString('nowoOpenModal', (string) file_get_contents($base . '/js/nowo-ui-modal.js'));
        self::assertStringContainsString('nowoUiToggleAside', (string) file_get_contents($base . '/js/nowo-ui-shell.js'));
    }

    public function testShellChromePartialsExist(): void
    {
        $views = dirname(__DIR__, 2) . '/src/Resources/views/partials';
        foreach (['_aside.html.twig', '_burger.html.twig', '_avatar.html.twig', '_user_menu.html.twig', '_footer.html.twig', '_shell.html.twig'] as $file) {
            self::assertFileExists($views . '/' . $file);
        }
    }

    private function renderMacro(string $expression, string $framework): string
    {
        $views = dirname(__DIR__, 2) . '/src/Resources/views';
        $fs    = new FilesystemLoader();
        $fs->addPath($views, 'NowoUiKitBundle');

        $loader = new ArrayLoader([
            't.twig' => "{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}{$expression}",
        ]);

        $chain = new \Twig\Loader\ChainLoader([$loader, $fs]);
        $twig  = new Environment($chain);
        $twig->addGlobal('nowo_ui_kit_css_framework', $framework);
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');

        return trim($twig->render('t.twig'));
    }
}
