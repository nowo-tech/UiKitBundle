<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Nowo\UiKitBundle\Form\ActionCsrfType;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ViewList;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Page contract Twig API (REQ-UI-003):
 *
 * - `nowo_ui_class(slot, bundle = null, framework = null, page = null, defaults = true)` — semantic + framework
 *   default + host classes of a theme slot; `page` (page type) defaults to the `nowo_ui_page_type` variable the
 *   scaffolds set; `defaults: false` (2.3, host-only mode) leaves the UiKit framework defaults out
 * - `nowo_ui_attrs(slot, bundle = null, extra = {}, page = null)` — escaped attribute string of a slot
 * - `nowo_ui_html_attrs(attributes)` — escaped attribute string of a plain map (page model `attributes`)
 * - `nowo_ui_partial(name, bundle = null)` — template of a (possibly host-replaced) UiKit partial
 * - `nowo_ui_script_names()` — the UiKit runtime scripts the `scripts` partial prints (2.2)
 * - `nowo_ui_theme_tokens(nonce = null)` — `<style data-nowo-ui-theme-tokens>` with the host tokens ('' without)
 * - `nowo_ui_tokens(nonce = null)` — every token stylesheet in precedence order: the theme tokens (host defaults),
 *   then the appearance SPI style (user choice, `nowo_ui_appearance_style()`) when the appearance is enabled
 * - `nowo_ui_theme_css()` — the token stylesheet text
 * - `nowo_ui_breadcrumb_kit(collection = null)` — BreadcrumbKit's `breadcrumb_render()` output ('' when absent)
 * - `nowo_ui_breadcrumb_kit_available()` — whether BreadcrumbKit is installed and enabled for page headers
 * - `nowo_ui_page_header(header, bundle = null, extra_actions = [], header_actions = true)` (2.3) — the scaffold page
 *   header (`page/_page_header.html.twig`: the `page_header` partial with the 2.0 and 1.x inputs) for unique pages;
 *   bundle and page type default to `nowo_ui_bundle` / `nowo_ui_page_type` of the context
 * - `nowo_ui_locale_name(locale, display_locale = null)` (2.5) — human name of a locale in the display locale (default
 *   the request locale; ext-intl, else the upper-cased code), `nowo_ui_locale_dir(locale)` — `rtl` / `ltr`
 * - `nowo_ui_action_form(action)` (2.18) — the `FormView` a POST {@see Action} renders through `form_start` /
 *   `form_end` (REQ-TWIG-005): its `form`, else a nameless CSRF-only form built from `csrfToken` / `csrfField`
 *   ({@see ActionCsrfType}); null (deprecated raw fallback) without symfony/form and its Twig extension
 * - `|nowo_ui_trans(params = {})` — translates a {@see Label}; strings pass through (escaped by Twig)
 * - `|nowo_ui_translatable(params = {})` (2.3) — a {@see TranslatableText} for values a form theme translates again
 *   (`attr.placeholder`, choice `placeholder`): a Label is translated exactly once, a string printed as given
 * - globals `nowo_ui_layout` ({admin, guest}), `nowo_ui_theme` ({@see Theme}), `nowo_ui_breadcrumbs`
 */
final class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    /** Public page-header contract (2.3): what the scaffolds render in `nowo_ui_page_header`. */
    public const PAGE_HEADER_TEMPLATE = '@NowoUiKitBundle/page/_page_header.html.twig';

    /**
     * @param array{admin: string, guest: string}                                  $layouts
     * @param array{breadcrumb_kit: bool, collection: string|null, enabled?: bool} $breadcrumbs `enabled` (2.3.1, default true): false = no breadcrumb trail in page headers
     * @param FormFactoryInterface|null                                            $actionForms standalone factory of the CSRF-only action forms (2.18; core types only, so no host type extension changes them); null = a new one per call
     */
    public function __construct(
        private readonly Theme $theme,
        private readonly array $layouts = ['admin' => '@NowoUiKit/layout/admin.html.twig', 'guest' => '@NowoUiKit/layout/guest.html.twig'],
        private readonly array $breadcrumbs = ['breadcrumb_kit' => true, 'collection' => null],
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly LocaleNames $localeNames = new LocaleNames(),
        private readonly ?FormFactoryInterface $actionForms = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_ui_class', $this->slotClass(...), ['needs_context' => true]),
            new TwigFunction('nowo_ui_attrs', $this->slotAttributes(...), ['needs_context' => true, 'is_safe' => ['html']]),
            new TwigFunction('nowo_ui_html_attrs', $this->theme->renderAttributeMap(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_partial', $this->theme->partial(...)),
            new TwigFunction('nowo_ui_script_names', static fn (): array => ThemeSlots::SCRIPTS),
            new TwigFunction('nowo_ui_theme_tokens', $this->tokens(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_theme_css', $this->theme->tokensCss(...)),
            new TwigFunction('nowo_ui_tokens', $this->allTokens(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('nowo_ui_breadcrumb_kit', $this->breadcrumbKit(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('nowo_ui_breadcrumb_kit_available', $this->breadcrumbKitAvailable(...), ['needs_environment' => true]),
            new TwigFunction('nowo_ui_locale_name', fn (string $locale, ?string $displayLocale = null): string => $this->localeNames->name($locale, $displayLocale ?? $this->requestStack?->getCurrentRequest()?->getLocale())),
            new TwigFunction('nowo_ui_locale_dir', $this->localeNames->dir(...)),
            new TwigFunction('nowo_ui_action_form', $this->actionForm(...), ['needs_environment' => true]),
            new TwigFunction('nowo_ui_page_header', $this->pageHeader(...), ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('nowo_ui_trans', $this->trans(...)),
            new TwigFilter('nowo_ui_translatable', $this->translatable(...)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        return [
            'nowo_ui_layout' => $this->layouts,
            'nowo_ui_theme' => $this->theme,
            'nowo_ui_breadcrumbs' => $this->breadcrumbs,
        ];
    }

    /**
     * `nowo_ui_class()`: the page type defaults to `nowo_ui_page_type` from the template context; `defaults: false`
     * (2.3) drops the UiKit framework defaults (semantic + host / bundle classes only).
     *
     * @param array<string, mixed> $context
     */
    public function slotClass(array $context, string $slot, ?string $bundle = null, ?string $framework = null, ?string $page = null, bool $defaults = true): string
    {
        return $this->theme->classFor($slot, $bundle, $framework, $page ?? $this->pageType($context), $defaults);
    }

    /**
     * `nowo_ui_attrs()`: the page type defaults to `nowo_ui_page_type` from the template context.
     *
     * @param array<string, mixed>                      $context
     * @param array<string, string|int|float|bool|null> $extra
     */
    public function slotAttributes(array $context, string $slot, ?string $bundle = null, array $extra = [], ?string $page = null): string
    {
        return $this->theme->renderAttributes($slot, $bundle, $extra, $page ?? $this->pageType($context));
    }

    /**
     * `<style>` with the host tokens; the nonce defaults to the request attribute `csp_nonce`.
     */
    public function tokens(?string $nonce = null): string
    {
        $css = $this->theme->tokensCss();
        if ('' === $css) {
            return '';
        }
        $nonce ??= $this->requestNonce();
        $attr = null !== $nonce && '' !== $nonce ? ' nonce="'.htmlspecialchars($nonce, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"' : '';

        return '<style'.$attr.' data-nowo-ui-theme-tokens>'."\n".$css."\n".'</style>';
    }

    /**
     * Theme tokens, then the appearance style (`nowo_ui_appearance_style()`, registered with
     * `nowo_ui_kit.appearance.enabled`): both set `--nowo-ui-*` with the same specificity, so the appearance
     * (user-chosen) overrides the theme tokens (host defaults).
     */
    public function allTokens(Environment $env, ?string $nonce = null): string
    {
        $parts = [$this->tokens($nonce)];
        $callable = $env->getFunction('nowo_ui_appearance_style')?->getCallable();
        if (\is_callable($callable)) {
            $style = $callable($nonce);
            $parts[] = \is_scalar($style) || $style instanceof \Stringable ? (string) $style : '';
        }

        return implode("\n", array_filter($parts, static fn (string $part): bool => '' !== $part));
    }

    /**
     * Translates a {@see Label} (or a `{key, domain, params}` hash); other values are returned as strings.
     *
     * @param array<string, mixed> $params merged over the label's own parameters
     */
    public function trans(mixed $value, array $params = []): string
    {
        if (\is_array($value) && isset($value['key']) && \is_string($value['key'])) {
            $domain = $value['domain'] ?? 'messages';
            $labelParams = $value['params'] ?? [];
            $value = new Label($value['key'], \is_string($domain) ? $domain : 'messages', \is_array($labelParams) ? $labelParams : []);
        }
        if ($value instanceof Label) {
            $all = array_merge($value->params, $params);

            return $this->translator?->trans($value->key, $all, $value->domain) ?? strtr($value->key, array_map(static fn (mixed $v): string => \is_scalar($v) || $v instanceof \Stringable ? (string) $v : '', $all));
        }
        if (null === $value || \is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        throw new \InvalidArgumentException(\sprintf('nowo_ui_trans expects a Label, a string or a number, %s given.', get_debug_type($value)));
    }

    /**
     * Wraps a {@see Label} (or a `{key, domain, params}` hash) or a literal for a form theme that pipes the value
     * through `|trans` (`form_row(child, {attr: {placeholder: x|nowo_ui_translatable}})`): the label is translated
     * once in its own domain, a literal is printed as given. Render with `translation_domain: false` instead when
     * the value is already translated.
     *
     * @param array<string, mixed> $params merged over the label's own parameters
     */
    public function translatable(mixed $value, array $params = []): TranslatableText
    {
        if ($value instanceof TranslatableText) {
            return [] === $params ? $value : new TranslatableText($value->text, $value->domain, array_merge($value->params, $params));
        }
        if (\is_array($value) && isset($value['key']) && \is_string($value['key'])) {
            $domain = $value['domain'] ?? 'messages';
            $labelParams = $value['params'] ?? [];
            $value = new Label($value['key'], \is_string($domain) ? $domain : 'messages', \is_array($labelParams) ? $labelParams : []);
        }
        if ($value instanceof Label) {
            return new TranslatableText($value->key, $value->domain, array_merge($value->params, $params));
        }

        return new TranslatableText($this->trans($value), null, $params);
    }

    /**
     * `nowo_ui_page_header()`: renders `page/_page_header.html.twig` (the scaffolds' page header) for a unique page.
     *
     * @param array<string, mixed> $context
     * @param iterable<Action>     $extraActions rendered after the header actions
     */
    public function pageHeader(Environment $env, array $context, PageHeader $header, ?string $bundle = null, iterable $extraActions = [], bool $headerActions = true): string
    {
        $contextBundle = $context['nowo_ui_bundle'] ?? null;

        return $env->render(self::PAGE_HEADER_TEMPLATE, [
            'header' => $header,
            'bundle' => $bundle ?? (\is_string($contextBundle) && '' !== $contextBundle ? $contextBundle : null),
            'nowo_ui_page_type' => $this->pageType($context),
            'extra_actions' => ViewList::of($extraActions, Action::class, 'nowo_ui_page_header() extra actions'),
            'header_actions' => $headerActions,
        ]);
    }

    /**
     * `nowo_ui_action_form()` (2.18): the form a POST action renders through `form_start` / `form_end` — its own
     * `form`, else a nameless CSRF-only view of `csrfToken` in `csrfField` ({@see ActionCsrfType}). Null for a GET
     * action; null too (with a deprecation: the partial then prints the 2.17 raw `<form>`, removed in 3.0) when
     * symfony/form or the Twig `form_start` function is missing, or `csrfField` is not a valid form field name.
     */
    public function actionForm(Environment $env, Action $action): ?FormView
    {
        if ($action->form instanceof FormView) {
            return $action->form;
        }
        if (!$action->isPost()) {
            return null;
        }
        $view = null;
        if (class_exists(Forms::class) && $env->getFunction('form_start') instanceof TwigFunction) {
            $view = ActionCsrfType::createActionView($this->actionForms ?? Forms::createFormFactory(), $action);
        }
        if (!$view instanceof FormView) {
            @trigger_error(\sprintf('Since nowo-tech/ui-kit-bundle 2.18: rendering a POST %s without a "form" needs symfony/form, its Twig extension (form_start) and a valid form field name as "csrfField" (got "%s"); the raw <form> fallback is deprecated and will be removed in 3.0. Install symfony/form or pass a CSRF-only FormView as "form".', Action::class, $action->csrfField), \E_USER_DEPRECATED);
        }

        return $view;
    }

    public function breadcrumbKitAvailable(Environment $env): bool
    {
        return $this->breadcrumbs['breadcrumb_kit'] && $env->getFunction('breadcrumb_render') instanceof TwigFunction;
    }

    public function breadcrumbKit(Environment $env, ?string $collection = null): string
    {
        if (!$this->breadcrumbKitAvailable($env)) {
            return '';
        }
        $callable = $env->getFunction('breadcrumb_render')?->getCallable();
        if (!\is_callable($callable)) {
            return '';
        }

        $html = $callable($env, $collection ?? $this->breadcrumbs['collection']);

        return \is_scalar($html) || $html instanceof \Stringable ? (string) $html : '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function pageType(array $context): ?string
    {
        $page = $context['nowo_ui_page_type'] ?? null;

        return \is_string($page) && '' !== $page ? $page : null;
    }

    private function requestNonce(): ?string
    {
        $nonce = $this->requestStack?->getCurrentRequest()?->attributes->get('csp_nonce');

        return \is_string($nonce) ? $nonce : null;
    }
}
