<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Form\ActionCsrfType;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Action;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * 2.18 (REQ-TWIG-005): a POST Action with `csrfToken` (no `form`) renders through form_start / form_end with a
 * CSRF-only Form Type; the raw 2.17 form is a deprecated fallback without symfony/form's Twig extension.
 */
final class ActionPostFormTest extends TestCase
{
    private const VARS = ['action_href' => '/items/7', 'button_class' => 'btn btn-danger', 'button_html' => 'Delete', 'button_attrs' => ['data-x' => '1', 'data-on' => true, 'data-off' => false]];

    public function testActionPostPartialHasNoRawFormMarkup(): void
    {
        $source = (string) file_get_contents(PageContractTwig::root().'/src/Resources/views/partials/_action_post.html.twig');
        $code = (string) preg_replace('/\{#.*?#\}/s', '', $source);

        self::assertDoesNotMatchRegularExpression('/<(form|input|textarea|select)[\s>]/', $code);
        self::assertStringContainsString('nowo_ui_action_form(action)', $code);
    }

    public function testCsrfTokenActionRendersThroughFormStartWithTheSameContract(): void
    {
        $html = $this->post(new Action('delete', href: '/items/7', method: 'DELETE', csrfToken: 'tok"en', disabled: true), ['form_id' => 'f-1']);

        // form_start (form_div_layout): spoofed method as a self-closing hidden, token after the button (form view path).
        self::assertMatchesRegularExpression('#^<form method="post" action="/items/7" class="nowo-ui-action__form" id="f-1">'
            .'<input type="hidden" name="_method" value="DELETE" />\s*'
            .'<button type="submit" class="btn btn-danger" data-x="1" data-on disabled>Delete</button>'
            .'<input type="hidden" name="_token" value="tok&quot;en"></form>$#', trim($html));
    }

    public function testCustomCsrfFieldAndPostWithoutToken(): void
    {
        $custom = $this->post(new Action('custom', href: '/x', method: 'POST', csrfToken: 't', csrfField: '_csrf_token'));
        self::assertStringContainsString('<input type="hidden" name="_csrf_token" value="t">', $custom);
        self::assertStringNotContainsString('_method', $custom);
        self::assertSame(1, substr_count($custom, '<input'));

        $bare = $this->post(new Action('custom', href: '/x', method: 'POST'));
        self::assertMatchesRegularExpression('#^<form method="post" action="/items/7" class="nowo-ui-action__form">\s*<button type="submit" class="btn btn-danger" data-x="1" data-on>Delete</button></form>$#', trim($bare));
    }

    public function testFormViewActionKeepsItsForm(): void
    {
        $form = PageContractTwig::forms()->createNamed('del', FormType::class)->add('_token', HiddenType::class, ['data' => 'ft'])->createView();
        $html = $this->post(new Action('delete', href: '/d', method: 'POST', csrfToken: 'ignored', form: $form));

        self::assertStringContainsString('<form name="del" method="post" action="/items/7" class="nowo-ui-action__form">', $html);
        self::assertStringContainsString('name="del[_token]" value="ft"', $html);
        self::assertStringNotContainsString('ignored', $html);
    }

    public function testToolbarAndConfirmCallersUseTheFormPath(): void
    {
        $twig = PageContractTwig::create();
        $toolbar = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('restore', 'Restore', '/r', method: 'PATCH', csrfToken: 'rt')]);
        self::assertStringContainsString('<input type="hidden" name="_method" value="PATCH" />', $toolbar);
        self::assertStringContainsString('<input type="hidden" name="_token" value="rt">', $toolbar);
    }

    public function testThemeExtensionReturnsNullForGetActionsAndUsesTheInjectedFactory(): void
    {
        $extension = new ThemeExtension(Theme::fromConfig('bootstrap5', []), actionForms: Forms::createFormFactory());
        $twig = PageContractTwig::create();

        self::assertNull($extension->actionForm($twig, new Action('view', href: '/v')));
        $view = $extension->actionForm($twig, new Action('custom', href: '/x', method: 'PUT', csrfToken: 'k'));
        self::assertNotNull($view);
        self::assertSame('k', $view->children['_token']->vars['value']);
    }

    public function testLegacyFallbackWithoutTheFormExtensionIsDeprecated(): void
    {
        $twig = $this->twigWithoutForms();
        $html = $this->deprecated($twig, new Action('delete', href: '/items/7', method: 'DELETE', csrfToken: 'tok'), $messages);

        self::assertMatchesRegularExpression('#<form method="post" action="/items/7" class="nowo-ui-action__form">\s*<input type="hidden" name="_method" value="DELETE">\s*<input type="hidden" name="_token" value="tok">\s*<button type="submit" class="btn btn-danger" data-x="1" data-on>Delete</button>\s*</form>#', $html);
        self::assertCount(1, $messages);
        self::assertStringContainsString('Since nowo-tech/ui-kit-bundle 2.18', $messages[0]);
        self::assertStringContainsString('removed in 3.0', $messages[0]);
    }

    public function testLegacyFallbackForAnInvalidCsrfFieldName(): void
    {
        $html = $this->deprecated(PageContractTwig::create(), new Action('custom', href: '/x', method: 'POST', csrfToken: 't', csrfField: 'token[x]'), $messages);

        self::assertStringContainsString('<input type="hidden" name="token[x]" value="t">', $html);
        self::assertCount(1, $messages);
        self::assertStringContainsString('(got "token[x]")', $messages[0]);
    }

    public function testCsrfTypeBuildsTheHiddenTokenOnly(): void
    {
        $factory = Forms::createFormFactory();
        $view = ActionCsrfType::createActionView($factory, new Action('custom', href: '/x', method: 'POST', csrfToken: 'v', csrfField: 'my_token'));

        self::assertNotNull($view);
        self::assertSame(['my_token'], array_keys($view->children));
        self::assertSame('my_token', $view->children['my_token']->vars['full_name']);
        self::assertSame('v', $view->children['my_token']->vars['value']);
        self::assertCount(0, $factory->createNamed('', ActionCsrfType::class)->createView());
        self::assertNull(ActionCsrfType::createActionView($factory, new Action('custom', href: '/x', method: 'POST', csrfField: 'a b')));

        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $factory->createNamed('', ActionCsrfType::class, null, ['csrf_field' => 'a[b]']);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function post(Action $action, array $vars = []): string
    {
        return PageContractTwig::create()->render('@NowoUiKitBundle/partials/_action_post.html.twig', ['action' => $action] + $vars + self::VARS);
    }

    /**
     * @param list<string>|null $messages
     */
    private function deprecated(Environment $twig, Action $action, ?array &$messages): string
    {
        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            return $twig->render('@NowoUiKitBundle/partials/_action_post.html.twig', ['action' => $action] + self::VARS);
        } finally {
            restore_error_handler();
        }
    }

    private function twigWithoutForms(): Environment
    {
        $fs = new FilesystemLoader();
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment($fs, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new ThemeExtension(Theme::fromConfig('bootstrap5', []), translator: new Translator('en')));

        return $twig;
    }
}
