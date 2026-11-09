<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use InvalidArgumentException;
use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearanceView;
use Nowo\UiKitBundle\Dialog\RemoteDialog;
use Nowo\UiKitBundle\Dialog\RemoteDialogHeaders;
use Nowo\UiKitBundle\Form\ActionCsrfType;
use Nowo\UiKitBundle\Form\ImageCrop;
use Nowo\UiKitBundle\Form\UiFormViews;
use Nowo\UiKitBundle\Form\VisibleWhen;
use Nowo\UiKitBundle\Shell\HeaderCrumbs;
use Nowo\UiKitBundle\Shell\HeaderPresentation;
use Nowo\UiKitBundle\Shell\HeaderPresentationResolver;
use Nowo\UiKitBundle\Shell\SafeHref;
use Nowo\UiKitBundle\Shell\SidebarLayoutResolver;
use Nowo\UiKitBundle\Theme\FontPreload;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\HeaderActions;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\SafeAttributes;
use Nowo\UiKitBundle\View\ViewList;
use Stringable;
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
 * - `nowo_ui_safe_attrs(attributes, reserved = [])` (2.34) — the checked map of a per-element option
 *   ({@see SafeAttributes}: no on*, `style`, `srcdoc` or unsafe URL schemes), `class` kept for the caller to merge;
 *   `nowo_ui_attrs_without_class(attributes)` is that map without `class`
 * - `nowo_ui_partial(name, bundle = null)` — template of a (possibly host-replaced) UiKit partial
 * - `nowo_ui_script_names()` — the UiKit runtime scripts the `scripts` partial prints (2.2)
 * - `nowo_ui_theme_tokens(nonce = null)` — `<style data-nowo-ui-theme-tokens>` with the host tokens ('' without)
 * - `nowo_ui_tokens(nonce = null)` — every token stylesheet in precedence order: the theme tokens (host defaults),
 *   then the appearance SPI style (user choice, `nowo_ui_appearance_style()`) when the appearance is enabled
 * - `nowo_ui_theme_css()` — the token stylesheet text
 * - `nowo_ui_breadcrumb_kit(collection = null)` — BreadcrumbKit's `breadcrumb_render()` output ('' when absent)
 * - `nowo_ui_breadcrumb_kit_available()` — whether BreadcrumbKit is installed and enabled for page headers
 * - `nowo_ui_page_header(header, bundle = null, extra_actions = [], header_actions = true, presentation = null)` (2.3)
 *   — the scaffold page header (`page/_page_header.html.twig`: the `page_header` partial with the 2.0 and 1.x inputs)
 *   for unique pages; bundle and page type default to `nowo_ui_bundle` / `nowo_ui_page_type` of the context. 2.27: the
 *   second argument may be an options hash instead (`{bundle, extra_actions, header_actions, presentation}`), and
 *   `presentation` (a {@see HeaderPresentation} value; anything else is ignored) defaults to {@see self::headerPresentation()}
 * - `nowo_ui_header_presentation(presentation = null)` (2.27) — the resolved page-header presentation of the request
 *   ({@see HeaderPresentation}: a valid option, else the request attribute, else `nowo_ui_kit.header.presentation`)
 * - `nowo_ui_sidebar_layout(layout = null)` (2.32) — the app shell's sidebar layout of the request (`below-header` |
 *   `full-height`: a valid option, else the request attribute `_nowo_ui_sidebar_layout`, else
 *   `nowo_ui_kit.shell.sidebar_layout`)
 * - `nowo_ui_safe_image_src(src)` (2.32) — the image source when it is relative, http or https ({@see SafeHref::isSafeImage()}),
 *   else null
 * - `nowo_ui_appearance_or_null()` (2.40) — the appearance SPI's {@see Appearance} (its `logos`
 *   drive the shell, guest, auth and error brand), or null while the appearance is disabled or its provider throws
 * - `nowo_ui_appearance_head()` (2.40) — favicon / apple-touch links and `meta theme-color` of the appearance ('' without
 *   one); `nowo_ui_appearance_head_links()` — whether the kit layouts print it (`appearance.head_links`)
 * - `nowo_ui_safe_href(href)` (2.27) — the href when its scheme is safe ({@see SafeHref}: relative, http, https,
 *   mailto), else null
 * - `nowo_ui_breadcrumb_kit_trail(collection = null, context_key = '')` (2.27) — BreadcrumbKit's `breadcrumb_trail()`
 *   mapped to `list<View\Crumb>` ([] without BreadcrumbKit); unlike `nowo_ui_breadcrumb_kit()` it ignores
 *   `nowo_ui_kit.breadcrumbs.breadcrumb_kit` (calling it is the opt-in)
 * - `nowo_ui_header_crumbs(crumbs = null, header = null, options = {})` (2.27) — the {@see HeaderCrumbs} model the
 *   `header_crumbs` partial renders (options: home_href, drop_home_crumb, max_visible)
 * - `nowo_ui_locale_name(locale, display_locale = null)` (2.5) — human name of a locale in the display locale (default
 *   the request locale; ext-intl, else the upper-cased code), `nowo_ui_locale_dir(locale)` — `rtl` / `ltr`
 * - `nowo_ui_action_form(action)` (2.18) — the `FormView` a POST {@see Action} renders through `form_start` /
 *   `form_end` (REQ-TWIG-005): its `form`, else a nameless CSRF-only form built from `csrfToken` / `csrfField`
 *   ({@see ActionCsrfType}); null (deprecated raw fallback) without symfony/form and its Twig extension
 * - `nowo_ui_form(kind, options)` (2.19) — the form view (`hidden`, `search`, `per_page`, `checkbox`) of a partial
 *   that used to print a raw `<form>` / `<input>` ({@see UiFormViews}; null = deprecated raw fallback), called by
 *   `partials/_ui_form.html.twig`; `nowo_ui_form_hidden(field)` prints one hidden field whatever its rendered flag
 *   (shared action FormViews)
 * - `nowo_ui_header_actions(actions)` (2.21) — the {@see HeaderActions} split of a list of {@see Action}s (icon
 *   buttons, one primary, the "more" menu) the `header_actions` partial renders
 * - `nowo_ui_visible_when(field, values)` (2.23) — row attributes of the `visible-when` peer
 *   ({@see VisibleWhen::attributes()}; merge into `row_attr`, or print with `nowo_ui_html_attrs`)
 * - `nowo_ui_image_crop_attrs(options)` (2.43) — attributes of a file input with the image crop step ({@see ImageCrop})
 * - `nowo_ui_font_preload()` (2.21) — `<link rel="preload" as="font" crossorigin>` tags of `nowo_ui_kit.font_preload`
 *   ({@see FontPreload}; '' when not configured), printed by the document and error layouts
 * - `|nowo_ui_trans(params = {})` — translates a {@see Label}; strings pass through (escaped by Twig)
 * - `|nowo_ui_translatable(params = {})` (2.3) — a {@see TranslatableText} for values a form theme translates again
 *   (`attr.placeholder`, choice `placeholder`): a Label is translated exactly once, a string printed as given
 * - globals `nowo_ui_layout` ({admin, guest}), `nowo_ui_theme` ({@see Theme}), `nowo_ui_breadcrumbs`,
 *   `nowo_ui_header` (2.27, the {@see HeaderPresentationResolver}: `resolve(option)`, `isCompact(option)`,
 *   `compactActions()` — partials read it with `|default(null)`, so a Twig environment without this extension still
 *   renders them in the standard presentation)
 */
final class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    private readonly UiFormViews $uiForms;

    private readonly HeaderPresentationResolver $presentationResolver;

    private readonly SidebarLayoutResolver $sidebarLayoutResolver;

    /** Public page-header contract (2.3): what the scaffolds render in `nowo_ui_page_header`. */
    public const PAGE_HEADER_TEMPLATE = '@NowoUiKitBundle/page/_page_header.html.twig';

    /**
     * @param array{admin: string, guest: string}                                  $layouts
     * @param array{breadcrumb_kit: bool, collection: string|null, enabled?: bool} $breadcrumbs   `enabled` (2.3.1, default true): false = no breadcrumb trail in page headers
     * @param FormFactoryInterface|null                                            $actionForms   standalone factory of the CSRF-only action forms (2.18; core types only, so no host type extension changes them); null = a new one per call
     * @param FontPreload|null                                                     $fontPreload   font preload tags (2.21, `nowo_ui_kit.font_preload`); null = none
     * @param array{presentation: string, compact_actions: string}                 $header        2.27 `nowo_ui_kit.header`: default presentation, where `header.actions` go in compact (`page` | `bar`)
     * @param string                                                               $sidebarLayout 2.32 `nowo_ui_kit.shell.sidebar_layout`: `below-header` | `full-height`
     */
    public function __construct(
        private readonly Theme $theme,
        private readonly array $layouts = ['admin' => '@NowoUiKit/layout/admin.html.twig', 'guest' => '@NowoUiKit/layout/guest.html.twig'],
        private readonly array $breadcrumbs = ['breadcrumb_kit' => true, 'collection' => null],
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly LocaleNames $localeNames = new LocaleNames(),
        private readonly ?FormFactoryInterface $actionForms = null,
        private readonly ?FontPreload $fontPreload = null,
        array $header = ['presentation' => 'standard', 'compact_actions' => 'page'],
        string $sidebarLayout = 'below-header',
        private readonly ?RemoteDialogHeaders $remoteDialogHeaders = null,
        private readonly AppearanceView $appearanceView = new AppearanceView(),
    ) {
        $this->uiForms = new UiFormViews($actionForms);
        $this->presentationResolver = new HeaderPresentationResolver($requestStack, $header['presentation'], $header['compact_actions']);
        $this->sidebarLayoutResolver = new SidebarLayoutResolver($requestStack, $sidebarLayout);
    }

    /**
     * `nowo_ui_image_crop_attrs(options)` (2.43): the attributes of a file input with the image crop step, labels translated.
     *
     * @param array<mixed> $options {@see ImageCrop} options
     *
     * @return array<string, string>
     */
    public function imageCropAttributes(array $options = []): array
    {
        return ImageCrop::attributes($options, $this->translator);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_ui_class', $this->slotClass(...), ['needs_context' => true]),
            new TwigFunction('nowo_ui_attrs', $this->slotAttributes(...), ['needs_context' => true, 'is_safe' => ['html']]),
            new TwigFunction('nowo_ui_html_attrs', $this->theme->renderAttributeMap(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_safe_attrs', SafeAttributes::of(...)),
            new TwigFunction('nowo_ui_attrs_without_class', SafeAttributes::withoutClass(...)),
            new TwigFunction('nowo_ui_partial', $this->theme->partial(...)),
            new TwigFunction('nowo_ui_script_names', static fn (): array => ThemeSlots::SCRIPTS),
            new TwigFunction('nowo_ui_progress_amount', self::progressAmount(...)),
            new TwigFunction('nowo_ui_tree_has_current', self::treeHasCurrent(...)),
            new TwigFunction('nowo_ui_tree_group_id', self::treeGroupId(...)),
            new TwigFunction('nowo_ui_theme_tokens', $this->tokens(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_theme_css', $this->theme->tokensCss(...)),
            new TwigFunction('nowo_ui_tokens', $this->allTokens(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('nowo_ui_breadcrumb_kit', $this->breadcrumbKit(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('nowo_ui_breadcrumb_kit_available', $this->breadcrumbKitAvailable(...), ['needs_environment' => true]),
            new TwigFunction('nowo_ui_locale_name', fn (string $locale, ?string $displayLocale = null): string => $this->localeNames->name($locale, $displayLocale ?? $this->requestStack?->getCurrentRequest()?->getLocale())),
            new TwigFunction('nowo_ui_locale_dir', $this->localeNames->dir(...)),
            new TwigFunction('nowo_ui_action_form', $this->actionForm(...), ['needs_environment' => true]),
            new TwigFunction('nowo_ui_form', $this->uiForms->create(...), ['needs_environment' => true]),
            new TwigFunction('nowo_ui_form_hidden', $this->uiForms->renderHidden(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('nowo_ui_page_header', $this->pageHeader(...), ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']]),
            new TwigFunction('nowo_ui_header_actions', HeaderActions::split(...)),
            new TwigFunction('nowo_ui_visible_when', VisibleWhen::attributes(...)),
            new TwigFunction('nowo_ui_image_crop_attrs', $this->imageCropAttributes(...)),
            new TwigFunction('nowo_ui_font_preload', fn (): string => $this->fontPreload?->tags() ?? '', ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_header_presentation', $this->headerPresentation(...)),
            new TwigFunction('nowo_ui_sidebar_layout', $this->sidebarLayout(...)),
            new TwigFunction('nowo_ui_breadcrumb_kit_trail', $this->breadcrumbKitTrail(...), ['needs_environment' => true]),
            new TwigFunction('nowo_ui_header_crumbs', $this->headerCrumbs(...), ['needs_environment' => true]),
            new TwigFunction('nowo_ui_safe_href', static fn (mixed $href): ?string => \is_string($href) ? SafeHref::filter($href) : null),
            new TwigFunction('nowo_ui_safe_image_src', static fn (mixed $src): ?string => \is_string($src) ? SafeHref::filterImage($src) : null),
            new TwigFunction('nowo_ui_dialog_request', fn (): bool => RemoteDialog::isRequest($this->requestStack?->getCurrentRequest(), $this->remoteDialogHeaders)),
            // 2.33: the dialog attributes of configured (non-default) header names; empty with the defaults.
            new TwigFunction('nowo_ui_remote_dialog_attrs', fn (): array => ($this->remoteDialogHeaders ?? RemoteDialogHeaders::defaults())->dialogAttributes()),
            new TwigFunction('nowo_ui_remote_form_opener', self::remoteFormOpener(...)),
            new TwigFunction('nowo_ui_dialog_status', PageStatus::fromOption(...)),
            // 2.40 (spec 220 S1): registered with the appearance on or off; never throw.
            new TwigFunction('nowo_ui_appearance_or_null', $this->appearanceView->current(...)),
            new TwigFunction('nowo_ui_appearance_head', $this->appearanceView->head(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_appearance_head_links', $this->appearanceView->headLinks(...)),
        ];
    }

    /**
     * The `progress` partial's absolute amount (2.34): the value clamped to 0..max and the rounded percentage; null
     * unless both are finite numbers and max is above 0 (the meter stays indeterminate).
     *
     * @return array{value: int|float, max: int|float, percent: int}|null
     */
    public static function progressAmount(mixed $value, mixed $max): ?array
    {
        if (!is_numeric($value) || !is_numeric($max) || !is_finite((float) $value) || !is_finite((float) $max) || (float) $max <= 0.0) {
            return null;
        }
        $value = max(0, min(0 + $max, 0 + $value));

        return ['value' => $value, 'max' => 0 + $max, 'percent' => (int) round($value / (0 + $max) * 100)];
    }

    /**
     * Attributes of a remote form dialog opener (2.28), for `nowo_ui_html_attrs()`: the Stimulus peer's action and
     * title, or the IIFE's `data-nowo-ui-dialog-open` / `-src` / `-form` / `-title`. `src` is the opener's own href
     * (the full page) for the IIFE; Stimulus reads the href itself.
     *
     * @return array<string, string|bool>
     */
    public static function remoteFormOpener(string $dialogId, ?string $title = null, bool $stimulus = false, ?string $src = null): array
    {
        if ($stimulus) {
            return array_filter(['data-action' => 'remote-form-dialog#open', 'data-remote-form-dialog-title' => $title], static fn (?string $v): bool => null !== $v);
        }

        return array_filter([
            'data-nowo-ui-dialog-open' => $dialogId,
            'data-nowo-ui-dialog-src' => $src,
            'data-nowo-ui-dialog-form' => true,
            'data-nowo-ui-dialog-title' => $title,
        ], static fn (string|bool|null $v): bool => null !== $v);
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
            'nowo_ui_header' => $this->presentationResolver,
        ];
    }

    /**
     * Id of a node's child group in a `tree` (2.24): unique per tree (the tree id prefixes it, so two trees with the same
     * node ids on one page never share an aria-owns target), with anything outside `[A-Za-z0-9_-]` replaced.
     */
    public static function treeGroupId(string $treeId, string|int $nodeId): string
    {
        return $treeId.'-'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $nodeId).'-group';
    }

    /**
     * Whether any node of a `tree` (at any depth) is the current page (2.24): otherwise the first item takes the tab stop.
     *
     * @param iterable<mixed> $nodes Tree nodes ({current?, children?})
     */
    public static function treeHasCurrent(iterable $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!\is_array($node)) {
                continue;
            }
            if (($node['current'] ?? false) === true || (is_iterable($node['children'] ?? null) && self::treeHasCurrent($node['children']))) {
                return true;
            }
        }

        return false;
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
            $parts[] = \is_scalar($style) || $style instanceof Stringable ? (string) $style : '';
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

            return $this->translator?->trans($value->key, $all, $value->domain) ?? strtr($value->key, array_map(static fn (mixed $v): string => \is_scalar($v) || $v instanceof Stringable ? (string) $v : '', $all));
        }
        if (null === $value || \is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(\sprintf('nowo_ui_trans expects a Label, a string or a number, %s given.', get_debug_type($value)));
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
     * 2.27: `$bundle` may be an options hash (`bundle`, `extra_actions`, `header_actions`, `presentation`) instead of
     * the positional arguments; `presentation` null resolves through {@see self::headerPresentation()}.
     *
     * @param array<string, mixed>             $context
     * @param string|array<string, mixed>|null $bundle       bundle name, or the options hash (2.27)
     * @param iterable<Action>                 $extraActions rendered after the header actions
     */
    public function pageHeader(Environment $env, array $context, PageHeader $header, string|array|null $bundle = null, iterable $extraActions = [], bool $headerActions = true, mixed $presentation = null): string
    {
        if (\is_array($bundle)) {
            $options = $bundle;
            $bundle = \is_string($options['bundle'] ?? null) ? $options['bundle'] : null;
            $extraActions = is_iterable($options['extra_actions'] ?? null) ? $options['extra_actions'] : $extraActions;
            $headerActions = \is_bool($options['header_actions'] ?? null) ? $options['header_actions'] : $headerActions;
            $presentation = $options['presentation'] ?? $presentation;
        }
        $contextBundle = $context['nowo_ui_bundle'] ?? null;

        return $env->render(self::PAGE_HEADER_TEMPLATE, [
            'header' => $header,
            'bundle' => $bundle ?? (\is_string($contextBundle) && '' !== $contextBundle ? $contextBundle : null),
            'nowo_ui_page_type' => $this->pageType($context),
            'extra_actions' => ViewList::of($extraActions, Action::class, 'nowo_ui_page_header() extra actions'),
            'header_actions' => $headerActions,
            'presentation' => $this->headerPresentation($presentation),
        ]);
    }

    /**
     * `nowo_ui_header_presentation()` (2.27): a valid explicit option, else the request attribute
     * {@see HeaderPresentation::ATTRIBUTE} (current, then main request), else `nowo_ui_kit.header.presentation`. Invalid
     * values are ignored ({@see HeaderPresentationResolver::resolve()}).
     */
    public function headerPresentation(mixed $presentation = null): string
    {
        return $this->presentationResolver->resolve($presentation);
    }

    /**
     * `nowo_ui_sidebar_layout(layout = null)` (2.32): the app shell's sidebar layout of the request
     * ({@see SidebarLayout}: a valid `layout`, else the request attribute `_nowo_ui_sidebar_layout`, else
     * `nowo_ui_kit.shell.sidebar_layout`): `below-header` | `full-height`.
     */
    public function sidebarLayout(mixed $layout = null): string
    {
        return $this->sidebarLayoutResolver->resolve($layout);
    }

    /**
     * `nowo_ui_breadcrumb_kit_trail()` (2.27): BreadcrumbKit's `breadcrumb_trail()` as {@see Crumb}s — label as given
     * (BreadcrumbKit localises it), href only for a linked level. [] when BreadcrumbKit is not installed. The
     * collection defaults to `nowo_ui_kit.breadcrumbs.collection`.
     *
     * @return list<Crumb>
     */
    public function breadcrumbKitTrail(Environment $env, ?string $collection = null, string $contextKey = ''): array
    {
        $callable = $env->getFunction('breadcrumb_trail')?->getCallable();
        if (!\is_callable($callable)) {
            return [];
        }
        $nodes = $callable($collection ?? $this->breadcrumbs['collection'], $contextKey);
        $crumbs = [];
        foreach (is_iterable($nodes) ? $nodes : [] as $node) {
            if (\is_object($node)) {
                $crumbs[] = $this->crumbFromNode($node);
            }
        }

        return $crumbs;
    }

    /**
     * `nowo_ui_header_crumbs()` (2.27): the compact top-bar trail. Source, first match: `crumbs` (View\Crumb, hashes
     * {label, href?} or BreadcrumbKit nodes); `header` (View\PageHeader, resolved like the page-header trail:
     * `own` its breadcrumbs, `kit` BreadcrumbKit for its collection, `auto` BreadcrumbKit when it yields levels, else its
     * breadcrumbs; BreadcrumbKit only while `nowo_ui_kit.breadcrumbs.breadcrumb_kit` is on); else
     * {@see self::breadcrumbKitTrail()}.
     *
     * @param iterable<mixed>|null $crumbs
     * @param array<string, mixed> $options home_href, drop_home_crumb (default true), max_visible (default 3)
     */
    public function headerCrumbs(Environment $env, ?iterable $crumbs = null, ?PageHeader $header = null, array $options = []): HeaderCrumbs
    {
        if (null === $crumbs && $header instanceof PageHeader) {
            $kit = 'own' !== $header->breadcrumbsMode && $this->breadcrumbs['breadcrumb_kit'] ? $this->breadcrumbKitTrail($env, $header->breadcrumbCollection) : [];
            $crumbs = 'kit' === $header->breadcrumbsMode || [] !== $kit ? $kit : $header->breadcrumbs;
        }
        $crumbs ??= $this->breadcrumbKitTrail($env);
        $levels = [];
        foreach ($crumbs as $crumb) {
            $levels[] = $this->level($crumb);
        }
        $home = $options['home_href'] ?? null;
        $max = $options['max_visible'] ?? HeaderCrumbs::DEFAULT_MAX_VISIBLE;

        return HeaderCrumbs::build(
            $levels,
            \is_string($home) && '' !== $home ? $home : null,
            false !== ($options['drop_home_crumb'] ?? true),
            is_numeric($max) ? (int) $max : HeaderCrumbs::DEFAULT_MAX_VISIBLE,
        );
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

        return \is_scalar($html) || $html instanceof Stringable ? (string) $html : '';
    }

    /**
     * One translated level of a compact trail: a {@see Crumb}, a hash {label, href?} or a BreadcrumbKit node.
     *
     * @return array{label: string, href: string|null}
     */
    private function level(mixed $crumb): array
    {
        if (\is_array($crumb) && \array_key_exists('label', $crumb)) {
            $crumb = new Crumb($crumb['label'] instanceof Label ? $crumb['label'] : $this->trans($crumb['label']), \is_string($crumb['href'] ?? null) ? $crumb['href'] : null);
        } elseif (\is_object($crumb) && !$crumb instanceof Crumb && property_exists($crumb, 'label')) {
            $crumb = $this->crumbFromNode($crumb);
        }
        if (!$crumb instanceof Crumb) {
            throw new InvalidArgumentException(\sprintf('nowo_ui_header_crumbs expects View\Crumb items, {label, href?} hashes or BreadcrumbKit nodes, %s given.', get_debug_type($crumb)));
        }

        return ['label' => $this->trans($crumb->label), 'href' => null !== $crumb->href && '' !== $crumb->href ? $crumb->href : null];
    }

    /**
     * A BreadcrumbKit `BreadcrumbNode` (read by property, BreadcrumbKit is optional): `label`, `url`, `linkEnabled`.
     */
    private function crumbFromNode(object $node): Crumb
    {
        $vars = get_object_vars($node);
        $label = $vars['label'] ?? '';
        $url = $vars['url'] ?? null;
        $linked = ($vars['linkEnabled'] ?? true) && \is_string($url) && '' !== $url;

        return new Crumb(\is_scalar($label) || $label instanceof Stringable ? (string) $label : '', $linked ? $url : null);
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
