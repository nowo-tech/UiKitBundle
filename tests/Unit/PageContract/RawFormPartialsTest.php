<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Form\HiddenFieldsType;
use Nowo\UiKitBundle\Form\PerPageType;
use Nowo\UiKitBundle\Form\SearchFilterType;
use Nowo\UiKitBundle\Form\UiFormViews;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * 2.19 (REQ-TWIG-005): the partials that printed a hand-written form / input (row_actions 1.x POST hashes, user_menu
 * logout, locale_switcher, filters, pagination_compact per page, segmented plain checkbox, the shared token of
 * _action_form_view) render Form Types through form_start / form_widget / form_end with the same request contract.
 * Without symfony/form's Twig extension (or with an invalid field name) they print the deprecated raw markup; a bare
 * Twig environment without UiKit's ThemeExtension prints it too, without a deprecation.
 */
final class RawFormPartialsTest extends TestCase
{
    private const ROW_POST = ['kind' => 'delete', 'href' => '/d/1', 'method' => 'POST', 'csrf_token' => 'tok', 'csrf_field' => '_csrf_token', 'confirm_message' => 'Sure "x"?', 'attr' => ['data-x' => '1', 'class' => 'ignored']];
    private const LOCALES = [['code' => 'en', 'label' => 'English', 'form_action' => '/locale/en', 'csrf_token' => 'lt', 'redirect' => '/back', 'current' => true], ['code' => 'es', 'label' => 'Español', 'href' => '/es']];

    /**
     * @return iterable<string, array{string}>
     */
    public static function partials(): iterable
    {
        foreach (['_row_actions', '_user_menu', '_locale_switcher', '_filters', '_pagination_compact', '_segmented', '_action_form_view', '_ui_form', '_row_actions_form', '_user_menu_logout_form', '_locale_switcher_form', '_filters_form', '_pagination_per_page_form', '_segmented_checkbox_form'] as $partial) {
            yield $partial => [$partial];
        }
    }

    #[DataProvider('partials')]
    public function testPartialHasNoRawFormMarkup(string $partial): void
    {
        $source = (string) file_get_contents(PageContractTwig::root().'/src/Resources/views/partials/'.$partial.'.html.twig');

        self::assertDoesNotMatchRegularExpression('/<(form|input|textarea|select)(\s|>|$)/m', $source);
    }

    public function testRowActionPostHashRendersThroughFormStart(): void
    {
        $html = $this->render('_row_actions', ['actions' => [self::ROW_POST], 'display' => 'text']);

        self::assertStringContainsString('<form method="post" action="/d/1" class="nowo-ui-row-actions__form" onsubmit="return confirm(&quot;Sure \&quot;x\&quot;?&quot;);" data-x="1"><input type="hidden" name="_csrf_token" value="tok">', $html);
        self::assertStringNotContainsString('ignored', $html);
        self::assertMatchesRegularExpression('#<button\s+type="submit"\s+class="[^"]*nowo-ui-action--delete[^"]*"\s+title="Delete"\s+aria-label="Delete"\s*>\s*<span class="nowo-ui-action__label">Delete</span>\s*</button>\s*</form>#', $html);

        $bare = $this->render('_row_actions', ['actions' => [['kind' => 'restore', 'href' => '/r', 'method' => 'POST']], 'display' => 'icon']);
        self::assertMatchesRegularExpression('#<form method="post" action="/r" class="nowo-ui-row-actions__form">\s*<button#', $bare);
    }

    public function testUserMenuLogoutRendersThroughFormStart(): void
    {
        $html = $this->render('_user_menu', ['name' => 'Ann', 'logout_path' => '/logout', 'logout_csrf' => 'lc']);
        self::assertStringContainsString('<form method="post" action="/logout" class="nowo-ui-user-menu__logout-form"><input type="hidden" name="_csrf_token" value="lc">', $html);
        self::assertSame(1, substr_count($html, '<input'));

        $noToken = $this->render('_user_menu', ['name' => 'Ann', 'logout_path' => '/logout']);
        self::assertStringNotContainsString('<input', $noToken);
        self::assertStringContainsString('class="nowo-ui-user-menu__logout-form"', $noToken);
    }

    public function testLocaleSwitcherFormRendersThroughFormStart(): void
    {
        $html = $this->render('_locale_switcher', ['locales' => self::LOCALES]);

        self::assertStringContainsString('<form method="post" action="/locale/en" class="nowo-ui-locale-switcher__form"><input type="hidden" name="_token" value="lt"><input type="hidden" name="redirect" value="/back">', $html);
        self::assertStringContainsString('class="nowo-ui-locale-switcher__option is-active"', $html);
        self::assertStringContainsString('href="/es"', $html);

        $custom = $this->render('_locale_switcher', ['locales' => [['code' => 'de', 'label' => 'Deutsch', 'form_action' => '/l', 'csrf_token' => 'c', 'csrf_field' => '_locale_token']]]);
        self::assertStringContainsString('<input type="hidden" name="_locale_token" value="c"></form>', (string) preg_replace('/\s*<button.*?<\/button>\s*/s', '', $custom));
        self::assertStringNotContainsString('name="redirect"', $custom);
    }

    public function testFiltersRenderThroughFormStart(): void
    {
        $html = $this->render('_filters', ['q' => 'abc', 'action' => '/items', 'clear_href' => '/items', 'actions' => '<a href="/x">X</a>']);

        self::assertStringContainsString('<form method="get" action="/items" class="nowo-ui-filters nowo-ui-search d-flex flex-wrap gap-2 mb-3" data-nowo-ui-filters="" role="search">', $html);
        self::assertStringContainsString('<label class="visually-hidden" for="nowo-ui-filter-q">Search…</label>', $html);
        self::assertStringContainsString('<input type="search" id="nowo-ui-filter-q" name="q" class="nowo-ui-input form-control nowo-ui-search__input" placeholder="Search…" autocomplete="off" value="abc" />', $html);
        self::assertStringContainsString('<div class="nowo-ui-filters__actions"><a href="/x">X</a></div>', $html);

        $post = $this->render('_filters', ['method' => 'post', 'q_name' => 'term', 'placeholder' => 'Find']);
        self::assertStringContainsString('<form method="post" class="nowo-ui-filters', $post);
        self::assertStringContainsString('name="term" class="nowo-ui-input form-control nowo-ui-search__input" placeholder="Find" autocomplete="off" />', $post);
    }

    public function testCompactPaginationPerPageKeepsTheQueryAndTheSlotAttributes(): void
    {
        $twig = PageContractTwig::create([], ['attributes' => ['pagination_select' => ['data-host' => 'sel', 'data-null' => null], 'pagination_per_page' => ['data-form' => 'pp']]]);
        $html = $twig->render('@NowoUiKit/partials/_pagination_compact.html.twig', ['pagination' => Pagination::fromTotal(60, 1, 25, route: 'r', query: ['tag' => ['a' => 'x', 'b' => true]]), 'per_page_choices' => [25, 50], 'id' => 'orders']);

        self::assertMatchesRegularExpression('#<form method="get" action="/r" class="nowo-ui-pagination__per-page-form" data-nowo-ui-region="per-page" data-form="pp"><input type="hidden" name="tag\[a\]" value="x"><input type="hidden" name="tag\[b\]" value="1">\s*<label class="nowo-ui-pagination__info" for="orders-per-page">#', $html);
        self::assertStringContainsString('<select id="orders-per-page" name="per_page" class="nowo-ui-pagination__select form-select form-select-sm" data-nowo-ui-autosubmit="" data-host="sel">', $html);
        self::assertStringNotContainsString('data-null', $html);
        self::assertStringContainsString('<option value="25" selected="selected">25</option>', $html);

        // A per page outside the choices selects nothing (as the raw select did).
        $odd = $twig->render('@NowoUiKit/partials/_pagination_compact.html.twig', ['pagination' => ['page' => 1, 'per_page' => 30, 'total' => 90, 'total_pages' => 3], 'route' => 'r', 'per_page_choices' => [25, 50]]);
        self::assertStringNotContainsString('selected', $odd);
    }

    public function testSegmentedPlainCheckboxIsACheckboxView(): void
    {
        $vars = ['options' => [['label' => 'All', 'checked' => false], ['label' => 'Mine', 'checked' => true]]];
        $html = $this->render('_segmented', $vars + ['name' => 'prefs[involved]', 'checked' => true]);
        self::assertStringContainsString('<input type="checkbox" id="prefs_involved" name="prefs[involved]" data-nowo-ui-segmented-input="data-nowo-ui-segmented-input" tabindex="-1" value="1" checked="checked" />', $html);

        $plain = $this->render('_segmented', $vars + ['name' => 'only', 'id' => 'o', 'value' => 'yes', 'input_attr' => ['data-model' => 'only']]);
        self::assertStringContainsString('<input type="checkbox" id="o" name="only" data-nowo-ui-segmented-input="data-nowo-ui-segmented-input" tabindex="-1" data-model="only" value="yes" />', $plain);
    }

    public function testSharedActionFormPrintsItsTokenOnEveryRender(): void
    {
        $form = PageContractTwig::forms()->createNamed('del', FormType::class)->add('_token', HiddenType::class, ['data' => 'ft'])->createView();
        $twig = PageContractTwig::create();
        $vars = ['action' => new \Nowo\UiKitBundle\View\Action('delete', href: '/d', method: 'POST', form: $form), 'action_href' => '/d', 'button_class' => 'b', 'button_html' => 'x'];
        $first = $twig->render('@NowoUiKitBundle/partials/_action_post.html.twig', $vars);
        $second = $twig->render('@NowoUiKitBundle/partials/_action_post.html.twig', $vars);

        self::assertStringContainsString('<input type="hidden" name="del[_token]" value="ft">', $first);
        self::assertStringContainsString('<input type="hidden" name="del[_token]" value="ft">', $second);
    }

    public function testWithoutTheFormExtensionThePartialsPrintTheDeprecatedRawMarkup(): void
    {
        $twig = $this->twigWithoutForms();
        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $row = $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [self::ROW_POST], 'display' => 'text']);
            $menu = $twig->render('@NowoUiKitBundle/partials/_user_menu.html.twig', ['name' => 'Ann', 'logout_path' => '/logout', 'logout_csrf' => 'lc']);
            $locale = $twig->render('@NowoUiKitBundle/partials/_locale_switcher.html.twig', ['locales' => self::LOCALES]);
            $filters = $twig->render('@NowoUiKitBundle/partials/_filters.html.twig', ['q' => 'abc']);
            $pages = $twig->render('@NowoUiKitBundle/partials/_pagination_compact.html.twig', ['pagination' => ['page' => 1, 'per_page' => 25, 'total' => 60, 'total_pages' => 3], 'route' => 'r', 'per_page_choices' => [25, 50], 'query' => ['q' => 'x', 'f' => ['a' => 1]]]);
            $segmented = $twig->render('@NowoUiKitBundle/partials/_segmented.html.twig', ['name' => 'only', 'checked' => true, 'options' => [['label' => 'A', 'checked' => false], ['label' => 'B', 'checked' => true]]]);
        } finally {
            restore_error_handler();
        }

        self::assertMatchesRegularExpression('#<form\s+method="post"\s+action="/d/1"\s+class="nowo-ui-row-actions__form"\s+onsubmit="return confirm\(&quot;Sure&\#x20;&\#x5C;&quot;x&\#x5C;&quot;&\#x3F;&quot;\);"\s+data-x="1"\s+class="ignored"\s*>\s*<input type="hidden" name="_csrf_token" value="tok">#', $row);
        self::assertMatchesRegularExpression('#<form method="post" action="/logout" class="nowo-ui-user-menu__logout-form">\s*<input type="hidden" name="_csrf_token" value="lc">#', $menu);
        self::assertMatchesRegularExpression('#<form method="post" action="/locale/en" class="nowo-ui-locale-switcher__form">\s*<input type="hidden" name="_token" value="lt">\s*<input type="hidden" name="redirect" value="/back">#', $locale);
        self::assertMatchesRegularExpression('#<form\s+class="[^"]*"\s+method="get"\s+data-nowo-ui-filters\s+role="search"\s*>#', $filters);
        self::assertStringContainsString('value="abc"', $filters);
        self::assertStringContainsString('<input type="hidden" name="q" value="x"><input type="hidden" name="f[a]" value="1">', $pages);
        self::assertStringContainsString('<option value="25" selected>25</option>', $pages);
        self::assertStringContainsString('<input type="checkbox" name="only" value="1" checked data-nowo-ui-segmented-input tabindex="-1">', $segmented);

        self::assertCount(6, $messages);
        foreach (['row_actions', 'user_menu', 'locale_switcher', 'filters', 'pagination_compact', 'segmented'] as $i => $partial) {
            self::assertStringContainsString('Since nowo-tech/ui-kit-bundle 2.19: the "'.$partial.'" partial printed its raw', $messages[$i]);
            self::assertStringContainsString('removed in 3.0', $messages[$i]);
        }
    }

    public function testABareTwigEnvironmentPrintsTheRawMarkupWithoutDeprecation(): void
    {
        $fs = new FilesystemLoader();
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment($fs, ['strict_variables' => false]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));

        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $html = $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [self::ROW_POST]])
                .$twig->render('@NowoUiKitBundle/partials/_user_menu.html.twig', ['logout_path' => '/logout', 'logout_csrf' => 'lc'])
                .$twig->render('@NowoUiKitBundle/partials/_locale_switcher.html.twig', ['locales' => self::LOCALES])
                .$twig->render('@NowoUiKitBundle/partials/_filters.html.twig', []);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $messages);
        self::assertSame(4, substr_count($html, '<form'));
        self::assertStringContainsString('<input type="hidden" name="_csrf_token" value="lc">', $html);
    }

    public function testInvalidFieldNamesFallBackWithADeprecation(): void
    {
        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $row = $this->render('_row_actions', ['actions' => [['kind' => 'delete', 'href' => '/d', 'method' => 'POST', 'csrf_token' => 't', 'csrf_field' => 'token[x]']]]);
            $segmented = $this->render('_segmented', ['name' => 'tags[]', 'checked' => false, 'options' => [['label' => 'A', 'checked' => false], ['label' => 'B', 'checked' => true]]]);
            $pages = PageContractTwig::create()->render('@NowoUiKit/partials/_pagination_compact.html.twig', ['pagination' => ['page' => 1, 'per_page' => 25, 'total' => 60, 'total_pages' => 3], 'route' => 'r', 'per_page_choices' => [25, 50], 'query' => ['filter.status' => 'open']]);
        } finally {
            restore_error_handler();
        }

        self::assertStringContainsString('<input type="hidden" name="token[x]" value="t">', $row);
        self::assertStringContainsString('<input type="checkbox" name="tags[]" value="1"', $segmented);
        self::assertStringContainsString('<input type="hidden" name="filter.status" value="open">', $pages);
        self::assertCount(3, $messages);
        self::assertStringContainsString('"tags[]" is not a form field name', $messages[1]);
    }

    public function testUiFormViewsBuildsEveryKind(): void
    {
        $forms = new UiFormViews(Forms::createFormFactory());
        $twig = PageContractTwig::create();

        $hidden = $forms->create($twig, 'hidden', ['fields' => ['a' => 'x', 'n' => null, 'f' => false, 't' => true, 'i' => 3, 's' => new class implements \Stringable {
            public function __toString(): string
            {
                return 'str';
            }
        }, 'o' => new \stdClass(), 'g' => ['h' => 'y']], 'method' => 'get']);
        self::assertNotNull($hidden);
        self::assertSame(['a', 't', 'i', 's', 'g'], array_keys($hidden->children));
        self::assertSame('1', $hidden->children['t']->vars['value']);
        self::assertSame('g[h]', $hidden->children['g']->children['h']->vars['full_name']);
        self::assertSame('GET', $hidden->vars['method']);

        $search = $forms->create($twig, 'search', ['q_name' => 'term', 'q' => 'z', 'input_id' => 'sid']);
        self::assertSame('sid', $search?->children['term']->vars['id']);

        $perPage = $forms->create($twig, 'per_page', ['choices' => [10, 20], 'per_page' => '20', 'fields' => ['q' => 'x']]);
        self::assertSame('20', $perPage?->children['per_page']->vars['value']);
        self::assertSame('nowo-ui-pagination-per-page', $perPage->children['per_page']->vars['id']);

        $checkbox = $forms->create($twig, 'checkbox', ['name' => 'a[b][c]', 'checked' => true, 'value' => 'on']);
        self::assertSame('a[b][c]', $checkbox?->vars['full_name']);
        self::assertTrue($checkbox->vars['checked']);
        self::assertSame('a_b_c', $checkbox->vars['id']);

        self::assertSame('<input type="hidden" name="a" value="x">', $forms->renderHidden($twig, $hidden->children['a']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown UiKit form kind "nope"');
        $forms->create($twig, 'nope');
    }

    public function testUiFormViewsNeedsTheFormRendererRuntime(): void
    {
        $twig = new Environment(new ArrayLoader([]));
        $twig->addFunction(new TwigFunction('form_start', static fn (): string => ''));
        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            self::assertNull((new UiFormViews())->hidden($twig, ['a' => 'b']));
            self::assertNull((new UiFormViews())->search(new Environment(new ArrayLoader([])), []));
        } finally {
            restore_error_handler();
        }

        self::assertCount(2, $messages);
        self::assertStringContainsString('the "a UiKit partial" partial', $messages[0]);
        self::assertStringContainsString('"filters" partial printed its raw <form> / <input> fallback (symfony/form or its Twig extension (form_start) is missing)', $messages[1]);
    }

    public function testFormTypesDefaults(): void
    {
        $factory = Forms::createFormFactory();

        self::assertSame('nowo_ui_hidden_fields', (new HiddenFieldsType())->getBlockPrefix());
        self::assertSame('nowo_ui_search_filter', (new SearchFilterType())->getBlockPrefix());
        self::assertSame('nowo_ui_per_page', (new PerPageType())->getBlockPrefix());
        self::assertSame(HiddenFieldsType::class, (new PerPageType())->getParent());
        self::assertCount(0, $factory->createNamed('', HiddenFieldsType::class)->createView());

        $search = $factory->createNamed('', SearchFilterType::class)->createView();
        self::assertSame('nowo-ui-filter-q', $search->children['q']->vars['id']);
        self::assertFalse($search->children['q']->vars['required']);

        $perPage = $factory->createNamed('', PerPageType::class, null, ['choices' => [5]])->createView();
        self::assertSame('', $perPage->children['per_page']->vars['value']);
        self::assertNull($perPage->children['per_page']->vars['placeholder']);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render(string $partial, array $vars): string
    {
        return PageContractTwig::create()->render('@NowoUiKitBundle/partials/'.$partial.'.html.twig', $vars);
    }

    private function twigWithoutForms(): Environment
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
}
