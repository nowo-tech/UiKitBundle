<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

final class UiMacrosTwigTest extends TestCase
{
    public function testBundleRegistersTwigPathsPass(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitBundle())->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        $found = false;
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
        $base = \dirname(__DIR__, 2).'/src/Resources/public';
        self::assertFileExists($base.'/css/nowo-ui.css');
        foreach (['nowo-ui-modal.js', 'nowo-ui-shell.js', 'nowo-ui-toast.js', 'nowo-ui-confirm.js', 'nowo-ui-page-loader.js', 'nowo-ui-theme.js', 'nowo-ui-orb.js'] as $js) {
            self::assertFileExists($base.'/js/'.$js);
        }
        self::assertStringContainsString('--nowo-ui-primary', (string) file_get_contents($base.'/css/nowo-ui.css'));
        self::assertStringContainsString('nowo-ui-toast', (string) file_get_contents($base.'/css/nowo-ui.css'));
        self::assertStringContainsString('nowo-ui-confirm', (string) file_get_contents($base.'/css/nowo-ui.css'));
        self::assertStringContainsString('nowoOpenModal', (string) file_get_contents($base.'/js/nowo-ui-modal.js'));
        self::assertStringContainsString('nowoUiToggleAside', (string) file_get_contents($base.'/js/nowo-ui-shell.js'));
        self::assertStringContainsString('nowoUiDismissToast', (string) file_get_contents($base.'/js/nowo-ui-toast.js'));
        self::assertStringContainsString('nowoUiOpenConfirm', (string) file_get_contents($base.'/js/nowo-ui-confirm.js'));
    }

    public function testShellChromePartialsExist(): void
    {
        $views = \dirname(__DIR__, 2).'/src/Resources/views/partials';
        foreach ([
            '_aside.html.twig',
            '_burger.html.twig',
            '_avatar.html.twig',
            '_user_menu.html.twig',
            '_footer.html.twig',
            '_shell.html.twig',
            '_toasts.html.twig',
            '_confirm.html.twig',
            '_page_loader.html.twig',
            '_card.html.twig',
            '_filters.html.twig',
            '_brand.html.twig',
            '_theme_toggle.html.twig',
            '_width_toggle.html.twig',
            '_thinking_orb.html.twig',
            '_locale_switcher.html.twig',
            '_kebab.html.twig',
        ] as $file) {
            self::assertFileExists($views.'/'.$file);
        }
    }

    public function testBadgeVariantAndCardMacros(): void
    {
        $badge = $this->renderMacro("{{ ui.badge('success') }}", 'custom');
        self::assertStringContainsString('nowo-ui-badge--success', $badge);

        $badgeFw = $this->renderMacro("{{ ui.badge('tailwind') }}", 'bootstrap5');
        self::assertStringContainsString('rounded-full', $badgeFw);

        $card = $this->renderMacro('{{ ui.card() }}', 'custom');
        self::assertStringContainsString('nowo-ui-card', $card);
        self::assertStringNotContainsString('card-body', $card);
    }

    public function testIconPartialCompilesWithoutUxIconsPackage(): void
    {
        $html = $this->renderIcon('edit', 'bootstrap-icons');
        self::assertStringContainsString('bi bi-pencil', $html);

        $html = $this->renderIcon('delete', 'svg_inline');
        self::assertStringContainsString('<svg', $html);

        $html = $this->renderIcon('view', 'none');
        self::assertStringContainsString('nowo-ui-icon--text', $html);
    }

    private function renderMacro(string $expression, string $framework): string
    {
        $views = \dirname(__DIR__, 2).'/src/Resources/views';
        $fs = new FilesystemLoader();
        $fs->addPath($views, 'NowoUiKitBundle');

        $loader = new ArrayLoader([
            't.twig' => "{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}{$expression}",
        ]);

        $chain = new \Twig\Loader\ChainLoader([$loader, $fs]);
        $twig = new Environment($chain);
        $twig->addGlobal('nowo_ui_kit_css_framework', $framework);
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');

        return trim($twig->render('t.twig'));
    }

    private function renderIcon(string $name, string $iconSet): string
    {
        $views = \dirname(__DIR__, 2).'/src/Resources/views';
        $fs = new FilesystemLoader();
        $fs->addPath($views, 'NowoUiKitBundle');

        $loader = new ArrayLoader([
            't.twig' => "{% include '@NowoUiKitBundle/components/_icon.html.twig' with { name: name } only %}",
        ]);

        $chain = new \Twig\Loader\ChainLoader([$loader, $fs]);
        $twig = new Environment($chain);
        $twig->addGlobal('nowo_ui_kit_icon_set', $iconSet);

        return trim($twig->render('t.twig', ['name' => $name]));
    }
}
