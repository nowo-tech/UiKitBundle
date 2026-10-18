<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\TestCase;

/**
 * Final Beacon validation: the full-page confirm has its own root slot (`confirm_page`; the dialog root slot never
 * reaches the page), `confirm_expect`, `detail_list_meta`, the 1.x body in the `confirm_message` element and the
 * message inside the header (`Confirm::messageInHeader` / `theme.confirm_message_in_header`).
 */
final class ConfirmPageSlotsTest extends TestCase
{
    private const THEME = [
        'classes' => [
            'confirm' => 'confirm-dialog',
            'confirm_page' => 'app-confirm-page',
            'confirm_expect' => 'app-expect',
            'detail_list_meta' => 'app-meta',
        ],
        'attributes' => [
            'confirm' => ['data-dialog' => '1'],
            'confirm_page' => ['data-confirm-page' => '1'],
            'confirm_expect' => ['data-expect' => '1'],
            'confirm_message' => ['data-message' => '1'],
            'detail_list_meta' => ['data-meta' => '1'],
        ],
    ];

    private function page(?bool $inHeader = null): ConfirmPage
    {
        return new ConfirmPage(new PageHeader('Delete'), new Confirm('Delete Ann?', 'Really.', expectedValue: 'ann', messageInHeader: $inHeader), Action::delete('/people/1', 'tok'), '/people');
    }

    public function testConfirmPageNeverPrintsTheDialogRootSlot(): void
    {
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/confirm.html.twig', ['page' => $this->page()]);

        self::assertStringContainsString('<section class="nowo-ui-confirm-page app-confirm-page nowo-ui-card card" data-nowo-ui-page="confirm" data-nowo-ui-region="confirm" data-nowo-confirm-scope data-confirm-page="1">', $html);
        // host dialog CSS (e.g. `.confirm-dialog:not([open]) { display: none }`) and UiKit's own dialog box rules
        // (`.nowo-ui-confirm`) cannot reach the page
        self::assertStringNotContainsString('confirm-dialog', $html);
        self::assertStringNotContainsString('data-dialog', $html);
        self::assertDoesNotMatchRegularExpression('/class="[^"]*\bnowo-ui-confirm(?![\w-])/', $html);
        self::assertStringContainsString('<div class="nowo-ui-confirm__expect app-expect" data-expect="1">', $html);
        self::assertStringContainsString('<p class="nowo-ui-confirm__message" data-message="1">Really.</p>', $html);

        // empty theme
        $html = PageContractTwig::create()->render('@NowoUiKit/page/confirm.html.twig', ['page' => $this->page()]);
        self::assertStringContainsString('<section class="nowo-ui-confirm-page nowo-ui-card card" data-nowo-ui-page="confirm" data-nowo-ui-region="confirm" data-nowo-confirm-scope>', $html);
        self::assertStringContainsString('<div class="nowo-ui-confirm__expect">', $html);
    }

    public function testDialogKeepsTheConfirmSlotAndGetsTheExpectSlot(): void
    {
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => new Confirm('Delete?', 'Gone.', expectedValue: 'acme'), 'id' => 'c1', 'action' => Action::delete('/x', 'tok')]);

        self::assertStringContainsString('class="nowo-ui-confirm confirm-dialog"', $html);
        self::assertStringContainsString('data-dialog="1"', $html);
        self::assertStringContainsString('<div class="nowo-ui-confirm__expect app-expect" data-expect="1">', $html);
        self::assertStringContainsString('<div class="nowo-ui-confirm__body"><p class="nowo-ui-confirm__message" data-message="1">Gone.</p></div>', $html);
        self::assertStringNotContainsString('app-confirm-page', $html);
    }

    public function testLegacyBodyIsWrappedOnlyWhenTheThemeStylesTheMessage(): void
    {
        $vars = ['id' => 'c2', 'title' => 'T', 'body' => '<p>Gone</p>'];
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_confirm.html.twig', $vars);
        self::assertStringContainsString('<div class="nowo-ui-confirm__body"><p>Gone</p></div>', $html);

        $html = PageContractTwig::create([], ['attributes' => ['confirm_message' => ['data-m' => 'x']]])->render('@NowoUiKitBundle/partials/_confirm.html.twig', $vars);
        self::assertStringContainsString('<div class="nowo-ui-confirm__body"><div class="nowo-ui-confirm__message" data-m="x"><p>Gone</p></div></div>', $html);

        $html = PageContractTwig::create([], ['classes' => ['confirm_message' => 'text-sm']], 'tailwind')->render('@NowoUiKitBundle/partials/_confirm.html.twig', $vars + ['bundle' => 'NowoXBundle']);
        self::assertStringContainsString('<div class="nowo-ui-confirm__message text-sm"><p>Gone</p></div>', $html);
    }

    public function testMessageInHeader(): void
    {
        $dialog = static fn (array $theme, ?bool $inHeader): string => PageContractTwig::create([], $theme)->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => new Confirm('Delete?', 'Gone.', messageInHeader: $inHeader), 'id' => 'c3']);
        $inHeader = '#<header class="nowo-ui-confirm__header">\s*<h2 class="nowo-ui-confirm__title" id="c3-title">Delete\?</h2>\s*<p class="nowo-ui-confirm__message">Gone.</p>\s*<button type="button" class="nowo-ui-confirm__close" data-nowo-confirm-close[^>]*>.*?</button>\s*</header>#s';

        self::assertMatchesRegularExpression($inHeader, $dialog([], true));
        self::assertStringNotContainsString('nowo-ui-confirm__body', $dialog([], true));
        self::assertMatchesRegularExpression($inHeader, $dialog(['confirm_message_in_header' => true], null));
        // Confirm::messageInHeader wins over the theme option
        self::assertStringContainsString('<div class="nowo-ui-confirm__body"><p class="nowo-ui-confirm__message">Gone.</p></div>', $dialog(['confirm_message_in_header' => true], false));
        self::assertStringContainsString('<div class="nowo-ui-confirm__body">', $dialog([], null));

        $page = PageContractTwig::create([], ['confirm_message_in_header' => true])->render('@NowoUiKit/page/confirm.html.twig', ['page' => $this->page()]);
        self::assertMatchesRegularExpression('#<header class="nowo-ui-confirm-page__header">\s*<h2 class="nowo-ui-confirm__title">Delete Ann\?</h2>\s*<p class="nowo-ui-confirm__message">Really.</p>\s*</header>#', $page);
        self::assertStringNotContainsString('nowo-ui-confirm-page__body', $page);
        $page = PageContractTwig::create([], ['confirm_message_in_header' => true])->render('@NowoUiKit/page/confirm.html.twig', ['page' => $this->page(false)]);
        self::assertStringContainsString('nowo-ui-confirm-page__body', $page);

        self::assertTrue(Theme::fromConfig('bootstrap5', ['confirm_message_in_header' => true])->confirmMessageInHeader());
        self::assertFalse((new Theme())->confirmMessageInHeader());
    }

    public function testDetailListMetaSlot(): void
    {
        $page = new ShowPage(new PageHeader('Order'), [], [new DetailItem('Created', '2026-10-16')]);
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKit/page/show.html.twig', ['page' => $page]);
        self::assertStringContainsString('<dl class="nowo-ui-detail-list nowo-ui-detail-list--meta app-meta" data-meta="1">', $html);

        $html = PageContractTwig::create()->render('@NowoUiKit/page/show.html.twig', ['page' => $page]);
        self::assertStringContainsString('<dl class="nowo-ui-detail-list nowo-ui-detail-list--meta">', $html);
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => [new DetailItem('A', 'b')], 'modifier' => 'compact']);
        self::assertStringContainsString('<dl class="nowo-ui-detail-list nowo-ui-detail-list--compact">', $html);
    }
}
