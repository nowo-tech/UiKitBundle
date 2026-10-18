<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Test;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Action;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Extra\String\StringExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Shared page contract test (REQ-UI-003 "Contract tests"). A UI bundle extends it once, returns one
 * {@see PageFixture} per page from {@see self::pageFixtures()} and gets seven tests per page, rendered with a real
 * Twig environment (UiKit views + the bundle's views, translator with the bundle catalogues, Symfony Form when
 * installed) against a **stub host layout** and a **stub theme** ({@see StubHost}):
 *
 * 1. the page extends a UiKit scaffold, or is declared unique in the bundle's `docs/TEMPLATES.md` and extends the
 *    layout directly;
 * 2. the stub layout's canonical blocks are all rendered and `nowo_ui_document_title` / `nowo_ui_content` filled;
 * 3. no `<html>` / `<body>`, no CDN `<link>` / `<script>`, no inline `on*=` handlers, no `confirm(`, no
 *    `javascript:` URLs;
 * 4. no framework utility classes outside the theme slots, for every framework of {@see self::cssFrameworks()};
 * 5. the stub theme is reflected: marker class + attribute in the content, page header through the partial;
 * 6. no raw translation keys (missing catalogue ids or `*.label|placeholder|help|title` keys in the output);
 * 7. every rendered form of a FormView-backed {@see Action} (`Action::$form`, e.g. a CSRF-only form) carries each
 *    hidden field of that FormView (the CSRF token) exactly once, however often the page renders the action (the
 *    stub `page_header` override renders the header `actions` itself, after the scaffold pre-rendered `toolbar`).
 *
 * The assertion helpers are protected so a bundle can reuse them in its own tests. PHPUnit is not a runtime
 * dependency of UiKit: bundles need `phpunit/phpunit` (and `symfony/form` for form pages) in `require-dev`.
 */
abstract class PageContractTestCase extends TestCase
{
    /** UiKit page scaffolds (`@NowoUiKit/page/<name>.html.twig`). */
    public const SCAFFOLDS = ['list', 'show', 'form', 'settings', 'dashboard', 'confirm', 'wizard', 'auth'];

    /** Blocks that must have content on every page. */
    public const FILLED_BLOCKS = ['nowo_ui_document_title', 'nowo_ui_content'];

    /** Raw key suffixes that must never reach the output. */
    public const RAW_KEY_PATTERN = '/^[a-z0-9_-]+(\.[a-z0-9_-]+)*\.(label|placeholder|help|title)$/i';

    /** Translation ids that look like keys (dotted, no spaces). */
    public const KEY_PATTERN = '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+$/';

    /**
     * The bundle's pages: name => fixture. Every page template of the bundle should have at least one fixture.
     *
     * @return iterable<string, PageFixture>
     */
    abstract public static function pageFixtures(): iterable;

    /**
     * @return iterable<string, array{0: PageFixture}>
     */
    public static function contractPages(): iterable
    {
        foreach (static::pageFixtures() as $name => $fixture) {
            yield $name => [$fixture];
        }
    }

    #[DataProvider('contractPages')]
    public function testPageExtendsAScaffoldOrIsDeclaredUnique(PageFixture $fixture): void
    {
        $this->assertExtendsScaffoldOrUnique($fixture);
    }

    #[DataProvider('contractPages')]
    public function testPageFillsTheCanonicalBlocks(PageFixture $fixture): void
    {
        $this->assertCanonicalBlocksFilled($this->renderPage($fixture));
    }

    #[DataProvider('contractPages')]
    public function testPageHasNoForbiddenMarkup(PageFixture $fixture): void
    {
        $this->assertNoForbiddenMarkup($this->renderPage($fixture));
    }

    #[DataProvider('contractPages')]
    public function testPageUsesFrameworkClassesOnlyThroughThemeSlots(PageFixture $fixture): void
    {
        foreach (static::cssFrameworks() as $framework) {
            $this->assertNoFrameworkUtilityClasses($this->renderPage($fixture, $framework));
        }
    }

    #[DataProvider('contractPages')]
    public function testPageReflectsTheStubTheme(PageFixture $fixture): void
    {
        $this->assertStubThemeReflected($this->renderPage($fixture));
    }

    #[DataProvider('contractPages')]
    public function testPageHasNoRawTranslationKeys(PageFixture $fixture): void
    {
        $this->assertNoRawTranslationKeys($this->renderPage($fixture));
    }

    #[DataProvider('contractPages')]
    public function testPageActionFormsKeepTheirHiddenFields(PageFixture $fixture): void
    {
        $this->assertActionFormsKeepHiddenFields($this->renderPage($fixture));
    }

    /**
     * Twig namespaces of the bundle views: namespace => path(s). Use {@see FilesystemLoader::MAIN_NAMESPACE} for
     * templates without namespace.
     *
     * @return array<string, string|list<string>>
     */
    protected static function templatePaths(): array
    {
        return [];
    }

    /**
     * In-memory templates (name => source), resolved before the filesystem. A template named like a stub host
     * template ({@see StubHost::templates()}: the stub layouts, the stub `page_header` override) replaces it (2.2).
     *
     * @return array<string, string>
     */
    protected static function inlineTemplates(): array
    {
        return [];
    }

    /**
     * Directories with translation catalogues (`<domain>.<locale>.yaml|yml|xlf`), e.g. the bundle's
     * `src/Resources/translations`. UiKit's own catalogues are always loaded.
     *
     * @return list<string>
     */
    protected static function translationDirs(): array
    {
        return [];
    }

    /**
     * The bundle's `docs/TEMPLATES.md` (unique pages are listed under a "Unique pages" heading), null when the
     * bundle has no unique pages.
     */
    protected static function templatesDocument(): ?string
    {
        return null;
    }

    /**
     * `css_framework` values the utility-class assertion renders with. `none` proves the bundle markup has no
     * framework classes of its own; the others prove UiKit's slot defaults cover what the scaffolds emit.
     *
     * @return list<string>
     */
    protected static function cssFrameworks(): array
    {
        return ['none', 'bootstrap5', 'tailwind'];
    }

    /**
     * Extra framework-looking classes the bundle may emit (document why in `docs/TEMPLATES.md`).
     *
     * @return list<string>
     */
    protected static function allowedClasses(): array
    {
        return [];
    }

    /**
     * Form themes of the Form extension (null disables it). Add the FormKit profile theme when the bundle uses one.
     *
     * @return list<string>|null
     */
    protected static function formThemes(): ?array
    {
        return ['form_div_layout.html.twig'];
    }

    protected static function locale(): string
    {
        return 'en';
    }

    /**
     * Hook to register the bundle's own Twig extensions / functions (functions added here override the stubs of
     * {@see ContractStubExtension}).
     */
    protected function configureTwig(Environment $twig): void
    {
    }

    /**
     * Real Twig environment with UiKit, the bundle's views, the stub host and theme.
     */
    protected function createTwig(string $framework = 'none'): Environment
    {
        return $this->buildEnvironment($framework)[0];
    }

    /**
     * Renders a fixture inside the stub host layout.
     */
    protected function renderPage(PageFixture $fixture, string $framework = 'none'): RenderedPage
    {
        [$twig, $translator] = $this->buildEnvironment($framework);
        $seen = [];
        $actionForms = [];
        self::prepareFormViews($fixture->context, $seen, $actionForms);
        $html = $twig->render($fixture->template, $fixture->context);
        $missing = [];
        foreach ($translator->getCollectedMessages() as $message) {
            if (DataCollectorTranslator::MESSAGE_MISSING === $message['state'] && 1 === preg_match(self::KEY_PATTERN, (string) $message['id'])) {
                $missing[] = $message['domain'].':'.$message['id'];
            }
        }

        return new RenderedPage($fixture->template, $html, $framework, array_values(array_unique($missing)), $actionForms);
    }

    /**
     * The page (or a template it extends) is a UiKit scaffold; otherwise it is listed as unique in
     * `docs/TEMPLATES.md` and extends the stub (or a UiKit) layout.
     */
    protected function assertExtendsScaffoldOrUnique(PageFixture $fixture): void
    {
        $twig = $this->createTwig();
        $chain = $this->inheritanceChain($twig, $fixture);
        $views = __DIR__.'/../Resources/views';
        foreach ($chain as [$name, $path]) {
            foreach (self::SCAFFOLDS as $scaffold) {
                if ($path === realpath($views.'/page/'.$scaffold.'.html.twig')) {
                    $this->addToAssertionCount(1);

                    return;
                }
            }
        }
        $names = array_column($chain, 0);
        self::assertTrue(
            $this->isDeclaredUnique($fixture->template),
            \sprintf('Page "%s" does not extend a UiKit scaffold (@NowoUiKit/page/{%s}.html.twig) and is not listed under "Unique pages" in docs/TEMPLATES.md. Inheritance: %s.', $fixture->template, implode(',', self::SCAFFOLDS), implode(' > ', $names)),
        );
        $layouts = [StubHost::LAYOUT_ADMIN, StubHost::LAYOUT_GUEST];
        $layoutPaths = array_map(static fn (string $l): string|false => realpath($views.'/layout/'.$l.'.html.twig'), ['admin', 'guest', 'document']);
        $onLayout = array_filter($chain, static fn (array $t): bool => \in_array($t[0], $layouts, true) || \in_array($t[1], $layoutPaths, true));
        self::assertNotSame([], $onLayout, \sprintf('Unique page "%s" must extend the UiKit layout (nowo_ui_layout.admin / .guest). Inheritance: %s.', $fixture->template, implode(' > ', $names)));
    }

    /**
     * The page rendered through exactly one stub layout, which rendered every canonical block, and the title /
     * content blocks have content.
     */
    protected function assertCanonicalBlocksFilled(RenderedPage $page): void
    {
        $layouts = $page->layouts();
        self::assertCount(1, $layouts, \sprintf('Page "%s" must render through the host layout (nowo_ui_layout) exactly once; got %d stub layouts (does it override nowo_ui_body or use its own layout?).', $page->template, \count($layouts)));
        $blocks = 'guest' === $layouts[0] ? StubHost::GUEST_BLOCKS : StubHost::ADMIN_BLOCKS;
        foreach ($blocks as $block) {
            self::assertNotNull($page->block($block), \sprintf('Page "%s": canonical block "%s" was not rendered by the host layout.', $page->template, $block));
        }
        foreach (self::FILLED_BLOCKS as $block) {
            self::assertNotSame('', $page->blockHtml($block), \sprintf('Page "%s": canonical block "%s" is empty.', $page->template, $block));
        }
    }

    /**
     * No document skeleton, CDN assets, inline handlers, `confirm(` or `javascript:` URLs.
     */
    protected function assertNoForbiddenMarkup(RenderedPage $page): void
    {
        $rules = [
            '/<(html|body|head)[\s>]|<!doctype/i' => 'a document skeleton (<html>/<head>/<body>/doctype); the host layout owns it',
            '/<script\b[^>]*\bsrc\s*=\s*["\']?(https?:)?\/\//i' => 'a CDN <script>; load assets through the host / asset packages',
            '/<link\b(?=[^>]*\brel\s*=\s*["\']?[^"\'>]*(stylesheet|preload|modulepreload))[^>]*\bhref\s*=\s*["\']?(https?:)?\/\//i' => 'a CDN <link>; load assets through the host / asset packages',
            '/<[a-z][^>]*\son[a-z]+\s*=/i' => 'an inline on* event handler (CSP)',
            '/\bconfirm\s*\(/' => 'a confirm() call; use the UiKit confirm contract (View\Confirm)',
            '/\b(href|src|action|formaction)\s*=\s*["\']?\s*javascript:/i' => 'a javascript: URL (CSP)',
        ];
        foreach ($rules as $pattern => $message) {
            self::assertDoesNotMatchRegularExpression($pattern, $page->html, \sprintf('Page "%s" contains %s.', $page->template, $message));
        }
    }

    /**
     * Framework-looking classes come from theme slots only ({@see FrameworkClasses}).
     */
    protected function assertNoFrameworkUtilityClasses(RenderedPage $page): void
    {
        $violations = FrameworkClasses::violations($page->classes(), $page->framework, static::allowedClasses());
        self::assertSame([], $violations, \sprintf('Page "%s" (css_framework %s) emits framework classes outside the theme slots: %s. Use nowo_ui_class(\'<slot>\') or nowo-ui-* classes.', $page->template, $page->framework, implode(', ', $violations)));
    }

    /**
     * The stub theme reaches the page: a slot marker class and attribute in the content block, and the page header
     * (when rendered) comes from the overridden partial.
     */
    protected function assertStubThemeReflected(RenderedPage $page): void
    {
        $markers = array_filter(
            $page->attributeValues('class', 'nowo_ui_content'),
            static fn (string $class): bool => 1 === preg_match('/(^|\s)pc-[a-z-]+(\s|$)/', $class),
        );
        self::assertNotSame([], $markers, \sprintf('Page "%s": no theme slot class in nowo_ui_content (stub classes pc-<slot> missing); render chrome with nowo_ui_class().', $page->template));
        self::assertNotSame([], $page->attributeNames('data-pc-', 'nowo_ui_content'), \sprintf('Page "%s": no theme slot attributes in nowo_ui_content (stub data-pc-<slot> missing); render chrome with nowo_ui_attrs().', $page->template));
        if ('' !== $page->blockHtml('nowo_ui_page_header')) {
            self::assertNotSame([], $page->attributeValues('data-nowo-ui-contract-partial', 'nowo_ui_page_header'), \sprintf('Page "%s": the page header ignores the theme partial override; include nowo_ui_partial(\'page_header\').', $page->template));
        }
    }

    /**
     * No translation id that looks like a key is missing from the catalogues, and no `*.label|placeholder|help|
     * title` key is printed.
     */
    protected function assertNoRawTranslationKeys(RenderedPage $page): void
    {
        self::assertSame([], $page->missingTranslations, \sprintf('Page "%s": translation keys missing from the catalogues: %s.', $page->template, implode(', ', $page->missingTranslations)));
        $raw = array_values(array_filter($page->texts(), static fn (string $t): bool => 1 === preg_match(self::RAW_KEY_PATTERN, $t)));
        self::assertSame([], $raw, \sprintf('Page "%s" prints raw translation keys: %s.', $page->template, implode(', ', $raw)));
    }

    /**
     * Every rendered `<form name="…">` of a FormView-backed action carries each hidden field of its FormView (the
     * CSRF token) exactly once, with its value ({@see RenderedPage::$actionForms}). Forms the page does not render
     * (invisible actions) are not required.
     */
    protected function assertActionFormsKeepHiddenFields(RenderedPage $page): void
    {
        $this->addToAssertionCount(1);
        foreach ($page->actionForms as $form => $fields) {
            foreach ($page->hiddenFieldValues($form, array_keys($fields)) as $index => $values) {
                foreach ($fields as $field => $value) {
                    self::assertSame([$value], $values[$field], \sprintf('Page "%s": rendered form "%s" #%d of a FormView-backed action must carry its hidden field "%s" exactly once (got %d); render it through the UiKit action partial (nowo_ui_partial(\'action\')), not form_start / form_end of an already rendered FormView.', $page->template, $form, $index + 1, $field, \count($values[$field])));
                }
            }
        }
    }

    /**
     * Unique pages listed under the "Unique pages" heading of {@see self::templatesDocument()}.
     *
     * @return list<string>
     */
    protected static function uniquePages(): array
    {
        $document = static::templatesDocument();
        if (null === $document) {
            return [];
        }
        $markdown = @file_get_contents($document);
        self::assertIsString($markdown, \sprintf('docs/TEMPLATES.md not readable: %s.', $document));

        $pages = [];
        $level = null;
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (1 === preg_match('/^(#{1,6})\s+(.*)$/', $line, $heading)) {
                $depth = \strlen($heading[1]);
                if (1 === preg_match('/\bunique pages\b/i', $heading[2])) {
                    $level = $depth;
                } elseif (null !== $level && $depth <= $level) {
                    $level = null;
                }
                continue;
            }
            if (null !== $level && preg_match_all('/`([^`\s]+\.twig)`/', $line, $matches) > 0) {
                $pages = [...$pages, ...$matches[1]];
            }
        }

        return array_values(array_unique($pages));
    }

    /**
     * Fixtures are rendered several times (one per test and css_framework): mark their FormViews (children and
     * collection prototypes) as not rendered,
     * and collect the hidden leaf fields of the FormViews backing an {@see Action} (form name => field full name =>
     * value) for {@see self::assertActionFormsKeepHiddenFields()}.
     *
     * @param array<int, true>                     $seen        visited objects (spl_object_id)
     * @param array<string, array<string, string>> $actionForms
     */
    private static function prepareFormViews(mixed $value, array &$seen, array &$actionForms): void
    {
        if (\is_array($value)) {
            foreach ($value as $item) {
                self::prepareFormViews($item, $seen, $actionForms);
            }

            return;
        }
        if (!\is_object($value) || isset($seen[spl_object_id($value)])) {
            return;
        }
        $seen[spl_object_id($value)] = true;
        if ($value instanceof FormView) {
            foreach (['rendered', 'methodRendered'] as $property) {
                (new \ReflectionProperty(FormView::class, $property))->setValue($value, false);
            }
            self::prepareFormViews($value->children, $seen, $actionForms);
            // Collection prototypes (CollectionType `prototype`) are FormViews outside `children`
            self::prepareFormViews($value->vars['prototype'] ?? null, $seen, $actionForms);

            return;
        }
        if ($value instanceof Action && $value->form instanceof FormView && '' !== (string) ($value->form->vars['name'] ?? '')) {
            foreach ($value->form->children as $child) {
                if ([] === $child->children && \in_array('hidden', $child->vars['block_prefixes'] ?? [], true) && \is_scalar($child->vars['value'] ?? null) && '' !== (string) $child->vars['value']) {
                    $actionForms[(string) $value->form->vars['name']][(string) $child->vars['full_name']] = (string) $child->vars['value'];
                }
            }
        }
        self::prepareFormViews(get_object_vars($value), $seen, $actionForms);
    }

    private function isDeclaredUnique(string $template): bool
    {
        foreach (static::uniquePages() as $page) {
            if ($page === $template || str_ends_with($template, '/'.ltrim($page, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{0: string, 1: string|false}> template name and real path, child first
     */
    private function inheritanceChain(Environment $twig, PageFixture $fixture): array
    {
        $context = $twig->mergeGlobals($fixture->context);
        $template = $twig->load($fixture->template)->unwrap();
        $chain = [];
        while (false !== $template) {
            $chain[] = [$template->getTemplateName(), realpath($template->getSourceContext()->getPath())];
            $template = $template->getParent($context);
        }

        return $chain;
    }

    /**
     * @return array{0: Environment, 1: DataCollectorTranslator}
     */
    private function buildEnvironment(string $framework): array
    {
        $views = __DIR__.'/../Resources/views';
        $filesystem = new FilesystemLoader();
        $filesystem->addPath($views, 'NowoUiKitBundle');
        $filesystem->addPath($views, 'NowoUiKit');
        foreach (static::templatePaths() as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                $filesystem->addPath($path, $namespace);
            }
        }
        $themes = static::formThemes();
        $withForm = null !== $themes && class_exists(FormRenderer::class);
        if ($withForm) {
            $filesystem->addPath(\dirname((string) (new \ReflectionClass(FormExtension::class))->getFileName(), 2).'/Resources/views/Form');
        }

        $twig = new Environment(
            new ChainLoader([new ArrayLoader(static::inlineTemplates() + StubHost::templates()), $filesystem]),
            ['strict_variables' => true, 'autoescape' => 'html', 'cache' => false],
        );

        $locale = static::locale();
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addLoader('xlf', new XliffFileLoader());
        foreach ([__DIR__.'/../Resources/translations', ...static::translationDirs()] as $dir) {
            foreach ([...(glob($dir.'/*.yaml') ?: []), ...(glob($dir.'/*.yml') ?: []), ...(glob($dir.'/*.xlf') ?: [])] as $file) {
                $parts = explode('.', basename($file));
                if (3 === \count($parts)) {
                    $translator->addResource('xlf' === $parts[2] ? 'xlf' : 'yaml', $file, $parts[1], $parts[0]);
                }
            }
        }
        $collector = new DataCollectorTranslator($translator);
        $twig->addExtension(new TranslationExtension($collector));

        if ($withForm) {
            $engine = new TwigRendererEngine($themes, $twig);
            $twig->addRuntimeLoader(new FactoryRuntimeLoader([FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine)]));
            $twig->addExtension(new FormExtension($collector));
        }
        $twig->addExtension(new StringExtension());

        $request = new Request([], [], ['_route' => 'nowo_ui_contract_page', '_route_params' => []]);
        $request->setLocale($locale);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $twig->addExtension(new ThemeExtension(
            Theme::fromConfig($framework, StubHost::theme()),
            ['admin' => StubHost::LAYOUT_ADMIN, 'guest' => StubHost::LAYOUT_GUEST],
            ['breadcrumb_kit' => false, 'collection' => null],
            $collector,
            $requestStack,
        ));
        $twig->addExtension(new ContractStubExtension());

        $app = new AppVariable();
        $app->setRequestStack($requestStack);
        $app->setEnvironment('test');
        $app->setDebug(false);
        $twig->addGlobal('app', $app);
        $twig->addGlobal('nowo_ui_kit_css_framework', $framework);
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');
        $twig->addGlobal('nowo_ui_kit_row_actions_display', 'icon');
        $twig->addGlobal('nowo_ui_kit_release_check', ['enabled' => false, 'current_version' => '']);

        $this->configureTwig($twig);

        return [$twig, $collector];
    }
}
