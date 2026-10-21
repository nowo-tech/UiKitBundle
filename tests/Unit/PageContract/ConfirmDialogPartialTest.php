<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Test\FrameworkClasses;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;

/**
 * `confirm_dialog` partial (2.6): the apps' shared/_confirm_dialog.html.twig (Stimulus confirm-dialog wrapper) with
 * theme slots, the same options and the trigger / form blocks.
 */
final class ConfirmDialogPartialTest extends TestCase
{
    private const EMBED = <<<'TWIG'
{% embed nowo_ui_partial('confirm_dialog') with opts %}
    {% block form %}<form method="post" action="/revoke"><button type="button" data-confirm-dialog-close>Cancel</button><button type="submit">Revoke</button></form>{% endblock %}
{% endembed %}
TWIG;

    public function testFullModeWithTriggerAndFormBlock(): void
    {
        self::assertSame('@NowoUiKitBundle/partials/_confirm_dialog.html.twig', ThemeSlots::PARTIALS['confirm_dialog']);
        $html = PageContractTwig::create(['t' => self::EMBED])->render('t', ['opts' => [
            'trigger_label' => 'Revoke',
            'trigger_title' => 'Revoke the key',
            'trigger_aria_label' => 'Revoke key 42',
            'trigger_testid' => 'revoke-42',
            'expected_value' => 'key "42"',
            'open_on_connect' => 'true',
            'dialog_modifier' => 'confirm-dialog--xl',
            'aria_labelledby' => 'revoke-title',
            'dialog_testid' => 'revoke-dialog',
        ]]);

        self::assertStringContainsString('<div class="nowo-ui-confirm-dialog-root" data-controller="confirm-dialog" data-confirm-dialog-expected-value="key &quot;42&quot;" data-confirm-dialog-open-on-connect-value="true">', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" data-action="confirm-dialog#open" title="Revoke the key" aria-label="Revoke key 42" data-testid="revoke-42">Revoke</button>', $html);
        self::assertStringContainsString('<dialog class="nowo-ui-confirm-dialog confirm-dialog--xl" data-confirm-dialog-target="dialog" data-testid="revoke-dialog" aria-labelledby="revoke-title">', $html);
        self::assertStringContainsString('<form method="post" action="/revoke"><button type="button" data-confirm-dialog-close>Cancel</button>', $html);
        self::assertMatchesRegularExpression('#</dialog>\s*</div>#', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/i', $html));
    }

    public function testOnlyDialogIdForTheKitRuntimeAndTheme(): void
    {
        $twig = PageContractTwig::create(['t' => self::EMBED], [
            'classes' => ['confirm_dialog' => 'confirm-dialog', 'confirm_dialog_trigger' => ['value' => 'btn-ghost', 'framework_defaults' => false], 'confirm_dialog_root' => 'confirm-root'],
            'attributes' => ['confirm_dialog' => ['data-host' => 'dlg']],
        ], 'none');

        $only = $twig->render('t', ['opts' => ['only_dialog' => true, 'dialog_id' => 'new-key', 'trigger_label' => 'ignored', 'open_on_connect' => 'yes']]);
        self::assertStringNotContainsString('data-controller', $only);
        self::assertStringNotContainsString('ignored', $only);
        self::assertStringContainsString('<dialog class="nowo-ui-confirm-dialog confirm-dialog" data-confirm-dialog-target="dialog" id="new-key" data-nowo-ui-confirm data-host="dlg">', $only);

        $legacy = $twig->render('t', ['opts' => ['wrapper' => false]]);
        self::assertStringNotContainsString('data-controller', $legacy);

        $full = $twig->render('t', ['opts' => ['dialog_id' => 'd1', 'trigger_label' => 'Open', 'trigger_disabled' => true, 'attr' => ['data-turbo' => 'false'], 'dialog_attr' => ['data-size' => 'lg'], 'open_on_connect' => false]]);
        self::assertStringContainsString('<div class="nowo-ui-confirm-dialog-root confirm-root" data-controller="confirm-dialog" data-turbo="false">', $full);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary btn-ghost" data-action="confirm-dialog#open" data-nowo-confirm-open data-nowo-confirm-target="d1" aria-haspopup="dialog" aria-controls="d1" disabled>Open</button>', $full);
        self::assertStringContainsString('data-nowo-ui-confirm data-host="dlg" data-size="lg">', $full);

        $own = PageContractTwig::create(['t' => "{% embed nowo_ui_partial('confirm_dialog') with {trigger_class: 'x-btn'} %}{% block trigger %}<a href=\"#\" data-action=\"confirm-dialog#open\">Own</a>{% endblock %}{% endembed %}"])->render('t');
        self::assertStringContainsString('<a href="#" data-action="confirm-dialog#open">Own</a>', $own);
        self::assertStringContainsString('<dialog class="nowo-ui-confirm-dialog" data-confirm-dialog-target="dialog">', $own);

        $classes = PageContractTwig::create(['t' => self::EMBED], [], 'tailwind')->render('t', ['opts' => ['trigger_label' => 'Go']]);
        preg_match_all('/class="([^"]*)"/', $classes, $m);
        self::assertSame([], FrameworkClasses::violations(preg_split('/\s+/', implode(' ', $m[1])) ?: [], 'tailwind'));
    }
}
