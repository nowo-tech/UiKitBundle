<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\View\Label;
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
 * - `nowo_ui_class(slot, bundle = null)` — semantic + framework default + host classes of a theme slot
 * - `nowo_ui_attrs(slot, bundle = null, extra = {})` — escaped attribute string of a slot
 * - `nowo_ui_partial(name, bundle = null)` — template of a (possibly host-replaced) UiKit partial
 * - `nowo_ui_theme_tokens(nonce = null)` — `<style data-nowo-ui-theme-tokens>` with the host tokens ('' without)
 * - `nowo_ui_theme_css()` — the token stylesheet text
 * - `nowo_ui_breadcrumb_kit(collection = null)` — BreadcrumbKit's `breadcrumb_render()` output ('' when absent)
 * - `nowo_ui_breadcrumb_kit_available()` — whether BreadcrumbKit is installed and enabled for page headers
 * - `|nowo_ui_trans(params = {})` — translates a {@see Label}; strings pass through (escaped by Twig)
 * - globals `nowo_ui_layout` ({admin, guest}), `nowo_ui_theme` ({@see Theme}), `nowo_ui_breadcrumbs`
 */
final class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param array{admin: string, guest: string}                  $layouts
     * @param array{breadcrumb_kit: bool, collection: string|null} $breadcrumbs
     */
    public function __construct(
        private readonly Theme $theme,
        private readonly array $layouts = ['admin' => '@NowoUiKit/layout/admin.html.twig', 'guest' => '@NowoUiKit/layout/guest.html.twig'],
        private readonly array $breadcrumbs = ['breadcrumb_kit' => true, 'collection' => null],
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_ui_class', $this->theme->classFor(...)),
            new TwigFunction('nowo_ui_attrs', $this->theme->renderAttributes(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_partial', $this->theme->partial(...)),
            new TwigFunction('nowo_ui_theme_tokens', $this->tokens(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_theme_css', $this->theme->tokensCss(...)),
            new TwigFunction('nowo_ui_breadcrumb_kit', $this->breadcrumbKit(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('nowo_ui_breadcrumb_kit_available', $this->breadcrumbKitAvailable(...), ['needs_environment' => true]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('nowo_ui_trans', $this->trans(...)),
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

    private function requestNonce(): ?string
    {
        $nonce = $this->requestStack?->getCurrentRequest()?->attributes->get('csp_nonce');

        return \is_string($nonce) ? $nonce : null;
    }
}
