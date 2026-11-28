<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use PHPUnit\Framework\TestCase;

/**
 * 2.78: APG roles on the Action entries of `menu_semantics: 'menu'` menus (POST form role="none", control
 * role="menuitem" tabindex="-1"), the `confirm` runtime added to a `scripts` list with `dialog`, and the logout button
 * of the user menu without an inline style. Defaults (menu_semantics null / disclosure, the default script list) keep
 * their output.
 */
final class MenuRolesScriptsLogout278Test extends TestCase
{
    public function testPostActionInAnApgMenuHasMenuRoles(): void
    {
        foreach ([false, true] as $menuItems) {
            $html = $this->menu('menu', $menuItems);
            $button = $menuItems ? 'nowo-ui-menu__item nowo-ui-action--copy' : 'nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--copy';
            self::assertStringContainsString('<form method="post" action="/copy" class="nowo-ui-action__form" role="none">', $html);
            self::assertStringContainsString('<button type="submit" class="'.$button.'" role="menuitem" tabindex="-1"><span class="nowo-ui-action__label">Copy</span></button>', $html);
            // dialog opener, confirm toggle and a disabled link: the control carries the roles once
            self::assertStringContainsString('href="/edit" role="menuitem" tabindex="-1" data-nowo-ui-dialog-open="dlg"', $html);
            self::assertMatchesRegularExpression('#<button type="button" class="[^"]*nowo-ui-action--delete" role="menuitem" tabindex="-1" data-nowo-confirm-open#', $html);
            self::assertStringContainsString('href="/off" role="menuitem" tabindex="-1" aria-disabled="true">', $html);
            self::assertSame(0, substr_count($html, 'tabindex="-1" aria-disabled="true" tabindex="-1"'));
            // the confirm dialog's own POST form is not a menu entry
            self::assertSame(1, substr_count($html, 'role="none">'.\PHP_EOL.'    <button type="submit"'));
        }
    }

    public function testDefaultAndDisclosureMenusAreUnchanged(): void
    {
        foreach ([null, 'disclosure'] as $semantics) {
            $html = $this->menu($semantics, false);
            self::assertStringContainsString('<form method="post" action="/copy" class="nowo-ui-action__form">', $html);
            self::assertStringNotContainsString('menuitem', $html);
            self::assertStringNotContainsString('role="none"', $html);
            self::assertStringContainsString('href="/off" aria-disabled="true" tabindex="-1">', $html);
        }
    }

    public function testActionPartialOptionsOutsideMenus(): void
    {
        $twig = PageContractTwig::create();
        $action = new Action('copy', 'Copy', '/copy', method: 'POST', csrfToken: 't');
        $plain = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => $action]);
        self::assertStringContainsString('<form method="post" action="/copy" class="nowo-ui-action__form">', $plain);
        self::assertStringNotContainsString('menuitem', $plain);
        // only `true` turns the roles on
        self::assertSame($plain, $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => $action, 'menu_role' => 'menu']));
    }

    public function testLegacyPostFormTakesTheMenuRole(): void
    {
        $twig = PageContractTwig::create();
        $vars = ['action' => new Action('copy', 'Copy', '/copy', method: 'POST', csrfToken: 't'), 'action_href' => '/copy', 'button_class' => 'b', 'button_attrs' => [], 'button_html' => 'Copy'];
        self::assertStringContainsString('<form method="post" action="/copy" class="nowo-ui-action__form" role="none">', $twig->render('@NowoUiKitBundle/partials/_action_post_legacy.html.twig', $vars + ['form_attr' => ['role' => 'none']]));
        self::assertStringContainsString('<form method="post" action="/copy" class="nowo-ui-action__form">', $twig->render('@NowoUiKitBundle/partials/_action_post_legacy.html.twig', $vars));
    }

    public function testScriptsAddConfirmWithTheDialogRuntime(): void
    {
        $tpl = "{{ include(nowo_ui_partial('scripts'), names is null ? {} : {script_names: names}) }}";
        $twig = PageContractTwig::create(['t' => $tpl]);
        $render = static fn (?array $names): string => $twig->render('t', ['names' => $names]);
        $confirm = '<script src="/bundles/nowouikit/js/nowo-ui-confirm.js" defer></script>';

        $default = $render(null);
        self::assertContains('dialog', ThemeSlots::SCRIPTS);
        self::assertContains('confirm', ThemeSlots::SCRIPTS);
        self::assertSame(\count(ThemeSlots::SCRIPTS), substr_count($default, '<script '));
        self::assertSame(1, substr_count($default, $confirm));

        $dialog = $render(['dialog', 'toast']);
        self::assertSame(1, substr_count($dialog, $confirm));
        self::assertSame(3, substr_count($dialog, '<script '));
        self::assertLessThan(strpos($dialog, $confirm), strpos($dialog, 'nowo-ui-toast.js'), 'appended after the host list');

        self::assertSame(1, substr_count($render(['confirm', 'dialog']), $confirm));
        self::assertStringNotContainsString('nowo-ui-confirm.js', $render(['toast', 'modal']));
        self::assertSame('', trim($render([])));
    }

    public function testLogoutButtonHasNoInlineStyle(): void
    {
        $twig = PageContractTwig::create();
        $button = '<button type="submit" class="nowo-ui-user-menu__link nowo-ui-user-menu__link--danger nowo-ui-user-menu__logout-button" role="menuitem">';
        $menu = $twig->render('@NowoUiKitBundle/partials/_user_menu.html.twig', ['name' => 'Ann', 'logout_path' => '/logout', 'logout_csrf' => 'lc']);
        self::assertStringContainsString($button, $menu);
        self::assertStringNotContainsString('style=', $menu);

        $root = PageContractTwig::root().'/src/Resources/views/partials/';
        foreach (['_user_menu_logout_form.html.twig', '_user_menu_logout_legacy.html.twig'] as $file) {
            $source = (string) file_get_contents($root.$file);
            self::assertStringContainsString($button, $source, $file);
            self::assertStringNotContainsString('style=', $source, $file);
        }
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/'.$file);
            self::assertMatchesRegularExpression('/\.nowo-ui-user-menu__logout-button \{\s*inline-size: 100%;\s*text-align: start;\s*border: 0;\s*background: transparent;\s*cursor: pointer;\s*font: inherit;\s*\}/', $css, $file);
        }
    }

    private function menu(?string $semantics, bool $menuItems): string
    {
        $items = [
            new Action('copy', 'Copy', '/copy', method: 'POST', csrfToken: 't'),
            new Action('edit', 'Edit', '/edit', dialogId: 'dlg'),
            new Action('delete', 'Delete', '/delete', method: 'POST', csrfToken: 'd', confirm: new Confirm('Delete?')),
            new Action('custom', 'Off', '/off', disabled: true),
        ];

        return PageContractTwig::create()->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => $items, 'id' => 'r', 'popover' => true, 'menu_semantics' => $semantics, 'menu_items' => $menuItems]);
    }
}
