<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\TestCase;

/**
 * No-JS fallback of confirm actions (2.1): Action::fallbackHref prints a <noscript> link next to the dialog toggle,
 * in the page-header toolbar and in row actions.
 */
final class ConfirmFallbackTest extends TestCase
{
    public function testToolbarConfirmActionPrintsANoscriptLink(): void
    {
        $action = new Action('delete', 'Delete', '/items/1', method: 'POST', csrfToken: 't', confirm: Confirm::delete(), fallbackHref: '/items/1/delete');
        $html = PageContractTwig::create()->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage(new PageHeader('Item', null, [$action]), [])]);

        self::assertMatchesRegularExpression('#data-nowo-confirm-target="[^"]+">.*?</button>\s*<noscript><a class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger nowo-ui-action--delete nowo-ui-action__fallback" href="/items/1/delete"><span class="nowo-ui-action__label">Delete</span></a></noscript>#s', $html);
        self::assertSame(1, substr_count($html, '<noscript>'));
    }

    public function testRowActionsPrintTheFallbackPerRowWithAnAccessibleName(): void
    {
        $page = new ListPage(
            new PageHeader('Items'),
            [new Column('name', 'Name')],
            [7 => ['name' => 'A'], 8 => ['name' => 'B']],
            rowActions: static fn (array $row, int|string $key): array => [new Action('delete', null, '/items/'.$key, method: 'POST', csrfToken: 't', confirm: Confirm::delete(), fallbackHref: '/items/'.$key.'/delete')],
        );
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertSame(2, substr_count($html, '<noscript>'));
        self::assertMatchesRegularExpression('#<noscript><a class="[^"]*nowo-ui-action__fallback" href="/items/7/delete" title="Delete" aria-label="Delete">#', $html);
        self::assertStringContainsString('href="/items/8/delete"', $html);
    }

    public function testNoFallbackWithoutConfirmOrWhenDisabled(): void
    {
        $twig = PageContractTwig::create();
        $plain = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('delete', href: '/d', method: 'POST', fallbackHref: '/d/confirm')]);
        self::assertStringNotContainsString('noscript', $plain);
        $disabled = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('delete', href: '/d', method: 'POST', confirm: Confirm::delete(), disabled: true, fallbackHref: '/d/confirm')]);
        self::assertStringNotContainsString('noscript', $disabled);
    }

    public function testModelKeepsTheFallback(): void
    {
        $action = new Action('delete', href: '/d', method: 'POST', confirm: Confirm::delete(), fallbackHref: '/d/confirm');

        self::assertSame('/d/confirm', $action->withConfirmScope(3)->fallbackHref);
        self::assertSame('/d/confirm', $action->toArray()['fallback_href']);
        self::assertSame('/d/confirm', $action['fallback_href']);
        self::assertNull(Action::delete('/x')->fallbackHref);
    }
}
