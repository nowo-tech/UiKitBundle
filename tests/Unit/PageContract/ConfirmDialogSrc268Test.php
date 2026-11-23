<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.68 — `confirm_dialog` on demand (`dialog_src`). Without `dialog_src` the partial prints exactly what UiKit 2.67.0
 * printed: the fixtures under tests/Fixtures/bc-2.67 were rendered by `_confirm_dialog.html.twig` of tag v2.67.0 from
 * these scenarios (no whitespace normalisation), with a parent context that holds a `src` and a `title` (an embed
 * without `only` inherits them). With `dialog_src` the page holds only the opener (a real link to that URL) and an
 * empty remote form dialog shell: none of the `form` / `body` / `footer` blocks is rendered.
 */
final class ConfirmDialogSrc268Test extends TestCase
{
    private const CONTEXT = ['src' => '/img/logo.png', 'title' => 'Members', 'size' => 'wide'];

    private const EMBED = <<<'TWIG'
{% embed nowo_ui_partial('confirm_dialog') with opts %}
    {% block form %}<form method="post" action="/members/1/edit" data-testid="edit-form"><input name="name"><button type="button" data-confirm-dialog-close>Cancel</button><button type="submit">Save</button></form>{% endblock %}
    {% block body %}<div data-testid="anatomy-body">{{ block('form') }}</div>{% endblock %}
    {% block footer %}<button type="submit" form="edit" data-testid="anatomy-footer">Save</button>{% endblock %}
{% endembed %}
TWIG;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function bcOptions(): array
    {
        return [
            'full' => ['trigger_label' => 'Edit', 'dialog_id' => 'edit-1', 'dialog_modifier' => 'nowo-ui-confirm-dialog--lg', 'trigger_testid' => 'edit-open', 'dialog_testid' => 'edit-dialog'],
            'no-id' => ['trigger_label' => 'Revoke', 'trigger_title' => 'Revoke it', 'trigger_aria_label' => 'Revoke token'],
            'no-trigger' => ['dialog_id' => 'quiet'],
            'only-dialog' => ['only_dialog' => true, 'dialog_id' => 'only-1'],
            'wrapper-false' => ['wrapper' => false, 'trigger_label' => 'Ignored'],
            'type-to-confirm' => ['trigger_label' => 'Delete', 'dialog_id' => 'del-1', 'expected_value' => 'acme', 'open_on_connect' => true, 'attr' => ['class' => 'extra', 'data-x' => '1'], 'dialog_attr' => ['class' => 'more', 'data-y' => '2'], 'trigger_disabled' => true],
            'anatomy' => ['anatomy' => true, 'trigger_label' => 'Edit', 'title' => 'Edit member', 'dialog_id' => 'anat-1', 'size' => 'wide', 'phone' => 'sheet'],
            'anatomy-status' => ['anatomy' => true, 'title' => 'Edit member', 'dialog_id' => 'anat-2', 'status' => ['label' => 'Up', 'tone' => 'success'], 'dismissible' => false],
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function bcScenarios(): iterable
    {
        foreach (array_keys(self::bcOptions()) as $name) {
            yield $name => [$name];
        }
    }

    public static function render(array $opts): string
    {
        return PageContractTwig::create(['t' => self::EMBED])->render('t', self::CONTEXT + ['opts' => $opts]);
    }

    #[DataProvider('bcScenarios')]
    public function testWithoutDialogSrcTheMarkupIsByteIdenticalTo267(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.67/confirm-dialog-'.$name.'.html';
        self::assertFileExists($file);
        self::assertSame((string) file_get_contents($file), self::render(self::bcOptions()[$name]), $name.' must render exactly as UiKit 2.67.0 printed it');
    }

    #[DataProvider('bcScenarios')]
    public function testEmptyDialogSrcKeepsTheInlineMarkup(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.67/confirm-dialog-'.$name.'.html';
        foreach ([null, '', false] as $off) {
            self::assertSame((string) file_get_contents($file), self::render(self::bcOptions()[$name] + ['dialog_src' => $off]), $name);
        }
    }

    public function testDialogSrcRendersOnlyTheOpenerAndAnEmptyShell(): void
    {
        $html = self::render(['dialog_src' => '/members/1/edit?tab=a&b=1', 'trigger_label' => 'Edit', 'dialog_id' => 'edit-1', 'title' => 'Edit member', 'trigger_testid' => 'edit-open']);

        self::assertStringNotContainsString('edit-form', $html, 'the form block is never rendered');
        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('<input', $html);
        self::assertStringNotContainsString('anatomy-body', $html);
        self::assertStringNotContainsString('anatomy-footer', $html);
        self::assertStringNotContainsString('data-controller="confirm-dialog"', $html, 'no Stimulus confirm-dialog root: the remote dialog runtime drives it');

        self::assertMatchesRegularExpression('~<a href="([^"]+)"~', $html);
        preg_match('~<a href="([^"]+)"([^>]*)>Edit</a>~', $html, $m);
        self::assertNotEmpty($m, 'the opener is a real link');
        self::assertSame('/members/1/edit?tab=a&amp;b=1', $m[1], 'the opener href is dialog_src (escaped)');
        self::assertStringContainsString('data-nowo-ui-dialog-src="/members/1/edit?tab=a&amp;b=1"', $m[2]);
        self::assertStringContainsString('data-nowo-ui-dialog-open="edit-1"', $m[2]);
        self::assertStringContainsString('data-nowo-ui-dialog-form', $m[2]);
        self::assertStringContainsString('data-nowo-ui-dialog-title="Edit member"', $m[2]);
        self::assertStringContainsString('aria-haspopup="dialog"', $m[2]);
        self::assertStringContainsString('aria-controls="edit-1"', $m[2]);
        self::assertStringContainsString('data-testid="edit-open"', $m[2]);

        // The shell: the remote form dialog with an empty body and an empty footer target.
        self::assertSame(1, substr_count($html, '<dialog'));
        self::assertStringContainsString('id="edit-1"', $html);
        self::assertStringContainsString('data-nowo-ui-remote-form', $html);
        self::assertMatchesRegularExpression('~<div[^>]*data-nowo-ui-dialog-body[^>]*>\s*</div>~', $html, 'the body is empty until the dialog opens');
        self::assertStringContainsString('aria-labelledby="edit-1-title"', $html);
        self::assertStringContainsString('id="edit-1-title"', $html);
        self::assertStringContainsString('>Edit member</h2>', $html);
    }

    public function testDialogSrcIgnoresTheAnatomyBlocks(): void
    {
        $html = self::render(['dialog_src' => '/members/1/edit', 'anatomy' => true, 'trigger_label' => 'Edit', 'dialog_id' => 'a', 'title' => 'Edit']);
        self::assertStringNotContainsString('anatomy-body', $html);
        self::assertStringNotContainsString('anatomy-footer', $html);
        self::assertStringNotContainsString('edit-form', $html);
    }

    public function testDialogSrcSizes(): void
    {
        self::assertStringNotContainsString('nowo-ui-dialog--lg', self::render(['dialog_src' => '/x', 'dialog_id' => 'a', 'size' => 'md']), 'md (the default) is the medium size');
        self::assertStringContainsString('nowo-ui-dialog--lg', self::render(['dialog_src' => '/x', 'dialog_id' => 'a', 'size' => 'wide']));
        self::assertStringContainsString('nowo-ui-dialog--lg', self::render(['dialog_src' => '/x', 'dialog_id' => 'a', 'size' => null, 'dialog_modifier' => 'nowo-ui-confirm-dialog--xl']));
        self::assertStringNotContainsString('nowo-ui-dialog--lg', self::render(['dialog_src' => '/x', 'dialog_id' => 'a', 'size' => null, 'dialog_modifier' => 'nowo-ui-confirm-dialog--sm']));
    }

    public function testShellFalseRendersOnlyTheOpenerAndOnlyDialogOnlyTheShell(): void
    {
        $opener = self::render(['dialog_src' => '/members/1/edit', 'trigger_label' => 'Edit', 'dialog_id' => 'shared', 'dialog_shell' => false]);
        self::assertStringNotContainsString('<dialog', $opener);
        self::assertStringContainsString('<a href="/members/1/edit"', $opener);
        self::assertStringContainsString('data-nowo-ui-dialog-open="shared"', $opener);

        $shell = self::render(['dialog_src' => '/members/1/edit', 'trigger_label' => 'Edit', 'dialog_id' => 'shared', 'only_dialog' => true]);
        self::assertStringNotContainsString('<a ', $shell);
        self::assertSame(1, substr_count($shell, '<dialog'));
        self::assertStringContainsString('id="shared"', $shell);
    }

    public function testDisabledTriggerIsADisabledButtonWithoutLink(): void
    {
        $html = self::render(['dialog_src' => '/members/1/edit', 'trigger_label' => 'Edit', 'dialog_id' => 'd', 'trigger_disabled' => true]);
        self::assertStringNotContainsString('<a ', $html);
        self::assertMatchesRegularExpression('~<button type="button"[^>]* disabled[^>]*>Edit</button>~', $html);
    }

    public function testCustomTriggerBlockAndGeneratedId(): void
    {
        $twig = PageContractTwig::create(['t' => <<<'TWIG'
{% embed nowo_ui_partial('confirm_dialog') with {dialog_src: '/members/1/edit', title: 'Edit member'} %}
    {% block trigger %}<a href="{{ dialog_opener_href }}" class="mine"{{ nowo_ui_html_attrs(dialog_opener_attr) }}>Custom</a>{% endblock %}
    {% block form %}<form data-testid="edit-form"></form>{% endblock %}
{% endembed %}
TWIG]);
        $html = $twig->render('t');
        self::assertStringNotContainsString('edit-form', $html);
        self::assertMatchesRegularExpression('~<a href="/members/1/edit" class="mine" data-nowo-ui-dialog-open="(nowo-ui-dialog-\d+)"~', $html);
        preg_match('~data-nowo-ui-dialog-open="([^"]+)"~', $html, $m);
        self::assertStringContainsString('<dialog id="'.$m[1].'"', $html);
    }

    /** The markup the TypeScript test (nowo-ui-dialog-src.test.ts) drives: keep both in step. */
    public const SRC_OPTS = ['dialog_src' => '/members/1/edit', 'dialog_id' => 'edit-1', 'trigger_label' => 'Edit', 'title' => 'Edit member', 'size' => 'wide', 'trigger_testid' => 'edit-open'];

    public function testDialogSrcMarkupIsTheOneTheTypeScriptTestDrives(): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/dialog-src/confirm-dialog-src.html';
        if ('1' === getenv('UIKIT_WRITE_DIALOG_SRC_FIXTURE')) {
            file_put_contents($file, self::render(self::SRC_OPTS));
        }
        self::assertFileExists($file);
        self::assertSame((string) file_get_contents($file), self::render(self::SRC_OPTS));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function scriptUrls(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'mixed case + blanks' => [' JavaScript:alert(1)'];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'blob' => ['blob:https://example.test/1'];
    }

    #[DataProvider('scriptUrls')]
    public function testAScriptUrlPrintsADisabledOpenerAndNoLink(string $url): void
    {
        $html = self::render(['dialog_src' => $url, 'trigger_label' => 'Edit', 'dialog_id' => 'x', 'title' => 'Edit']);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringNotContainsString('data-nowo-ui-dialog-src', $html);
        self::assertStringNotContainsString('data-nowo-ui-dialog-open', $html);
        self::assertStringNotContainsString('edit-form', $html, 'still on demand: no body');
        self::assertMatchesRegularExpression('~<button type="button"[^>]* disabled[^>]*>Edit</button>~', $html);

        $custom = PageContractTwig::create(['t' => <<<'TWIG'
{% embed nowo_ui_partial('confirm_dialog') with {dialog_src: url, dialog_id: 'x'} %}
    {% block trigger %}<a href="{{ dialog_opener_href }}"{{ nowo_ui_html_attrs(dialog_opener_attr) }}>Custom</a>{% endblock %}
{% endembed %}
TWIG])->render('t', ['url' => $url]);
        self::assertStringContainsString('<a href="">Custom</a>', $custom);
    }

    public function testOnlyAStringTurnsTheModeOn(): void
    {
        foreach ([42, 1.5, true, ['/x'], new ArrayObject(['/x'])] as $value) {
            self::assertSame((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/bc-2.67/confirm-dialog-full.html'), self::render(self::bcOptions()['full'] + ['dialog_src' => $value]), get_debug_type($value));
        }
    }

    public function testTheSharedShellRecipeRendersOneRemoteShellForManyOpeners(): void
    {
        $twig = PageContractTwig::create(['t' => <<<'TWIG'
{% for id in [1, 2, 3] %}
    {% embed nowo_ui_partial('confirm_dialog') with {dialog_src: '/members/' ~ id ~ '/edit', dialog_id: 'edit-member', dialog_shell: false, trigger_label: 'Edit', title: 'Edit #' ~ id} %}{% block form %}<form data-testid="edit-form"></form>{% endblock %}{% endembed %}
{% endfor %}
{% embed nowo_ui_partial('confirm_dialog') with {only_dialog: true, dialog_shell: true, dialog_id: 'edit-member', title: 'Edit member', size: 'wide'} %}{% block form %}<form data-testid="edit-form"></form>{% endblock %}{% endembed %}
TWIG]);
        $html = $twig->render('t');
        self::assertSame(1, substr_count($html, '<dialog'));
        self::assertSame(3, substr_count($html, 'data-nowo-ui-dialog-open="edit-member"'));
        self::assertStringContainsString('<dialog id="edit-member" class="nowo-ui-dialog nowo-ui-dialog--lg nowo-ui-dialog--remote-form"', $html);
        self::assertMatchesRegularExpression('~<div[^>]*data-nowo-ui-dialog-body[^>]*>\s*</div>~', $html, 'the remote body target the openers load into');
        self::assertStringNotContainsString('edit-form', $html);
        self::assertStringNotContainsString('data-confirm-dialog-target', $html, 'not the inline Stimulus dialog');

        // only_dialog without dialog_shell: true (or with false) keeps the 2.67 inline dialog
        $inline = PageContractTwig::create(['t' => "{% embed nowo_ui_partial('confirm_dialog') with {only_dialog: true, dialog_id: 'only-1', dialog_shell: flag} %}{% endembed %}"]);
        foreach ([false, null, 'true', 1] as $flag) {
            self::assertStringContainsString('data-confirm-dialog-target="dialog"', $inline->render('t', ['flag' => $flag]));
        }
    }

    public function testNoRawUnescapedSrc(): void
    {
        $html = self::render(['dialog_src' => '/x?"><script>alert(1)</script>', 'trigger_label' => 'Edit', 'dialog_id' => 'e']);
        self::assertStringNotContainsString('<script>', $html);
    }
}
