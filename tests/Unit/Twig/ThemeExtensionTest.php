<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\ArrayLoader as TranslationArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class ThemeExtensionTest extends TestCase
{
    public function testFunctionsFiltersAndGlobals(): void
    {
        $theme = new Theme('bootstrap5', classes: ['btn_primary' => 'btn-moss'], attributes: ['toasts' => ['data-controller' => 'toast-stack']], partials: ['pagination' => 'kit/_p.html.twig']);
        $extension = new ThemeExtension($theme, ['admin' => 'layouts/admin.html.twig', 'guest' => 'layouts/guest.html.twig']);

        $names = array_map(static fn (TwigFunction $f): string => $f->getName(), $extension->getFunctions());
        self::assertSame(['nowo_ui_class', 'nowo_ui_attrs', 'nowo_ui_partial', 'nowo_ui_theme_tokens', 'nowo_ui_theme_css', 'nowo_ui_breadcrumb_kit', 'nowo_ui_breadcrumb_kit_available'], $names);
        self::assertSame('nowo_ui_trans', $extension->getFilters()[0]->getName());
        self::assertSame(['admin' => 'layouts/admin.html.twig', 'guest' => 'layouts/guest.html.twig'], $extension->getGlobals()['nowo_ui_layout']);
        self::assertSame($theme, $extension->getGlobals()['nowo_ui_theme']);
        self::assertSame(['breadcrumb_kit' => true, 'collection' => null], $extension->getGlobals()['nowo_ui_breadcrumbs']);

        $html = $this->twig($extension, "<a class=\"{{ nowo_ui_class('btn_primary') }}\"{{ nowo_ui_attrs('toasts', null, {'data-x': '\"'}) }}>{{ nowo_ui_partial('pagination') }}|{{ nowo_ui_layout.admin }}</a>")->render('t');
        self::assertSame('<a class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary btn-moss" data-controller="toast-stack" data-x="&quot;">kit/_p.html.twig|layouts/admin.html.twig</a>', $html);
    }

    public function testTokensStyleWithRequestNonceExplicitNonceAndNoTokens(): void
    {
        $theme = new Theme(tokens: ['primary' => 'var(--moss)']);
        $stack = new RequestStack();
        $request = new Request();
        $request->attributes->set('csp_nonce', 'n0nce');
        $stack->push($request);

        $extension = new ThemeExtension($theme, requestStack: $stack);
        self::assertSame("<style nonce=\"n0nce\" data-nowo-ui-theme-tokens>\n:root {\n  --nowo-ui-primary: var(--moss);\n}\n</style>", $extension->tokens());
        self::assertStringStartsWith('<style nonce="x&quot;" ', $extension->tokens('x"'));
        self::assertStringStartsWith('<style data-nowo-ui-theme-tokens>', (new ThemeExtension($theme))->tokens());
        self::assertStringStartsWith('<style data-nowo-ui-theme-tokens>', $extension->tokens(''));
        self::assertSame('', (new ThemeExtension(new Theme()))->tokens('n'));
        self::assertSame(":root {\n  --nowo-ui-primary: var(--moss);\n}", $this->twig($extension, '{{ nowo_ui_theme_css() }}')->render('t'));
    }

    public function testTrans(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new TranslationArrayLoader());
        $translator->addResource('array', ['items.title' => 'Items of %owner%'], 'en', 'admin');
        $extension = new ThemeExtension(new Theme(), translator: $translator);

        self::assertSame('Items of Ann', $extension->trans(new Label('items.title', 'admin', ['%owner%' => 'Ann'])));
        self::assertSame('Items of Bob', $extension->trans(new Label('items.title', 'admin', ['%owner%' => 'Ann']), ['%owner%' => 'Bob']));
        self::assertSame('Items of Cy', $extension->trans(['key' => 'items.title', 'domain' => 'admin', 'params' => ['%owner%' => 'Cy']]));
        self::assertSame('items.title', $extension->trans(['key' => 'items.title', 'domain' => 5, 'params' => 'x']));
        self::assertSame('Plain <b>', $extension->trans('Plain <b>'));
        self::assertSame('', $extension->trans(null));
        self::assertSame('42', $extension->trans(42));
        self::assertSame('str', $extension->trans(new class implements \Stringable {
            public function __toString(): string
            {
                return 'str';
            }
        }));

        $noTranslator = new ThemeExtension(new Theme());
        self::assertSame('Hi Ann', $noTranslator->trans(new Label('Hi %name%', params: ['%name%' => 'Ann', '%x%' => new \stdClass()])));

        $this->expectException(\InvalidArgumentException::class);
        $extension->trans(new \stdClass());
    }

    public function testTransFilterEscapesStrings(): void
    {
        self::assertSame('&lt;b&gt;', $this->twig(new ThemeExtension(new Theme()), '{{ v|nowo_ui_trans }}')->render('t', ['v' => '<b>']));
    }

    public function testBreadcrumbKitBridge(): void
    {
        $extension = new ThemeExtension(new Theme(), breadcrumbs: ['breadcrumb_kit' => true, 'collection' => 'admin']);
        $twig = $this->twig($extension, '{{ nowo_ui_breadcrumb_kit_available() ? "yes" : "no" }}|{{ nowo_ui_breadcrumb_kit(c) }}');
        self::assertSame('no|', $twig->render('t', ['c' => null]));

        $twig = $this->twig($extension, '{{ nowo_ui_breadcrumb_kit_available() ? "yes" : "no" }}|{{ nowo_ui_breadcrumb_kit(c) }}');
        $twig->addFunction(new TwigFunction('breadcrumb_render', static fn (Environment $env, ?string $collection = null): string => '<nav>'.($collection ?? 'default').'</nav>', ['is_safe' => ['html'], 'needs_environment' => true]));
        self::assertSame('yes|<nav>admin</nav>', $twig->render('t', ['c' => null]));
        self::assertSame('yes|<nav>front</nav>', $twig->render('t', ['c' => 'front']));

        $disabled = new ThemeExtension(new Theme(), breadcrumbs: ['breadcrumb_kit' => false, 'collection' => null]);
        $twig = $this->twig($disabled, '{{ nowo_ui_breadcrumb_kit_available() ? "yes" : "no" }}|{{ nowo_ui_breadcrumb_kit() }}');
        $twig->addFunction(new TwigFunction('breadcrumb_render', static fn (): string => 'x'));
        self::assertSame('no|', $twig->render('t'));

        $weird = $this->twig($extension, '{{ nowo_ui_breadcrumb_kit() }}');
        $weird->addFunction(new TwigFunction('breadcrumb_render', static fn (Environment $env): array => []));
        self::assertSame('', $weird->render('t'));

        $noCallable = $this->twig($extension, '{{ nowo_ui_breadcrumb_kit() }}');
        $noCallable->addFunction(new TwigFunction('breadcrumb_render', null, ['node_class' => \Twig\Node\Expression\FunctionExpression::class]));
        self::assertSame('', $noCallable->render('t'));
    }

    private function twig(ThemeExtension $extension, string $template): Environment
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]), ['strict_variables' => true]);
        $twig->addExtension($extension);

        return $twig;
    }
}
