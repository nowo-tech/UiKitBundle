<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Translation\IdentityTranslator;
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

    public function testRowActionsDisplayModes(): void
    {
        $icon = $this->renderRowActions('icon', [['kind' => 'edit', 'href' => '#edit']]);
        self::assertStringContainsString('nowo-ui-row-actions--icon', $icon);
        self::assertStringContainsString('bi bi-pencil', $icon);
        self::assertStringContainsString('visually-hidden', $icon);
        self::assertStringNotContainsString('nowo-ui-action__label', $icon);
        self::assertStringContainsString('nowo-ui-action--edit', $icon);

        $text = $this->renderRowActions('text', [['kind' => 'delete', 'href' => '#del']]);
        self::assertStringContainsString('nowo-ui-row-actions--text', $text);
        self::assertStringContainsString('nowo-ui-action__label', $text);
        self::assertStringNotContainsString('bi bi-', $text);
        self::assertStringNotContainsString('visually-hidden', $text);

        $both = $this->renderRowActions('icon_text', [['kind' => 'view', 'href' => '#show']]);
        self::assertStringContainsString('nowo-ui-row-actions--icon-text', $both);
        self::assertStringContainsString('bi bi-eye', $both);
        self::assertStringContainsString('nowo-ui-action__label', $both);
    }

    public function testRowActionsPostFormAndConfirmButton(): void
    {
        $form = $this->renderRowActions('text', [[
            'kind' => 'delete',
            'method' => 'POST',
            'href' => '/delete/1',
            'csrf_token' => 'tok',
            'csrf_field' => '_csrf_token',
            'confirm_message' => 'Sure?',
        ]]);
        self::assertStringContainsString('<form', $form);
        self::assertStringContainsString('method="post"', $form);
        self::assertStringContainsString('action="/delete/1"', $form);
        self::assertStringContainsString('name="_csrf_token"', $form);
        self::assertStringContainsString('value="tok"', $form);
        self::assertStringContainsString('nowo-ui-action--delete', $form);

        $btn = $this->renderRowActions('icon', [[
            'kind' => 'delete',
            'tag' => 'button',
            'confirm_id' => 'del-1',
        ]]);
        self::assertStringContainsString('data-nowo-confirm-open', $btn);
        self::assertStringContainsString('data-nowo-confirm-target="del-1"', $btn);
        self::assertStringContainsString('<button', $btn);
    }

    public function testActionMacroDefaultsAreSecondaryExceptDeleteAndCreate(): void
    {
        $edit = $this->renderMacro("{{ ui.action('edit') }}", 'bootstrap5');
        self::assertStringContainsString('nowo-ui-action--edit', $edit);
        self::assertStringContainsString('btn-outline-secondary', $edit);

        $delete = $this->renderMacro("{{ ui.action('delete') }}", 'bootstrap5');
        self::assertStringContainsString('btn-outline-danger', $delete);

        $create = $this->renderMacro("{{ ui.action('create') }}", 'bootstrap5');
        self::assertStringContainsString('btn-primary', $create);
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
        $twig->addGlobal('nowo_ui_kit_row_actions_display', 'icon');

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

    /**
     * @param list<array{kind: string, href?: string}> $actions
     */
    private function renderRowActions(string $display, array $actions): string
    {
        $views = \dirname(__DIR__, 2).'/src/Resources/views';
        $fs = new FilesystemLoader();
        $fs->addPath($views, 'NowoUiKitBundle');

        $loader = new ArrayLoader([
            't.twig' => "{% include '@NowoUiKitBundle/partials/_row_actions.html.twig' with { display: display, actions: actions } only %}",
        ]);

        $chain = new \Twig\Loader\ChainLoader([$loader, $fs]);
        $twig = new Environment($chain);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addGlobal('nowo_ui_kit_css_framework', 'bootstrap5');
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');
        $twig->addGlobal('nowo_ui_kit_row_actions_display', 'icon');

        return trim($twig->render('t.twig', [
            'display' => $display,
            'actions' => $actions,
        ]));
    }
}
