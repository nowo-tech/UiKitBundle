<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

/**
 * 2.22 (REQ-UI-003): `confirm_mode` native | dialog (or the `nowo_ui_kit_row_actions_confirm` global) moves the
 * confirm_message of 1.x row_actions POST hashes from the inline `onsubmit="return confirm(…)"` handler to the UiKit
 * confirm contract — the confirm-submit Stimulus peer attributes — on all three render paths. The default stays
 * inline and byte-identical to 2.21 ({@see RowActionsConfirmBcTest}). Fixtures: tests/Fixtures/confirm-contract-2.22.
 */
final class RowActionsConfirmContractTest extends TestCase
{
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
    public function testOptInRendersTheConfirmSubmitPeer(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/confirm-contract-2.22/'.$name.'.html';
        self::assertFileExists($file);
        $html = self::renders()[$name]();
        self::assertSame(file_get_contents($file), $html);
        self::assertStringNotContainsString('return confirm(', $html, 'opt-in output must not carry an inline handler');
    }

    public function testFormPathAttributesAndHostTokens(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_row_actions.html.twig', [
            'actions' => [['kind' => 'delete', 'href' => '/d', 'method' => 'POST', 'csrf_token' => 't', 'confirm_message' => 'Sure "x" <b>?', 'attr' => ['data-controller' => 'row', 'data-action' => 'click->row#go', 'data-x' => '1', 'onsubmit' => 'evil()']]],
            'confirm_mode' => 'dialog',
        ]);

        self::assertStringContainsString('<form method="post" action="/d" class="nowo-ui-row-actions__form" data-controller="row confirm-submit" data-action="click-&gt;row#go submit-&gt;confirm-submit#confirm" data-confirm-submit-message-value="Sure &quot;x&quot; &lt;b&gt;?" data-confirm-submit-mode-value="dialog" data-x="1" onsubmit="evil()">', $html);
        self::assertStringContainsString('<input type="hidden" name="_token" value="t">', $html);
    }

    public function testLegacyPathKeepsHostTokensAndEscapesTheMessage(): void
    {
        $html = self::bare()->render('@NowoUiKitBundle/partials/_row_actions.html.twig', [
            'actions' => [['kind' => 'delete', 'href' => '/d', 'method' => 'POST', 'confirm_message' => 'Sure "x" <b>?', 'attr' => ['data-controller' => 'row', 'data-x' => '1']]],
            'confirm_mode' => 'native',
        ]);

        self::assertStringContainsString('data-controller="row confirm-submit" data-action="submit-&gt;confirm-submit#confirm" data-confirm-submit-message-value="Sure &quot;x&quot; &lt;b&gt;?"', $html);
        self::assertStringNotContainsString('data-confirm-submit-mode-value', $html);
        self::assertSame(1, substr_count($html, 'data-controller='));
        self::assertStringContainsString('data-x="1"', $html);
    }

    public function testGlobalSelectsTheModeAndTheIncludeOptionWins(): void
    {
        $vars = ['actions' => [['kind' => 'delete', 'href' => '/d', 'method' => 'POST', 'confirm_message' => 'Sure?']]];
        $twig = PageContractTwig::create();
        $twig->addGlobal('nowo_ui_kit_row_actions_confirm', 'dialog');

        self::assertStringContainsString('data-confirm-submit-mode-value="dialog"', $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $vars));
        self::assertStringContainsString('onsubmit="return confirm(', $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $vars + ['confirm_mode' => 'inline']));
        // Unknown values fall back to the 2.21 inline handler.
        self::assertStringContainsString('onsubmit="return confirm(', PageContractTwig::create()->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $vars + ['confirm_mode' => 'popup']));
    }

    /**
     * The BC option sets of {@see RowActionsConfirmBcTest} with confirm_mode native and dialog.
     *
     * @return array<string, \Closure(): string>
     */
    public static function renders(): array
    {
        $renders = [];
        foreach (RowActionsConfirmBcTest::optionSets() as $set => $vars) {
            foreach (['native', 'dialog'] as $mode) {
                $v = $vars + ['confirm_mode' => $mode];
                foreach (['bootstrap5', 'tailwind'] as $fw) {
                    $renders['form_'.$set.'_'.$fw.'_'.$mode] = static fn (): string => PageContractTwig::create([], [], $fw)->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $v);
                }
                $renders['legacy_'.$set.'_'.$mode] = static fn (): string => self::silenced(static fn (): string => self::withoutForms()->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $v));
                $renders['bare_'.$set.'_'.$mode] = static fn (): string => self::bare()->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $v);
            }
        }

        return $renders;
    }

    /**
     * Writes the fixtures (run once when the opt-in markup changes, never in CI).
     */
    public static function dumpFixtures(string $dir): void
    {
        foreach (self::renders() as $name => $render) {
            file_put_contents($dir.'/'.$name.'.html', $render());
        }
    }

    /**
     * @param \Closure(): string $render
     */
    private static function silenced(\Closure $render): string
    {
        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            return $render();
        } finally {
            restore_error_handler();
        }
    }

    private static function withoutForms(): Environment
    {
        return RowActionsConfirmBcTest::twigWithoutForms();
    }

    private static function bare(): Environment
    {
        return RowActionsConfirmBcTest::bareTwig();
    }
}
