<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Dialog;

use InvalidArgumentException;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Action;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.28 markup: the `remote_form_dialog` partial (both drivers), the dialog `root_class`, the side panel
 * `backdrop_close`, the `menu` partial's `menu_semantics`, the fragment layout and the Twig helpers.
 */
final class RemoteFormDialogPartialTest extends TestCase
{
    public function testIifeDriverCarriesTheSettingsOnTheDialog(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', [
            'id' => 'monitor-dialog', 'title' => 'Edit monitor', 'invalid_selector' => '.err input', 'follow_on_load_error' => false,
            'attr' => ['data-testid' => 'monitor-dialog'],
        ]);
        self::assertStringContainsString('<dialog id="monitor-dialog" class="nowo-ui-dialog nowo-ui-dialog--lg nowo-ui-dialog--remote-form" data-nowo-ui-dialog aria-labelledby="monitor-dialog-title"', $html);
        self::assertStringNotContainsString('data-nowo-ui-dialog-backdrop-close', $html);
        foreach ([
            'data-nowo-ui-remote-form',
            'data-nowo-ui-remote-form-loading="Loading…"',
            'data-nowo-ui-remote-form-error="Could not save. Check your connection and try again."',
            'data-nowo-ui-remote-form-load-error="The form could not be loaded."',
            'data-nowo-ui-remote-form-invalid=".err input"',
            'data-nowo-ui-remote-form-follow-on-load-error="false"',
            'data-testid="monitor-dialog"',
            'tabindex="-1">Edit monitor</h2>',
            'data-nowo-ui-dialog-body',
        ] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertStringNotContainsString('remote-form-dialog-target', $html);
    }

    public function testStimulusDriverRendersTheTargetsAndKeepsTheDefaults(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', [
            'id' => 'dest', 'stimulus' => true, 'size' => 'xl', 'backdrop_close' => true, 'labels' => ['loading' => 'Wait', 'error' => 'Oops', 'load_error' => 'Nope'],
        ]);
        self::assertStringContainsString('nowo-ui-dialog--xl nowo-ui-dialog--remote-form', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-backdrop-close', $html);
        self::assertStringContainsString('data-remote-form-dialog-target="dialog"', $html);
        self::assertStringContainsString('data-remote-form-dialog-target="title"', $html);
        self::assertStringContainsString('data-remote-form-dialog-target="body" data-action="submit-&gt;remote-form-dialog#submit"', $html);
        self::assertStringNotContainsString('data-nowo-ui-remote-form-loading', $html);
        self::assertSame('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ThemeSlots::PARTIALS['remote_form_dialog']);
    }

    public function testSidePanelBackdropCloseCanBeTurnedOff(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_side_panel.html.twig', ['id' => 'p', 'title' => 'P', 'layout' => 'overlay', 'backdrop_close' => false]);
        self::assertStringContainsString(' hidden data-nowo-ui-side-panel-backdrop-close="false"', $html);
    }

    public function testMenuSemanticsMenuAndDisclosure(): void
    {
        $items = [['label' => 'Profile', 'href' => '/profile'], ['label' => 'Refresh', 'attr' => ['data-action' => 'x#y']]];
        $twig = PageContractTwig::create();
        $menu = $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => $items, 'id' => 'acc', 'popover' => true, 'menu_semantics' => 'menu']);
        self::assertStringContainsString('data-nowo-ui-menu-semantics="menu"', $menu);
        self::assertStringContainsString('popovertarget="acc-panel" id="acc-panel-toggle" aria-haspopup="menu" aria-expanded="false" aria-controls="acc-panel"', $menu);
        self::assertStringContainsString('popover data-nowo-ui-popover-panel role="menu" aria-labelledby="acc-panel-toggle"', $menu);
        self::assertStringContainsString('<li role="none"><a class="nowo-ui-menu__item" href="/profile" role="menuitem" tabindex="-1"', $menu);
        self::assertStringContainsString('<button type="button" class="nowo-ui-menu__item" role="menuitem" tabindex="-1"', $menu);

        $actions = $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => [Action::create('/new', 'New'), new Action('copy', 'Copy', '/copy', method: 'POST', csrfToken: 't')], 'id' => 'r', 'popover' => true, 'menu_semantics' => 'menu']);
        self::assertSame(2, substr_count($actions, 'role="none"'));

        $disclosure = $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => $items, 'id' => 'acc', 'popover' => true, 'menu_semantics' => 'disclosure']);
        self::assertStringNotContainsString('aria-haspopup', $disclosure);
        self::assertStringContainsString('aria-controls="acc-panel"', $disclosure);
        self::assertStringNotContainsString('role="menu"', $disclosure);

        // Ignored for <details> menus, free content and unknown values.
        foreach ([
            ['items' => $items, 'id' => 'd', 'menu_semantics' => 'menu'],
            ['content' => '<p>x</p>', 'id' => 'c', 'popover' => true, 'menu_semantics' => 'menu'],
            ['items' => $items, 'id' => 'u', 'popover' => true, 'menu_semantics' => 'bogus'],
        ] as $options) {
            self::assertStringNotContainsString('data-nowo-ui-menu-semantics', $twig->render('@NowoUiKitBundle/partials/_menu.html.twig', $options));
        }
    }

    public function testMenuSemanticsFromTheTheme(): void
    {
        $html = PageContractTwig::create(theme: ['menu_semantics' => 'disclosure'])->render('@NowoUiKitBundle/partials/_menu.html.twig', ['items' => [['label' => 'A', 'href' => '/a']], 'id' => 't', 'popover' => true]);
        self::assertStringContainsString('data-nowo-ui-menu-semantics="disclosure"', $html);
        self::assertSame('menu', Theme::fromConfig('bootstrap5', ['menu_semantics' => 'menu'])->menuSemantics());
        self::assertNull(Theme::fromConfig('bootstrap5', [])->menuSemantics());
    }

    public function testInvalidMenuSemanticsAreRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Theme::fromConfig('bootstrap5', ['menu_semantics' => 'list']);
    }

    public function testInvalidMenuSemanticsInTheConstructorAreRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Theme(menuSemantics: 'list');
    }

    public function testTwigHelpers(): void
    {
        $request = Request::create('https://app.example/form');
        $request->headers->set('X-Nowo-Dialog', '1');
        $twig = PageContractTwig::create([
            'probe' => "{{ nowo_ui_dialog_request() ? 'dialog' : 'page' }}",
            'opener' => "<a href=\"/x\"{{ nowo_ui_html_attrs(nowo_ui_remote_form_opener('d', 'Edit', false, '/x')) }}>E</a><a{{ nowo_ui_html_attrs(nowo_ui_remote_form_opener('d', null, true)) }}>S</a>",
            'fragment' => "{% extends '@NowoUiKit/layout/fragment.html.twig' %}{% block body %}<form></form>{% endblock %}",
        ], request: $request);
        self::assertSame('dialog', $twig->render('probe'));
        self::assertSame('page', PageContractTwig::create(['probe' => "{{ nowo_ui_dialog_request() ? 'dialog' : 'page' }}"])->render('probe'));
        self::assertSame('<a href="/x" data-nowo-ui-dialog-open="d" data-nowo-ui-dialog-src="/x" data-nowo-ui-dialog-form data-nowo-ui-dialog-title="Edit">E</a><a data-action="remote-form-dialog#open">S</a>', $twig->render('opener'));
        self::assertSame('<form></form>', $twig->render('fragment'));
        self::assertSame(['data-action' => 'remote-form-dialog#open', 'data-remote-form-dialog-title' => 'T'], ThemeExtension::remoteFormOpener('d', 'T', true));
    }
}
