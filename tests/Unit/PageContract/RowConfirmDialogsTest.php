<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

/**
 * Confirm dialog ids of row actions (REQ-UI-003 round 3): the derived id covers the Confirm content and the row
 * key, so rows that share a target (or a static action list) never print duplicate dialog ids, and the list
 * scaffold renders one dialog per id.
 */
final class RowConfirmDialogsTest extends TestCase
{
    public function testSameHrefDifferentConfirmTextGetsDistinctIdsAndDialogs(): void
    {
        $page = new ListPage(
            new PageHeader('People'),
            [new Column('name')],
            [['name' => 'Ann'], ['name' => 'Bob']],
            rowActions: static fn (array $row): array => [Action::delete('/people/bulk', 'tok', new Confirm('Delete '.$row['name'].'?'))],
        );
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        preg_match_all('#data-nowo-confirm-target="([^"]+)"#', $html, $targets);
        preg_match_all('#<dialog\s+id="([^"]+)"#', $html, $dialogs);
        self::assertCount(2, array_unique($targets[1]));
        self::assertSame($targets[1], $dialogs[1]);
        self::assertStringContainsString('Delete Ann?', $html);
        self::assertStringContainsString('Delete Bob?', $html);
        // each toggle opens the dialog with its own text
        $annId = $targets[1][0];
        self::assertMatchesRegularExpression('#<dialog\s+id="'.$annId.'"[^>]*>\s*<div class="nowo-ui-confirm__panel">\s*<header class="nowo-ui-confirm__header">\s*<h2 class="nowo-ui-confirm__title" id="'.$annId.'-title">Delete Ann\?</h2>#', $html);
    }

    public function testStaticRowActionsAreScopedPerRowAndIdenticalDialogsRenderOnce(): void
    {
        $delete = Action::delete('/people/purge', 'tok');
        $page = new ListPage(new PageHeader('People'), [new Column('name')], [5 => ['name' => 'Ann'], 9 => ['name' => 'Bob']], rowActions: [$delete]);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertSame(1, substr_count($html, 'id="'.$delete->withConfirmScope(5)->confirmId().'"'));
        self::assertSame(1, substr_count($html, 'id="'.$delete->withConfirmScope(9)->confirmId().'"'));
        self::assertSame(0, substr_count($html, 'id="'.$delete->confirmId().'"'));

        // the same action + Confirm twice (explicit id shared by every row): one dialog
        $shared = Action::delete('/people/purge', 'tok', new Confirm('Purge?', id: 'purge-all'));
        $page = new ListPage(new PageHeader('People'), [new Column('name')], [['name' => 'Ann'], ['name' => 'Bob']], rowActions: [$shared, $shared]);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertSame(4, substr_count($html, 'data-nowo-confirm-target="purge-all"'));
        self::assertSame(1, substr_count($html, '<dialog'."\n".'    id="purge-all"'));

        // an explicit scope set by the bundle is kept
        $scoped = $page->rowActions(['name' => 'x'], 1);
        self::assertSame('purge-all', $scoped[0]->confirmId());
        $own = (new ListPage(new PageHeader('P'), rowActions: [$delete->withConfirmScope('mine')]))->rowActions([], 4);
        self::assertSame('mine', $own[0]->confirmScope);
    }

    public function testExplicitIdSharedByDifferentContentIsRejected(): void
    {
        $page = new ListPage(new PageHeader('People'));
        $a = Action::delete('/people/1', 'tok', new Confirm('Delete Ann?', id: 'del'));
        $b = Action::delete('/people/2', 'tok', new Confirm('Delete Bob?', id: 'del'));
        self::assertSame([$a], $page->confirmDialogs([$a, Action::edit('/e'), $a]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Confirm id "del" is used by row actions with different content');
        $page->confirmDialogs([$a, $b]);
    }

    public function testFormTokenIsPartOfTheConfirmKey(): void
    {
        $form = static fn (string $token) => PageContractTwig::forms()->createNamedBuilder('csrf', FormType::class)->add('_token', HiddenType::class, ['data' => $token])->getForm()->createView();
        $a = new Action('delete', href: '/x', method: 'POST', confirm: Confirm::delete(), form: $form('one'));
        $b = new Action('delete', href: '/x', method: 'POST', confirm: Confirm::delete(), form: $form('two'));
        self::assertNotSame($a->confirmKey(), $b->confirmKey());
        self::assertSame($a->confirmKey(), (new Action('delete', href: '/x', method: 'POST', confirm: Confirm::delete(), form: $form('one')))->confirmKey());
    }
}
