<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * BC (byte-exact): the 1.x `row_actions` POST hashes render exactly as UiKit 2.21.0 printed them, through all three
 * paths — `_row_actions_form` (symfony/form + ThemeExtension), `_row_actions_legacy` with the 2.19 deprecation
 * (ThemeExtension without symfony/form's Twig extension) and `_row_actions_legacy` in a bare Twig environment. The
 * fixtures under tests/Fixtures/bc-2.21 were rendered by UiKit 2.21.0 (origin/main e0f552e) from exactly these
 * scenarios; no whitespace normalisation.
 */
final class RowActionsConfirmBcTest extends TestCase
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
    public function testRowActionPostHashesRenderAsIn221(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.21/'.$name.'.html';
        self::assertFileExists($file);
        self::assertSame(file_get_contents($file), self::renders()[$name](), $name.' must render exactly as UiKit 2.21 printed it');
    }

    /**
     * Option sets of a 1.x POST hash (the confirm_message ones are the inline `onsubmit` confirm of REQ-UI-003).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function optionSets(): array
    {
        return [
            'confirm' => ['actions' => [['kind' => 'delete', 'href' => '/d/1', 'method' => 'POST', 'csrf_token' => 'tok', 'confirm_message' => 'Delete this item?']], 'display' => 'icon'],
            'confirm_quotes_attr' => ['actions' => [['kind' => 'delete', 'href' => '/d/2', 'method' => 'post', 'csrf_token' => 'tok', 'csrf_field' => '_csrf_token', 'confirm_message' => 'Sure "x" <b>&</b>?', 'attr' => ['data-x' => '1', 'class' => 'ignored', 'onsubmit' => 'evil()']]], 'display' => 'text'],
            'confirm_disabled_icon_text' => ['actions' => [['kind' => 'restore', 'href' => '/r', 'method' => 'POST', 'confirm_message' => 'Restore?', 'disabled' => true, 'label' => 'Bring back']], 'display' => 'icon_text'],
            'no_confirm' => ['actions' => [['kind' => 'delete', 'href' => '/d/3', 'method' => 'POST', 'csrf_token' => 'tok'], ['kind' => 'restore', 'href' => '/r/3', 'method' => 'POST', 'confirm_message' => '']], 'display' => 'icon'],
            'mixed_row' => ['actions' => [['kind' => 'edit', 'href' => '/e'], ['kind' => 'delete', 'href' => '/d/4', 'method' => 'POST', 'csrf_token' => 't4', 'confirm_message' => 'Sure?', 'attr' => ['data-y' => '2']], ['kind' => 'delete', 'confirm_id' => 'c1']], 'display' => 'icon'],
        ];
    }

    /**
     * Scenario name => render callback.
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $renders = [];
        foreach (self::optionSets() as $set => $vars) {
            foreach (['bootstrap5', 'tailwind'] as $fw) {
                $renders['form_'.$set.'_'.$fw] = static fn (): string => PageContractTwig::create([], [], $fw)->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $vars);
            }
            $renders['legacy_'.$set] = static fn (): string => self::silenced(static fn (): string => self::twigWithoutForms()->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $vars));
            $renders['bare_'.$set] = static fn (): string => self::bareTwig()->render('@NowoUiKitBundle/partials/_row_actions.html.twig', $vars);
        }

        return $renders;
    }

    /**
     * Writes the fixtures (run once against the reference revision, never in CI).
     */
    public static function dumpFixtures(string $dir): void
    {
        foreach (self::renders() as $name => $render) {
            file_put_contents($dir.'/'.$name.'.html', $render());
        }
    }

    /**
     * @param Closure(): string $render
     */
    private static function silenced(Closure $render): string
    {
        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            return $render();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * UiKit ThemeExtension without symfony/form's Twig extension (the deprecated legacy path).
     */
    public static function twigWithoutForms(): Environment
    {
        $fs = new FilesystemLoader();
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKit');
        $twig = new Environment(new ChainLoader([new ArrayLoader([]), $fs]), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new ThemeExtension(Theme::fromConfig('bootstrap5', []), translator: new Translator('en')));
        $twig->addFunction(new TwigFunction('path', static fn (string $name, array $params = []): string => '/'.$name));
        $twig->addGlobal('app', ['request' => new Request()]);

        return $twig;
    }

    /**
     * Bare Twig environment without the UiKit ThemeExtension (bundle tests rendering kit partials).
     */
    public static function bareTwig(): Environment
    {
        $fs = new FilesystemLoader();
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment($fs, ['strict_variables' => false]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));

        return $twig;
    }
}
