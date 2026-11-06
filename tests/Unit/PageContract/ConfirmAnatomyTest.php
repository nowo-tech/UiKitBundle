<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use PHPUnit\Framework\TestCase;

/**
 * 2.31 (dialog rule v2): the View\Confirm dialog (partial `confirm`) has the dialog anatomy with its own classes —
 * header (title, ×), body (message; the type-to-confirm input right under it), footer outside the body (Cancel, then
 * the one primary: solid danger for a destructive confirm) — and the stylesheet gives it the tinted header / footer,
 * the medium width and the content's height capped, only the body scrolling. The markup is the 2.30 one (ids, CSRF
 * form, data-nowo-confirm-* hooks).
 */
final class ConfirmAnatomyTest extends TestCase
{
    public function testDangerConfirmMarkupOrder(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_confirm.html.twig', [
            'confirm' => new Confirm('Delete monitor?', 'It stops checking.', 'Delete', id: 'del-1'),
            'action' => new Action('delete', 'Delete', '/monitors/1', method: 'POST', csrfToken: 'tok'),
        ]);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xp = new DOMXPath($doc);
        $panel = $xp->query('//dialog[@id="del-1"][@data-nowo-ui-confirm][@aria-labelledby="del-1-title"]/div[contains(@class,"nowo-ui-confirm__panel")]')->item(0);
        self::assertNotNull($panel);
        $children = [];
        foreach ($panel->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $children[] = $node->tagName.'.'.$node->getAttribute('class');
            }
        }
        self::assertSame(['header.nowo-ui-confirm__header', 'div.nowo-ui-confirm__body', 'div.nowo-ui-confirm__footer'], $children);
        self::assertSame('Delete monitor?', trim($xp->query('//header/h2[@id="del-1-title"]')->item(0)?->textContent ?? ''));
        self::assertSame(1, $xp->query('//header/button[@data-nowo-confirm-close][@aria-label="Close"]')->length, 'the × in the header');
        self::assertSame('It stops checking.', $xp->query('//div[contains(@class,"nowo-ui-confirm__body")]/p')->item(0)?->textContent);
        $footer = $xp->query('//div[contains(@class,"nowo-ui-confirm__footer")]')->item(0);
        self::assertNotNull($footer);
        $buttons = $xp->query('.//button', $footer);
        self::assertSame(2, $buttons->length, 'Cancel then the one primary');
        self::assertTrue($buttons->item(0)?->hasAttribute('data-nowo-confirm-close'));
        self::assertStringContainsString('nowo-ui-btn--solid', $buttons->item(1)?->getAttribute('class') ?? '');
        self::assertStringContainsString('nowo-ui-confirm__submit', $buttons->item(1)?->getAttribute('class') ?? '');
        self::assertSame(1, $xp->query('.//form[@method="post"][@action="/monitors/1"]', $footer)->length, 'the action form');
        self::assertStringContainsString('value="tok"', $html, 'the CSRF token');
    }

    public function testTypeToConfirmSitsBetweenBodyAndFooter(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_confirm.html.twig', [
            'confirm' => new Confirm('Delete space?', 'Everything goes.', 'Delete', id: 'sp', expectedValue: 'docs'),
            'action' => new Action('delete', 'Delete', '/spaces/1', method: 'POST', csrfToken: 'tok'),
        ]);
        self::assertMatchesRegularExpression('#</header>\s*<div class="nowo-ui-confirm__body">.*?</div>\s*<div class="nowo-ui-confirm__expect">.*?id="sp-expected".*?form="sp-form".*?</div>\s*<div class="nowo-ui-confirm__footer">.*?<form[^>]*id="sp-form"#s', $html);
        self::assertStringContainsString('data-nowo-confirm-submit', $html);
    }

    public function testStylesheetGivesTheAnatomy(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
            self::assertMatchesRegularExpression('/\.nowo-ui-confirm > \.nowo-ui-confirm__panel \{\s*display: flex;\s*flex-direction: column;\s*max-height: var\(--nowo-ui-dialog-max-h\);/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-confirm > \.nowo-ui-confirm__panel > \.nowo-ui-confirm__header \{[^}]*border-bottom: 1px solid var\(--nowo-ui-border\);[^}]*background: var\(--nowo-ui-dialog-chrome-bg,/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-confirm > \.nowo-ui-confirm__panel > \.nowo-ui-confirm__body \{[^}]*min-height: 0;[^}]*overflow: auto;/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-confirm > \.nowo-ui-confirm__panel > \.nowo-ui-confirm__footer \{\s*background: var\(--nowo-ui-dialog-chrome-bg,/', $css, $file);
            self::assertMatchesRegularExpression('/:is\(\.nowo-ui-dialog, \.nowo-ui-confirm-dialog--anatomy\) > \.nowo-ui-dialog__header \{[^}]*background: var\(--nowo-ui-dialog-chrome-bg,/', $css, $file);
            self::assertMatchesRegularExpression('/:is\(\.nowo-ui-dialog, \.nowo-ui-confirm-dialog--anatomy\) > \.nowo-ui-dialog__footer \{\s*background: var\(--nowo-ui-dialog-chrome-bg,/', $css, $file);
            self::assertDoesNotMatchRegularExpression('/^\s*--nowo-ui-dialog-chrome-bg\s*:/m', $css, $file.': no :root value');
            // Medium width for every Confirm size (2.29.1)
            self::assertStringContainsString('width: var(--nowo-ui-confirm-width, var(--nowo-ui-dialog-w));', $css, $file);
        }
    }
}
