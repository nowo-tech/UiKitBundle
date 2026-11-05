<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.29 — the `confirm_dialog` anatomy is an explicit opt-in (`anatomy: true`). An embed without `only` passes the
 * whole parent context, so a `title`, `title_html`, `status`, `size` or `title_id` that is merely in scope (a page
 * title, an include parameter, an outer embed parameter) must not switch the partial to the anatomy nor add an
 * `aria-labelledby`: the markup stays byte-identical to UiKit 2.28. The fixtures under tests/Fixtures/bc-2.28 were
 * rendered by UiKit 2.28.0 (`_confirm_dialog.html.twig` of tag v2.28.0) from exactly these scenarios; no whitespace
 * normalisation.
 */
final class ConfirmDialogAnatomy229BcTest extends TestCase
{
    /** The parent context a host page commonly has (Beacon `organizations/_confirm_form.html.twig` style). */
    private const CONTEXT = [
        'title' => 'Members',
        'title_html' => '<b>Members</b>',
        'status' => 'active',
        'size' => 'wide',
        'title_id' => 'page-title',
        'dismissible' => false,
        'close_label' => 'Close it',
    ];

    private const FORM_PANEL = <<<'TWIG'
{% embed nowo_ui_partial('confirm_dialog') with opts %}
    {% block form %}<form method="post" action="/members/1/remove"><h2 id="remove-title">Remove this member?</h2><button type="button" data-confirm-dialog-close>Cancel</button><button type="submit">Remove</button></form>{% endblock %}
{% endembed %}
TWIG;

    private const NESTED = <<<'TWIG'
{% embed 'outer' with {title: 'Confirm dialog', status: {label: 'Up', tone: 'success'}} %}
    {% block demo %}
        {% embed nowo_ui_partial('confirm_dialog') with {trigger_label: 'Delete', dialog_id: 'delete-x', dialog_modifier: 'nowo-ui-confirm-dialog--lg'} %}
            {% block form %}<p>Delete it?</p>{% endblock %}
        {% endembed %}
    {% endblock %}
{% endembed %}
TWIG;

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function scenarios(): iterable
    {
        foreach (array_keys(self::renders()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('scenarios')]
    public function testInheritedAnatomyOptionsChangeNothing(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.28/'.$name.'.html';
        self::assertFileExists($file);
        $expected = (string) file_get_contents($file);
        self::assertSame($expected, self::renders()[$name](), $name.' must render exactly as UiKit 2.28 printed it');
        self::assertStringNotContainsString('nowo-ui-confirm-dialog--anatomy', $expected);
    }

    public function testNoDanglingLabelledby(): void
    {
        foreach (self::renders() as $name => $render) {
            $html = $render();
            preg_match_all('/aria-labelledby="([^"]+)"/', $html, $m);
            foreach ($m[1] as $id) {
                self::assertStringContainsString('id="'.$id.'"', $html, $name.': aria-labelledby must point to an element');
            }
        }

        $anatomy = PageContractTwig::create(['t' => self::FORM_PANEL])->render('t', ['opts' => ['anatomy' => true, 'title' => 'Remove', 'dialog_id' => 'rm']]);
        self::assertStringContainsString('aria-labelledby="rm-title"', $anatomy);
        self::assertStringContainsString('<h2 class="nowo-ui-dialog__title" id="rm-title">Remove</h2>', $anatomy);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-dialog__body"><form method="post" action="/members/1/remove">.*</form></div>#s', $anatomy, 'an overridden form block becomes the anatomy body');
    }

    /**
     * Scenario name => render callback.
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $panel = static fn (array $opts): string => PageContractTwig::create(['t' => self::FORM_PANEL])->render('t', self::CONTEXT + ['opts' => $opts]);

        return [
            'confirm_dialog_inherited_title' => static fn (): string => $panel(['trigger_label' => 'Remove', 'dialog_id' => 'remove-member']),
            'confirm_dialog_inherited_title_labelledby' => static fn (): string => $panel(['trigger_label' => 'Remove', 'dialog_id' => 'remove-member', 'aria_labelledby' => 'remove-title']),
            'confirm_dialog_inherited_title_only_dialog' => static fn (): string => $panel(['only_dialog' => true]),
            'confirm_dialog_inherited_anatomy_not_true' => static fn (): string => PageContractTwig::create(['t' => self::FORM_PANEL])->render('t', self::CONTEXT + ['anatomy' => 'true', 'opts' => ['trigger_label' => 'Remove', 'dialog_id' => 'remove-member']]),
            'confirm_dialog_nested_embed' => static fn (): string => PageContractTwig::create(['t' => self::NESTED, 'outer' => '<section><h1>{{ title }}</h1>{% block demo %}{% endblock %}</section>'])->render('t'),
        ];
    }
}
