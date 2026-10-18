<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

/**
 * Real Twig environment for the page contract render tests: UiKit views under @NowoUiKitBundle and @NowoUiKit,
 * the real translator with UiKit catalogues, the Form extension (form_div_layout), the ThemeExtension, stub
 * `path()` / `asset()`.
 */
final class PageContractTwig
{
    public static function root(): string
    {
        return \dirname(__DIR__, 3);
    }

    /**
     * @param array<string, string>                 $templates extra in-memory templates
     * @param array<string, mixed>                  $theme     processed nowo_ui_kit.theme config
     * @param array{admin?: string, guest?: string} $layouts
     */
    public static function create(
        array $templates = [],
        array $theme = [],
        string $framework = 'bootstrap5',
        array $layouts = [],
        ?Request $request = null,
        bool $breadcrumbKit = true,
        string $locale = 'en',
        bool $breadcrumbs = true,
    ): Environment {
        $views = self::root().'/src/Resources/views';
        $fs = new FilesystemLoader();
        $fs->addPath($views, 'NowoUiKitBundle');
        $fs->addPath($views, 'NowoUiKit');
        $formViews = \dirname((string) (new \ReflectionClass(FormExtension::class))->getFileName(), 2).'/Resources/views/Form';
        $fs->addPath($formViews);

        $twig = new Environment(new ChainLoader([new ArrayLoader($templates), $fs]), ['strict_variables' => true, 'autoescape' => 'html']);

        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (glob(self::root().'/src/Resources/translations/NowoUiKitBundle.*.yaml') ?: [] as $file) {
            $translator->addResource('yaml', $file, explode('.', basename($file))[1], 'NowoUiKitBundle');
        }
        $twig->addExtension(new TranslationExtension($translator));

        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine)]));
        $twig->addExtension(new FormExtension());

        $requestStack = new RequestStack();
        $request ??= new Request([], [], ['_route' => 'items_index', '_route_params' => []]);
        $requestStack->push($request);

        $twig->addExtension(new ThemeExtension(
            Theme::fromConfig($framework, $theme),
            $layouts + ['admin' => '@NowoUiKit/layout/admin.html.twig', 'guest' => '@NowoUiKit/layout/guest.html.twig'],
            ['breadcrumb_kit' => $breadcrumbKit, 'collection' => null, 'enabled' => $breadcrumbs],
            $translator,
            $requestStack,
        ));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/bundles/nowouikit/'.$path));
        $twig->addFunction(new TwigFunction('path', static fn (string $name, array $params = []): string => '/'.$name.([] === $params ? '' : '?'.http_build_query($params))));
        $twig->addGlobal('nowo_ui_kit_css_framework', $framework);
        $twig->addGlobal('nowo_ui_kit_icon_set', 'bootstrap-icons');
        $twig->addGlobal('nowo_ui_kit_row_actions_display', 'icon');
        $twig->addGlobal('app', new class($request) {
            public function __construct(public readonly Request $request)
            {
            }

            /**
             * @return array<string, list<mixed>>
             */
            public function getFlashes(): array
            {
                return ['success' => ['Saved.']];
            }
        });

        return $twig;
    }

    public static function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->getFormFactory();
    }
}
