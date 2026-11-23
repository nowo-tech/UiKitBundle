<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * 2.69 — the Stimulus runtime of the on-demand dialogs (`nowo_ui_kit.dialogs.runtime`, the partials' `runtime` /
 * `stimulus` option) and the shared confirm dialog of menus and row actions outside the `table` partial
 * (`confirm_shared` / `confirm_group`). The default output is the 2.68 one (the bc-* fixtures of the other tests).
 */
final class StimulusDialogs269Test extends TestCase
{
    private const SHELL = "{{ include(nowo_ui_partial('remote_form_dialog'), opts, false) }}";

    /** The page the TypeScript tests drive (stimulus_dialogs.test.ts): menus of one group + rows sharing one remote shell. */
    private const PAGE = <<<'TWIG'
<div data-testid="rows">
{% for m in members %}
<div class="row" data-row="{{ m.id }}">{{ include(nowo_ui_partial('row_actions'), {actions: m.actions, menu: true, confirm_group: 'members-confirm', confirm_row: loop.index, runtime: runtime}, false) }}
{% embed nowo_ui_partial('confirm_dialog') with {dialog_src: '/members/' ~ m.id ~ '/edit', dialog_id: 'member-edit', dialog_shell: false, trigger_label: 'Edit ' ~ m.name, title: 'Edit member', trigger_testid: 'edit-' ~ m.id} %}{% endembed %}</div>
{% endfor %}
</div>
{% embed nowo_ui_partial('confirm_dialog') with {only_dialog: true, dialog_shell: true, dialog_id: 'member-edit', title: 'Edit member', runtime: runtime} %}{% endembed %}
TWIG;

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates, ?string $runtime = null, ?string $tableConfirm = null, ?Request $request = null): Environment
    {
        $twig = PageContractTwig::create($templates, request: $request);
        if (null !== $runtime) {
            $twig->addGlobal('nowo_ui_kit_dialogs_runtime', $runtime);
        }
        if (null !== $tableConfirm) {
            $twig->addGlobal('nowo_ui_kit_table_confirm', $tableConfirm);
        }

        return $twig;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function members(): array
    {
        return [
            ['id' => 1, 'name' => 'Ann', 'actions' => [Action::edit('/members/1/edit'), Action::delete('/members/1/delete', 'tok-1', new Confirm('Remove Ann?', 'She loses access.', 'Remove'))]],
            ['id' => 2, 'name' => 'Bob', 'actions' => [Action::edit('/members/2/edit'), Action::delete('/members/2/delete', 'tok-2', new Confirm('Remove Bob?', 'He loses access.', 'Remove'))]],
        ];
    }

    /** The markup without the peer attributes and with collapsed whitespace. */
    private function withoutPeers(string $html): string
    {
        return (string) preg_replace('~\s+~', ' ', (string) preg_replace('~ data-(controller|remote-form-dialog-[\w-]+|action)="[^"]*"~', '', $html));
    }

    private function page(string $runtime): string
    {
        return $this->twig(['t' => self::PAGE])->render('t', ['members' => $this->members(), 'runtime' => $runtime]);
    }

    // ---- remote_form_dialog / confirm_dialog with dialog_src -------------------------------------------------------

    public function testRuntimeStimulusWiresTheShellInDelegatedMode(): void
    {
        $iife = $this->twig(['t' => self::SHELL])->render('t', ['opts' => ['id' => 'd']]);
        $html = $this->twig(['t' => self::SHELL])->render('t', ['opts' => ['id' => 'd', 'runtime' => 'stimulus']]);

        self::assertStringNotContainsString('remote-form-dialog', $iife);
        self::assertStringContainsString('data-controller="remote-form-dialog"', $html);
        self::assertStringContainsString('data-remote-form-dialog-target="dialog"', $html);
        self::assertStringContainsString('data-remote-form-dialog-delegate-value="true"', $html);
        self::assertMatchesRegularExpression('~<h2 [^>]*data-remote-form-dialog-target="title"~', $html);
        self::assertStringContainsString('data-remote-form-dialog-target="body" data-action="submit-&gt;remote-form-dialog#submit"', $html);
        // The IIFE settings stay: the peer reads them, and the IIFE runtime drives the dialog without the peer.
        self::assertStringContainsString('data-nowo-ui-remote-form data-nowo-ui-remote-form-loading="Loading…"', $html);
        self::assertSame(
            $this->withoutPeers($html),
            $this->withoutPeers($iife),
            'stimulus = the IIFE markup + the peer attributes',
        );
    }

    public function testTheGlobalSwitchAndTheOptionPrecedence(): void
    {
        $global = $this->twig(['t' => self::SHELL], 'stimulus')->render('t', ['opts' => ['id' => 'd']]);
        self::assertStringContainsString('data-remote-form-dialog-delegate-value="true"', $global);

        $optionWins = $this->twig(['t' => self::SHELL], 'stimulus')->render('t', ['opts' => ['id' => 'd', 'runtime' => 'iife']]);
        self::assertStringNotContainsString('remote-form-dialog', $optionWins);

        $invalid = $this->twig(['t' => self::SHELL], 'stimulus')->render('t', ['opts' => ['id' => 'd', 'runtime' => 'bogus']]);
        self::assertStringContainsString('data-remote-form-dialog-delegate-value="true"', $invalid, 'an invalid option falls back to the global');

        // 2.28 ancestor mode (stimulus: true) wins: no delegated controller on the dialog.
        $ancestor = $this->twig(['t' => self::SHELL], 'stimulus')->render('t', ['opts' => ['id' => 'd', 'stimulus' => true]]);
        self::assertStringNotContainsString('data-controller=', $ancestor);
        self::assertStringNotContainsString('delegate-value', $ancestor);
        self::assertStringContainsString('data-remote-form-dialog-target="dialog"', $ancestor);
    }

    public function testAHostControllerOnTheDialogIsKept(): void
    {
        $html = $this->twig(['t' => self::SHELL])->render('t', ['opts' => ['id' => 'd', 'runtime' => 'stimulus', 'attr' => ['data-controller' => 'host-thing']]]);
        self::assertStringContainsString('data-controller="host-thing remote-form-dialog"', $html);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string|null, bool}>
     */
    public static function confirmDialogRuntimes(): iterable
    {
        yield 'default' => [[], null, false];
        yield 'stimulus: true' => [['stimulus' => true], null, true];
        yield 'runtime option' => [['runtime' => 'stimulus'], null, true];
        yield 'global' => [[], 'stimulus', true];
        yield 'stimulus: false over the global' => [['stimulus' => false], 'stimulus', false];
        yield 'runtime iife over stimulus: true' => [['runtime' => 'iife', 'stimulus' => true], null, false];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('confirmDialogRuntimes')]
    public function testConfirmDialogWithDialogSrc(array $options, ?string $global, bool $stimulus): void
    {
        $template = "{% embed nowo_ui_partial('confirm_dialog') with {dialog_src: '/m/1/edit', dialog_id: 'edit-1', trigger_label: 'Edit'}|merge(o) %}{% endembed %}";
        $html = $this->twig(['t' => $template], $global)->render('t', ['o' => $options]);
        self::assertSame($stimulus, str_contains($html, 'data-controller="remote-form-dialog"'));
        self::assertSame($stimulus, str_contains($html, 'data-remote-form-dialog-delegate-value="true"'));
        // The opener is the same for both runtimes.
        self::assertMatchesRegularExpression('~<a href="/m/1/edit" class="[^"]*" data-nowo-ui-dialog-open="edit-1" data-nowo-ui-dialog-src="/m/1/edit" data-nowo-ui-dialog-form aria-haspopup="dialog" aria-controls="edit-1">Edit</a>~', $html);
        self::assertStringNotContainsString('data-action="remote-form-dialog#open"', $html);
    }

    // ---- confirm_shared / table ----------------------------------------------------------------------------------

    public function testTheSharedConfirmDialogCarriesThePeer(): void
    {
        $t = "{{ include(nowo_ui_partial('confirm_shared'), {id: 'x'}|merge(o), false) }}";
        self::assertStringNotContainsString('data-controller', $this->twig(['t' => $t])->render('t', ['o' => []]));
        self::assertStringContainsString('<dialog id="x" class="nowo-ui-confirm" data-nowo-ui-confirm data-nowo-confirm-shared data-controller="confirm-shared" aria-labelledby="x-title"', $this->twig(['t' => $t])->render('t', ['o' => ['runtime' => 'stimulus']]));
        self::assertStringContainsString('data-controller="confirm-shared"', $this->twig(['t' => $t], 'stimulus')->render('t', ['o' => []]));
        self::assertStringNotContainsString('data-controller', $this->twig(['t' => $t], 'stimulus')->render('t', ['o' => ['runtime' => 'iife']]));
    }

    public function testTheTablePassesItsRuntime(): void
    {
        $page = TableSharedConfirm268Test::page(2);
        $t = "{{ include(nowo_ui_partial('table'), {table: page.table, confirm_shared: true, runtime: 'stimulus'}, false) }}";
        $html = $this->twig(['t' => $t])->render('t', ['page' => $page]);
        self::assertSame(1, substr_count($html, '<dialog'));
        self::assertStringContainsString('data-controller="confirm-shared"', $html);
    }

    // ---- menus and row actions outside the table -----------------------------------------------------------------

    public function testWithoutTheOptionEachMenuKeepsItsOwnDialog(): void
    {
        $t = "{% for m in members %}{{ include(nowo_ui_partial('row_actions'), {actions: m.actions, menu: true}, false) }}{% endfor %}";
        $html = $this->twig(['t' => $t])->render('t', ['members' => $this->members()]);
        self::assertSame(2, substr_count($html, '<dialog'));
        self::assertStringNotContainsString('data-nowo-confirm-shared', $html);
    }

    public function testAGroupRendersOneDialogOutsideTheMenusWithOneFormPerRow(): void
    {
        $html = $this->page('iife');
        self::assertSame(2, substr_count($html, '<dialog'), 'one shared confirm + one remote form shell');
        self::assertSame(1, substr_count($html, '<dialog id="members-confirm"'));
        self::assertStringNotContainsString('data-controller="confirm-shared"', $html);

        // The dialog is not inside a <details> panel (a closed one would hide it).
        $dialogAt = (int) strpos($html, '<dialog id="members-confirm"');
        self::assertSame(substr_count(substr($html, 0, $dialogAt), '<details'), substr_count(substr($html, 0, $dialogAt), '</details>'));

        foreach ($this->members() as $i => $m) {
            $action = $m['actions'][1];
            $formId = $action->confirmId().'-form-'.substr((string) $action->confirmKey(), 0, 10).'-'.($i + 1);
            self::assertStringContainsString('data-nowo-confirm-form="'.$formId.'"', $html);
            self::assertMatchesRegularExpression('~<form[^>]*id="'.preg_quote($formId, '~').'"[^>]*>|<form[^>]*action="/members/'.$m['id'].'/delete"[^>]*id="'.preg_quote($formId, '~').'"~', $html);
            self::assertStringContainsString('value="tok-'.$m['id'].'"', $html);
            self::assertStringContainsString('data-nowo-confirm-title="Remove '.$m['name'].'?"', $html);
        }
        self::assertSame(2, substr_count($html, 'data-nowo-confirm-target="members-confirm"'));
    }

    public function testTheTypeScriptFixturesAreTheRenderedPages(): void
    {
        foreach (['stimulus', 'iife'] as $runtime) {
            $html = $this->page($runtime);
            $file = \dirname(__DIR__, 2).'/Fixtures/stimulus-dialogs/page-'.$runtime.'.html';
            if ('1' === getenv('UIKIT_WRITE_STIMULUS_DIALOGS_FIXTURE')) {
                file_put_contents($file, $html);
            }
            self::assertFileExists($file);
            self::assertSame((string) file_get_contents($file), $html);
        }
        $stimulus = $this->page('stimulus');
        self::assertStringContainsString('<dialog id="members-confirm" class="nowo-ui-confirm" data-nowo-ui-confirm data-nowo-confirm-shared data-controller="confirm-shared"', $stimulus);
        self::assertStringContainsString('data-remote-form-dialog-delegate-value="true"', $stimulus);
        self::assertSame(
            $this->withoutPeers($stimulus),
            $this->withoutPeers($this->page('iife')),
            'the two runtimes differ only by the peer attributes of the two shells',
        );
    }

    public function testTrueUsesTheDefaultGroupAndTheGlobalSharedSwitch(): void
    {
        $t = "{% for m in members %}{{ include(nowo_ui_partial('row_actions'), {actions: m.actions, menu: true}|merge(o), false) }}{% endfor %}";
        $true = $this->twig(['t' => $t])->render('t', ['members' => $this->members(), 'o' => ['confirm_shared' => true]]);
        self::assertSame(1, substr_count($true, '<dialog id="'.ThemeExtension::MENU_CONFIRM_ID.'"'));
        self::assertSame(1, substr_count($true, '<dialog'));

        $global = $this->twig(['t' => $t], tableConfirm: 'shared')->render('t', ['members' => $this->members(), 'o' => []]);
        self::assertSame(1, substr_count($global, '<dialog'));
        self::assertStringContainsString('data-nowo-confirm-target="'.ThemeExtension::MENU_CONFIRM_ID.'"', $global);

        $off = $this->twig(['t' => $t], tableConfirm: 'shared')->render('t', ['members' => $this->members(), 'o' => ['confirm_shared' => false]]);
        self::assertSame(2, substr_count($off, '<dialog'), 'false keeps one dialog per action');

        $external = $this->twig(['t' => $t], tableConfirm: 'shared')->render('t', ['members' => $this->members(), 'o' => ['confirm_external' => true]]);
        self::assertSame(0, substr_count($external, '<dialog'), 'confirm_external: the dialogs are rendered elsewhere');
        self::assertStringNotContainsString('data-nowo-confirm-shared', $external);
    }

    public function testTwoGroupsTwoDialogsAndThePopoverMenu(): void
    {
        $t = "{% for m in members %}{{ include(nowo_ui_partial('row_actions'), {actions: m.actions, menu: true, popover: true, confirm_group: 'g' ~ loop.index}, false) }}{% endfor %}";
        $html = $this->twig(['t' => $t])->render('t', ['members' => $this->members()]);
        self::assertSame(2, substr_count($html, '<dialog'));
        self::assertStringContainsString('<dialog id="g1"', $html);
        self::assertStringContainsString('<dialog id="g2"', $html);
        // After the popover panel, not inside it.
        self::assertMatchesRegularExpression('~</ul>\s*</div>\s*<dialog id="g1"~', $html);
    }

    public function testAButtonClusterAndASingleAction(): void
    {
        $t = "{% for m in members %}{{ include(nowo_ui_partial('row_actions'), {actions: m.actions, confirm_shared: 'cluster', confirm_row: m.id}, false) }}{% endfor %}";
        $html = $this->twig(['t' => $t])->render('t', ['members' => $this->members()]);
        self::assertSame(1, substr_count($html, '<dialog id="cluster"'));
        self::assertSame(2, substr_count($html, 'data-nowo-confirm-target="cluster"'));

        $one = "{{ include(nowo_ui_partial('action'), {action: a, confirm_shared: {id: 'solo', render: true}}, false) }}{{ include(nowo_ui_partial('action'), {action: a, confirm_shared: {id: 'solo', render: true}}, false) }}";
        $single = $this->twig(['t' => $one])->render('t', ['a' => $this->members()[0]['actions'][1]]);
        self::assertSame(1, substr_count($single, '<dialog id="solo"'), 'a hash with render: true renders it, once');

        $hash = "{{ include(nowo_ui_partial('action'), {action: a, confirm_shared: {id: 'elsewhere', row: 3}}, false) }}";
        self::assertStringNotContainsString('<dialog', $this->twig(['t' => $hash])->render('t', ['a' => $this->members()[0]['actions'][1]]), 'the 2.68 hash: rendered elsewhere');
    }

    public function testTheGroupIsRenderedOncePerMainRequest(): void
    {
        $t = "{{ include(nowo_ui_partial('row_actions'), {actions: m.actions, menu: true, confirm_group: 'grp'}, false) }}";
        $request = new Request();
        $twig = $this->twig(['t' => $t], request: $request);
        self::assertSame(1, substr_count($twig->render('t', ['m' => $this->members()[0]]), '<dialog'));
        self::assertSame(0, substr_count($twig->render('t', ['m' => $this->members()[1]]), '<dialog'), 'same request: already rendered');
        self::assertSame(['grp' => true], $request->attributes->get(ThemeExtension::CONFIRM_SHARED_ATTRIBUTE));

        $next = $this->twig(['t' => $t], request: new Request());
        self::assertSame(1, substr_count($next->render('t', ['m' => $this->members()[1]]), '<dialog'), 'a new request starts empty');
    }

    // ---- ThemeExtension helpers --------------------------------------------------------------------------------

    /**
     * @return iterable<string, array{mixed, mixed, mixed, bool, string, array{id: string, row: int|string, render: bool}|null}>
     */
    public static function confirmGroups(): iterable
    {
        $menu = ThemeExtension::MENU_CONFIRM_ID;
        yield 'not given, per_row' => [null, null, null, false, 'per_row', null];
        yield 'not given, shared' => [null, null, null, false, 'shared', ['id' => $menu, 'row' => 0, 'render' => true]];
        yield 'not given, shared, external' => [null, null, null, true, 'shared', null];
        yield 'true' => [true, null, null, true, 'per_row', ['id' => $menu, 'row' => 0, 'render' => true]];
        yield 'false' => [false, null, null, false, 'shared', null];
        yield 'string' => [' g ', null, null, false, 'per_row', ['id' => 'g', 'row' => 0, 'render' => true]];
        yield 'empty string' => ['  ', null, null, false, 'shared', null];
        yield '2.68 hash' => [['id' => 'x', 'row' => 4], null, null, true, 'per_row', ['id' => 'x', 'row' => 4, 'render' => false]];
        yield 'hash render' => [['id' => 'x', 'render' => true, 'row' => 'r1'], null, null, false, 'per_row', ['id' => 'x', 'row' => 'r1', 'render' => true]];
        yield 'hash bad row' => [['id' => 'x', 'row' => 1.5], null, null, false, 'per_row', ['id' => 'x', 'row' => 0, 'render' => false]];
        yield 'hash without id' => [['row' => 1], null, null, false, 'per_row', null];
        yield 'number' => [5, null, null, false, 'per_row', null];
        yield 'group wins' => [false, 'grp', 9, false, 'per_row', ['id' => 'grp', 'row' => 9, 'render' => true]];
        yield 'blank group ignored' => [true, ' ', '', false, 'per_row', ['id' => $menu, 'row' => 0, 'render' => true]];
        yield 'row string' => [true, null, 'k-2', false, 'per_row', ['id' => $menu, 'row' => 'k-2', 'render' => true]];
    }

    /**
     * @param array{id: string, row: int|string, render: bool}|null $expected
     */
    #[DataProvider('confirmGroups')]
    public function testConfirmGroup(mixed $value, mixed $group, mixed $row, bool $external, string $tableConfirm, ?array $expected): void
    {
        self::assertSame($expected, ThemeExtension::confirmGroup($value, $group, $row, $external, $tableConfirm));
    }

    public function testConfirmSharedOnceWithoutARequestAlwaysRenders(): void
    {
        $extension = new ThemeExtension(new Theme());
        self::assertTrue($extension->confirmSharedOnce('a'));
        self::assertTrue($extension->confirmSharedOnce('a'));

        $stack = new RequestStack();
        $main = new Request();
        $stack->push($main);
        $stack->push(new Request());
        $withRequest = new ThemeExtension(new Theme(), requestStack: $stack);
        self::assertTrue($withRequest->confirmSharedOnce('a'));
        self::assertFalse($withRequest->confirmSharedOnce('a'));
        self::assertTrue($withRequest->confirmSharedOnce('b'));
        self::assertSame(['a' => true, 'b' => true], $main->attributes->get(ThemeExtension::CONFIRM_SHARED_ATTRIBUTE), 'kept on the main request (sub-requests share it)');

        $main->attributes->set(ThemeExtension::CONFIRM_SHARED_ATTRIBUTE, 'garbage');
        self::assertTrue($withRequest->confirmSharedOnce('a'), 'a non-array attribute starts over');
    }

    public function testDialogsRuntime(): void
    {
        self::assertSame('iife', ThemeExtension::dialogsRuntime());
        self::assertSame('stimulus', ThemeExtension::dialogsRuntime(null, 'stimulus'));
        self::assertSame('iife', ThemeExtension::dialogsRuntime('iife', 'stimulus'));
        self::assertSame('stimulus', ThemeExtension::dialogsRuntime('bogus', 'stimulus'));
        self::assertSame('iife', ThemeExtension::dialogsRuntime('bogus', 'bogus'));
        self::assertSame('stimulus', ThemeExtension::dialogsRuntime(null, 'iife', true));
        self::assertSame('iife', ThemeExtension::dialogsRuntime(null, 'stimulus', false));
        self::assertSame('iife', ThemeExtension::dialogsRuntime('iife', 'stimulus', true), 'a valid runtime wins over stimulus');
        self::assertSame('stimulus', ThemeExtension::dialogsRuntime(null, 'stimulus', 'yes'), 'a non-boolean stimulus is ignored');
    }
}
