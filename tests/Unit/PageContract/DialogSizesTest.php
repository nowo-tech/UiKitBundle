<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use ArrayObject;
use Nowo\UiKitBundle\Test\FrameworkClasses;
use Nowo\UiKitBundle\View\PageStatus;
use PHPUnit\Framework\TestCase;

/**
 * 2.29 — two dialog sizes (owner rule): the public tokens --nowo-dialog-{width,height}-{md,wide}, the legacy size
 * modifiers mapped onto them, the size's height with only the body scrolling (a plain yes / no keeps its natural
 * height), the header status badge, the confirm_dialog anatomy and the remote form dialog footer.
 */
final class DialogSizesTest extends TestCase
{
    private const ANATOMY = <<<'TWIG'
{% embed nowo_ui_partial('confirm_dialog') with opts %}
    {% block body %}<form id="edit-form" method="post" action="/edit"><input name="name"></form>{% endblock %}
    {% block footer %}<button type="button" data-confirm-dialog-close>Cancel</button><button type="submit" form="edit-form">Save</button>{% endblock %}
{% endembed %}
TWIG;

    public function testTokensAndSizesInBothStylesheets(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            foreach ([
                '--nowo-ui-dialog-w: var(--nowo-dialog-width-md, min(40rem, 100vw - 2rem));',
                '--nowo-ui-dialog-h: var(--nowo-dialog-height-md, min(36rem, 100dvh - 2rem));',
                '--nowo-ui-dialog-w: var(--nowo-dialog-width-wide, min(56rem, 100vw - 2rem));',
                '--nowo-ui-dialog-h: var(--nowo-dialog-height-wide, min(52rem, 100dvh - 2rem));',
            ] as $needle) {
                self::assertStringContainsString($needle, $css, $file.': '.$needle);
            }
            // No :root value: a host sets the tokens once whatever the load order.
            self::assertDoesNotMatchRegularExpression('/^\s*--nowo-dialog-(width|height)-(md|wide)\s*:/m', $css, $file);
            // lg / xl / wide → wide; sm keeps no width of its own (medium).
            self::assertMatchesRegularExpression('/\.nowo-ui-dialog--lg,\s*\.nowo-ui-dialog--xl,\s*\.nowo-ui-dialog--wide,\s*\.nowo-ui-confirm-dialog--lg,\s*\.nowo-ui-confirm-dialog--xl,\s*\.nowo-ui-confirm-dialog--wide \{/', $css, $file);
            self::assertDoesNotMatchRegularExpression('/^\.nowo-ui-(confirm-)?dialog--sm \{/m', $css, $file);
            self::assertDoesNotMatchRegularExpression('/^\.nowo-ui-confirm--(sm|lg) \{/m', $css, $file);
            // The size's height, the body scrolls, the header and the footer stay; a plain yes / no is natural.
            self::assertMatchesRegularExpression('/\.nowo-ui-dialog\[open\]:not\(\.nowo-ui-dialog--end\):not\(\.nowo-ui-dialog--start\),\s*\.nowo-ui-confirm-dialog--anatomy\[open\] \{[^}]*display: flex;[^}]*height: var\(--nowo-ui-dialog-h\);/', $css, $file);
            self::assertMatchesRegularExpression("/:not\\(:has\\(input:not\\(\\[type='hidden'\\]\\), select, textarea, table, ul, ol, details\\)\\) \\{[^}]*height: fit-content;/", $css, $file);
            self::assertMatchesRegularExpression('/> \.nowo-ui-dialog__body \{[^}]*flex: 1 1 auto;[^}]*min-height: 0;[^}]*overflow: auto;/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-dialog__footer\[hidden\] \{\s*display: none !important;/', $css, $file);
            // Phones: the remote form dialog keeps the 16 px gutter by default.
            self::assertStringContainsString('--nowo-ui-dialog-w: calc(100vw - 2 * var(--nowo-ui-remote-dialog-phone-inset, 1rem));', $css, $file);
        }
    }

    public function testDialogPartialWideStatusAndFooterTarget(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_dialog.html.twig', [
            'id' => 'w', 'title' => 'Monitors', 'size' => 'wide', 'status' => new PageStatus('Paused', 'warning'), 'footer_target' => true,
        ]);
        self::assertStringContainsString('<dialog id="w" class="nowo-ui-dialog nowo-ui-dialog--wide" data-nowo-ui-dialog', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-dialog__title-row"><h2 class="nowo-ui-dialog__title" id="w-title">Monitors</h2><span class="nowo-ui-page-status nowo-ui-badge nowo-ui-badge--warning[^"]*" data-nowo-ui-page-status="warning">Paused</span></div>\n#', $html);
        self::assertStringContainsString('<footer class="nowo-ui-dialog__footer" data-nowo-ui-dialog-footer hidden></footer>', $html);

        $hash = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 'h', 'title' => 'T', 'status' => ['label' => 'Up', 'tone' => 'success'], 'footer' => '<b>x</b>', 'footer_target' => true]);
        self::assertStringContainsString('data-nowo-ui-page-status="success">Up</span></div>', $hash);
        self::assertStringContainsString('<footer class="nowo-ui-dialog__footer"><b>x</b></footer>', $hash, 'a given footer wins over the target');
        self::assertStringNotContainsString('data-nowo-ui-dialog-footer', $hash);

        foreach (['inherited string', 42, true, ['tone' => 'success'], ['label' => ''], new ArrayObject(['label' => 'x'])] as $notAStatus) {
            self::assertSame(
                PageContractTwig::create()->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 's', 'title' => 'T']),
                PageContractTwig::create()->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 's', 'title' => 'T', 'status' => $notAStatus]),
                'a status that is not a PageStatus nor a hash with a label is ignored (no 500)',
            );
        }
        $unknown = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 'u', 'title' => 'T', 'status' => ['label' => 'Odd', 'tone' => 'bogus', 'title' => 'Since 2h', 'attributes' => ['data-x' => '1']]]);
        self::assertStringContainsString('data-nowo-ui-page-status="neutral" title="Since 2h"', $unknown);
        self::assertStringContainsString('data-x="1">Odd</span>', $unknown);

        $plain = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 'p', 'title' => 'T', 'size' => 'huge']);
        self::assertStringContainsString('<dialog id="p" class="nowo-ui-dialog" data-nowo-ui-dialog', $plain);
        self::assertStringNotContainsString('title-row', $plain);
        self::assertStringNotContainsString('<footer', $plain);
    }

    public function testRemoteFormDialogHasTheFooterTarget(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ['id' => 'r']);
        self::assertStringContainsString('class="nowo-ui-dialog nowo-ui-dialog--lg nowo-ui-dialog--remote-form"', $html);
        self::assertMatchesRegularExpression('#</div>\s*<footer class="nowo-ui-dialog__footer" data-nowo-ui-dialog-footer hidden></footer>\s*</dialog>#', $html);
    }

    public function testConfirmDialogAnatomy(): void
    {
        $twig = PageContractTwig::create(['t' => self::ANATOMY]);
        $html = $twig->render('t', ['opts' => [
            'anatomy' => true, 'trigger_label' => 'Edit', 'dialog_id' => 'edit', 'title' => 'Edit monitor', 'size' => 'wide', 'status' => ['label' => 'Up', 'tone' => 'success'],
        ]]);
        self::assertStringContainsString('<dialog class="nowo-ui-confirm-dialog nowo-ui-confirm-dialog--wide nowo-ui-confirm-dialog--anatomy" data-confirm-dialog-target="dialog" id="edit" data-nowo-ui-confirm aria-labelledby="edit-title">', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-dialog__title" id="edit-title">Edit monitor</h2>', $html);
        self::assertStringContainsString('data-nowo-ui-page-status="success">Up</span></div>', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-dialog__close" data-confirm-dialog-close data-nowo-confirm-close aria-label="Close" title="Close">', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-dialog__body"><form id="edit-form" method="post" action="/edit"><input name="name"></form></div>\s*<footer class="nowo-ui-dialog__footer"><button type="button" data-confirm-dialog-close>Cancel</button><button type="submit" form="edit-form">Save</button></footer>#', $html);
        self::assertSame(1, substr_count($html, '<header'));

        $generated = $twig->render('t', ['opts' => ['anatomy' => true, 'title' => 'T', 'dismissible' => false]]);
        self::assertMatchesRegularExpression('#aria-labelledby="(nowo-ui-confirm-dialog-\d+-title)">.*id="\1"#s', $generated);
        self::assertStringNotContainsString('nowo-ui-dialog__close', $generated);
        self::assertStringNotContainsString('data-nowo-confirm-close', $generated);

        $own = $twig->render('t', ['opts' => ['anatomy' => true, 'title' => 'T', 'aria_labelledby' => 'mine', 'title_id' => 'tid']]);
        self::assertStringContainsString('aria-labelledby="mine"', $own);
        self::assertStringContainsString('id="tid"', $own);

        $legacy = $twig->render('t', ['opts' => ['size' => 'wide', 'title' => 'T', 'status' => ['label' => 'Up'], 'title_id' => 'x']]);
        self::assertStringContainsString('<dialog class="nowo-ui-confirm-dialog" data-confirm-dialog-target="dialog">', $legacy);
        self::assertStringNotContainsString('<header', $legacy, 'without anatomy: true the form block keeps its free panel, whatever title / size');
        self::assertStringNotContainsString('aria-labelledby', $legacy);
        foreach ([false, 'true', 1, null] as $notTrue) {
            self::assertSame($legacy, $twig->render('t', ['opts' => ['anatomy' => $notTrue, 'size' => 'wide', 'title' => 'T']]), 'only an explicit true opts in');
        }

        $html = $twig->render('t', ['opts' => ['anatomy' => true, 'title_html' => '<code>a&amp;b</code>', 'status' => 'inherited string']]);
        self::assertStringContainsString('<h2 class="nowo-ui-dialog__title" id="', $html);
        self::assertStringContainsString('"><code>a&amp;b</code></h2>', $html, 'title_html is trusted HTML printed raw');
        self::assertStringNotContainsString('title-row', $html, 'a non-hash status is ignored');
        self::assertStringNotContainsString('nowo-ui-confirm-dialog--wide', $html);

        $toned = $twig->render('t', ['opts' => ['anatomy' => true, 'title' => 'T', 'status' => ['label' => 'Odd', 'tone' => 'primary']]]);
        self::assertStringContainsString('data-nowo-ui-page-status="neutral">Odd</span>', $toned, 'a tone outside PageStatus::TONES is neutral');

        preg_match_all('/class="([^"]*)"/', PageContractTwig::create(['t' => self::ANATOMY], [], 'tailwind')->render('t', ['opts' => ['anatomy' => true, 'title' => 'T']]), $m);
        self::assertSame([], FrameworkClasses::violations(preg_split('/\s+/', implode(' ', $m[1])) ?: [], 'tailwind'));
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
    }
}
