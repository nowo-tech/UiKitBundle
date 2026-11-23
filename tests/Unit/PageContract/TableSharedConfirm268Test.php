<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * 2.68 — one shared confirm dialog per table (`confirm_shared`, or nowo_ui_kit.table_confirm: shared). The default
 * (per_row) prints exactly what UiKit 2.67.0 printed: tests/Fixtures/bc-2.67/table-confirm-per-row.html was rendered by
 * the 2.67.0 `table` / `row_actions` / `action` / `menu` partials from the same page.
 */
final class TableSharedConfirm268Test extends TestCase
{
    public static function page(int $rows = 50): ListPage
    {
        $data = [];
        for ($i = 1; $i <= $rows; ++$i) {
            $data[$i] = ['id' => $i, 'name' => 'Item '.$i];
        }

        return new ListPage(
            new PageHeader('Items'),
            [new Column('name')],
            $data,
            rowActions: static fn (array $row): array => [
                Action::edit('/items/'.$row['id'].'/edit'),
                Action::delete('/items/'.$row['id'].'/delete', 'tok-'.$row['id'], new Confirm('Delete '.$row['name'].'?', 'This cannot be undone.', 'Delete')),
            ],
        );
    }

    public static function render(ListPage $page, ?string $mode = null, array $vars = []): string
    {
        $twig = PageContractTwig::create();
        if (null !== $mode) {
            $twig->addGlobal('nowo_ui_kit_table_confirm', $mode);
        }

        return $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $page] + $vars);
    }

    public function testPerRowDefaultIsByteIdenticalTo267(): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.67/table-confirm-per-row.html';
        self::assertFileExists($file);
        $expected = (string) file_get_contents($file);
        self::assertSame($expected, self::render(self::page(3)));
        self::assertSame($expected, self::render(self::page(3), 'per_row'));
    }

    public function testFiftyRowsPrintOneDialogInsteadOfFifty(): void
    {
        $perRow = self::render(self::page());
        $shared = self::render(self::page(), 'shared');

        self::assertSame(50, substr_count($perRow, '<dialog'));
        self::assertSame(1, substr_count($shared, '<dialog'), '50 rows, one shared dialog');
        self::assertSame(1, substr_count($shared, 'data-nowo-confirm-shared'));
        // Each row drops its dialog (~1.4 kB) and gains a hidden form + data attributes (~0.5 kB): > 700 bytes per row.
        self::assertLessThan(\strlen($perRow) - 50 * 700, \strlen($shared), \sprintf('per row %d bytes, shared %d bytes', \strlen($perRow), \strlen($shared)));

        // Every row keeps its own POST target and CSRF token in a hidden form the toggle names.
        preg_match_all('~data-nowo-confirm-form="([^"]+)"~', $shared, $forms);
        self::assertCount(50, array_unique($forms[1]));
        foreach ($forms[1] as $i => $id) {
            self::assertMatchesRegularExpression('~<form[^>]* id="'.preg_quote($id, '~').'"[^>]*action="/items/'.($i + 1).'/delete"|<form[^>]*action="/items/'.($i + 1).'/delete"[^>]* id="'.preg_quote($id, '~').'"~', $shared);
        }
        self::assertSame(50, substr_count($shared, 'data-nowo-confirm-target="nowo-ui-table-confirm"'));
        for ($i = 1; $i <= 50; ++$i) {
            self::assertStringContainsString('value="tok-'.$i.'"', $shared);
            self::assertStringContainsString('data-nowo-confirm-title="Delete Item '.$i.'?"', $shared);
        }
        self::assertStringContainsString('data-nowo-confirm-message="This cannot be undone."', $shared);
        self::assertStringContainsString('data-nowo-confirm-confirm-label="Delete"', $shared);
        self::assertStringContainsString('data-nowo-confirm-variant="danger"', $shared);
    }

    public function testTheSharedDialogKeepsTheConfirmStructure(): void
    {
        $html = self::render(self::page(2), 'shared');
        preg_match('~<dialog id="nowo-ui-table-confirm".*?</dialog>~s', $html, $m);
        self::assertNotEmpty($m);
        $dialog = $m[0];
        self::assertStringContainsString('data-nowo-ui-confirm', $dialog);
        self::assertStringContainsString('aria-labelledby="nowo-ui-table-confirm-title"', $dialog);
        self::assertStringContainsString('<h2 class="nowo-ui-confirm__title" id="nowo-ui-table-confirm-title" data-nowo-confirm-slot="title"></h2>', $dialog);
        self::assertStringContainsString('class="nowo-ui-confirm__close" data-nowo-confirm-close aria-label="Close"', $dialog);
        self::assertMatchesRegularExpression('~<button type="button" class="[^"]*" data-nowo-confirm-close data-nowo-confirm-slot="cancel">Cancel</button>~', $dialog);
        self::assertSame(3, substr_count($dialog, 'data-nowo-confirm-variant='));
        self::assertStringContainsString('nowo-ui-btn--danger', $dialog);
        self::assertStringNotContainsString('<form', $dialog, 'the dialog holds no form: the row form is submitted');
        self::assertStringNotContainsString(' on', preg_replace('~\s(class|id|aria-\w+|data-[\w-]+|type|for|title|autocomplete|autocapitalize|spellcheck|hidden)(="[^"]*")?~', '', $dialog) ?? '');
    }

    public function testOptionWinsOverTheGlobalAndCustomId(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('table'), {table: page.table, confirm_shared: shared, confirm_shared_id: 'people-confirm'}, false) }}"]);
        $twig->addGlobal('nowo_ui_kit_table_confirm', 'shared');
        $off = $twig->render('t', ['page' => self::page(4), 'shared' => false]);
        self::assertSame(4, substr_count($off, '<dialog'));

        $twig = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('table'), {table: page.table, confirm_shared: shared, confirm_shared_id: 'people-confirm'}, false) }}"]);
        $on = $twig->render('t', ['page' => self::page(4), 'shared' => true]);
        self::assertSame(1, substr_count($on, '<dialog'));
        self::assertStringContainsString('<dialog id="people-confirm"', $on);
        self::assertSame(4, substr_count($on, 'data-nowo-confirm-target="people-confirm"'));
    }

    public function testNoJsBehaviourIsTheSameAsPerRow(): void
    {
        $page = new ListPage(new PageHeader('Items'), [new Column('name')], [1 => ['name' => 'A']], rowActions: [
            new Action('delete', href: '/items/1/delete', method: 'POST', csrfToken: 't', confirm: Confirm::delete(), fallbackHref: '/items/1/confirm-delete'),
        ]);
        $shared = self::render($page, 'shared');
        self::assertMatchesRegularExpression('~<button type="button"[^>]*data-nowo-confirm-open~', $shared, 'the toggle stays a type=button');
        self::assertStringContainsString('<noscript><a class="', $shared);
        self::assertStringContainsString('href="/items/1/confirm-delete"', $shared);
        self::assertMatchesRegularExpression('~<div hidden>\s*<form~', $shared, 'the row form is hidden: never submitted without the dialog');
    }

    public function testTypeToConfirmSizeHeaderMessageAndDisabled(): void
    {
        $page = new ListPage(new PageHeader('Items'), [new Column('name')], [1 => ['name' => 'A']], rowActions: [
            Action::delete('/items/1/delete', 't', new Confirm('Delete A?', 'Gone.', variant: 'primary', expectedValue: 'a.b', messageInHeader: true, size: 'lg')),
            new Action('delete', href: '/items/1/purge', method: 'POST', csrfToken: 't', confirm: Confirm::delete(), disabled: true),
        ]);
        $html = self::render($page, 'shared');
        self::assertStringContainsString('data-nowo-confirm-expected-value="a.b"', $html);
        self::assertStringContainsString('data-nowo-confirm-expected-pattern="a\.b"', $html);
        self::assertStringContainsString('data-nowo-confirm-expected-label="Type a.b to confirm"', $html);
        self::assertStringContainsString('data-nowo-confirm-message-in-header="1"', $html);
        self::assertStringContainsString('data-nowo-confirm-size="lg"', $html);
        self::assertStringContainsString('data-nowo-confirm-variant="primary"', $html);
        self::assertStringNotContainsString('action="/items/1/purge"', $html, 'a disabled action prints no form');
        self::assertSame(1, substr_count($html, 'data-nowo-confirm-form='));
    }

    public function testRowActionsMenuThreadsTheSharedDialog(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('row_actions'), {actions: [action], menu: true, confirm_external: true, confirm_shared: {id: 'shared-x', row: 7}}, false) }}"]);
        $action = Action::delete('/items/1/delete', 't', new Confirm('Delete?'));
        $html = $twig->render('t', ['action' => $action]);
        self::assertStringContainsString('data-nowo-confirm-target="shared-x"', $html);
        $formId = $action->confirmId().'-form-'.substr((string) $action->confirmKey(), 0, 10).'-7';
        self::assertStringContainsString('data-nowo-confirm-form="'.$formId.'"', $html);
        self::assertStringContainsString('id="'.$formId.'"', $html);
        self::assertStringNotContainsString('<dialog', $html);
    }

    /** The markup the TypeScript test (nowo-ui-confirm-shared.test.ts) drives: keep both in step. */
    public function testSharedMarkupIsTheOneTheTypeScriptTestDrives(): void
    {
        $page = new ListPage(new PageHeader('Items'), [new Column('name')], [1 => ['name' => 'A'], 2 => ['name' => 'B']], rowActions: static fn (array $row, int|string $key): array => [
            Action::delete('/items/'.$key.'/delete', 'tok-'.$key, 1 === $key
                ? new Confirm('Delete A?', 'This cannot be undone.', 'Delete')
                : new Confirm('Archive B?', 'Gone.', 'Archive', variant: 'primary', cancelLabel: 'Keep', expectedValue: 'B', messageInHeader: true, size: 'lg')),
        ]);
        $html = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('table'), {table: page.table, confirm_shared: true}, false) }}"])->render('t', ['page' => $page]);
        $file = \dirname(__DIR__, 2).'/Fixtures/table-confirm/shared-two-rows.html';
        if ('1' === getenv('UIKIT_WRITE_SHARED_CONFIRM_FIXTURE')) {
            @mkdir(\dirname($file), 0o777, true);
            file_put_contents($file, $html);
        }
        self::assertFileExists($file);
        self::assertSame((string) file_get_contents($file), $html);
    }

    public function testAnExplicitIdWithDifferentContentStillThrowsInSharedMode(): void
    {
        $page = new ListPage(new PageHeader('Items'), [new Column('name')], [1 => ['name' => 'A'], 2 => ['name' => 'B']], rowActions: static fn (array $row, int|string $key): array => [
            Action::delete('/items/'.$key.'/delete', 't', new Confirm('Delete '.$row['name'].'?', id: 'del')),
        ]);
        try {
            self::render($page, 'shared');
            self::fail('expected the duplicate-id guard');
        } catch (Throwable $e) {
            $message = $e->getMessage().' '.($e->getPrevious()?->getMessage() ?? '');
            self::assertStringContainsString('Confirm id "del" is used by row actions with different content', $message);
        }
    }

    public function testRowFormIdsNeverCollideAcrossPagesOfAnExplicitId(): void
    {
        // Two pages of an infinite-scroll list render `row` 1 again: an explicit Confirm id with another target must
        // not reuse page 1's form id (getElementById would return the wrong row's form).
        $page = static fn (int $id): ListPage => new ListPage(new PageHeader('Items'), [new Column('name')], [$id => ['name' => 'X']], rowActions: [
            Action::delete('/items/'.$id.'/delete', 't', new Confirm('Delete?', id: 'del')),
        ]);
        preg_match('~data-nowo-confirm-form="([^"]+)"~', self::render($page(1), 'shared'), $one);
        preg_match('~data-nowo-confirm-form="([^"]+)"~', self::render($page(2), 'shared'), $two);
        self::assertNotSame($one[1], $two[1]);
        self::assertStringStartsWith('del-form-', $one[1]);
    }

    public function testTheHiddenRowSubmitIsDisabled(): void
    {
        $html = self::render(self::page(1), 'shared');
        self::assertMatchesRegularExpression('~<div hidden>\s*<form[^>]*>\s*<button type="submit" class="nowo-ui-confirm__row-submit" tabindex="-1" disabled>~', $html, 'Enter in the type-to-confirm input never submits past the check');
    }

    /** Sizes for the report (50 rows, kit default theme). */
    public static function sizes(): array
    {
        return ['per_row' => \strlen(self::render(self::page())), 'shared' => \strlen(self::render(self::page(), 'shared'))];
    }
}
