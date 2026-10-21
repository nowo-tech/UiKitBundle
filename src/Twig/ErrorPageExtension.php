<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Nowo\UiKitBundle\View\ErrorIllustration;
use Nowo\UiKitBundle\View\ErrorPage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Kit error pages (2.6, `nowo_ui_kit.error_pages`, templates `@NowoUiKit/error/{layout,status}.html.twig`):
 *
 * - `nowo_ui_error_page(status_code, exception = null)` — an {@see ErrorPage}: illustration of the code, the 429
 *   `Retry-After` seconds, "Try again" (408 / 5xx on GET), "Go back" (same-site referer) and the 5xx self-reload
 * - `nowo_ui_error_text(field, status_code = null, params = {})` — a text of the page from the host domain / key prefix
 *   (`<prefix>.<code>.<field>`, `<prefix>.generic.<field>`, `<prefix>.<field>`), falling back to UiKit's own catalogue
 *   (`NowoUiKitBundle`, `error.*`) so a host only translates what it brands
 * - global `nowo_ui_error_pages` — `{layout_template, theme_storage, theme_toggle, html_attributes, translation_domain,
 *   translation_prefix}`
 *
 * Stateless: the request is read from the request stack on each call (FrankenPHP worker safe).
 */
final class ErrorPageExtension extends AbstractExtension implements GlobalsInterface
{
    public const KIT_DOMAIN = 'NowoUiKitBundle';

    public const KIT_PREFIX = 'error';

    /** Per-code fields; the generic texts stand in for `title` / `lead` of a known code missing them. */
    private const CODE_FIELDS = ['title', 'lead', 'hint', 'image_alt'];

    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param array<string, mixed> $config processed `nowo_ui_kit.error_pages` (missing keys take the defaults)
     */
    public function __construct(
        array $config = [],
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
        $this->config = $config + self::defaults();
    }

    /**
     * Defaults of `nowo_ui_kit.error_pages` (same as the configuration tree).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'layout_template' => '@NowoUiKit/error/layout.html.twig',
            'translation_domain' => self::KIT_DOMAIN,
            'translation_prefix' => self::KIT_PREFIX,
            'home_path' => '/',
            'illustrations' => [],
            'illustration_width' => 1024,
            'illustration_height' => 683,
            'webp' => true,
            'retry_statuses' => [408],
            'back_link_statuses' => [404],
            'auto_retry_seconds' => 600,
            'theme_storage' => 'nowo-ui-theme',
            'theme_toggle' => true,
            'html_attributes' => [],
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_ui_error_page', $this->page(...)),
            new TwigFunction('nowo_ui_error_text', $this->text(...)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        return ['nowo_ui_error_pages' => [
            'layout_template' => $this->config['layout_template'],
            'theme_storage' => $this->config['theme_storage'],
            'theme_toggle' => $this->config['theme_toggle'],
            'html_attributes' => $this->config['html_attributes'],
            'translation_domain' => $this->config['translation_domain'],
            'translation_prefix' => $this->config['translation_prefix'],
        ]];
    }

    /**
     * The error page model of a status code; `exception` is TwigBundle's FlattenException (or any object with
     * `getHeaders()`, or a header map) — only its `Retry-After` header is read.
     */
    public function page(int|string|null $statusCode, mixed $exception = null): ErrorPage
    {
        $code = $this->code($statusCode);
        $request = $this->requestStack?->getCurrentRequest();
        $get = $request instanceof Request && 'GET' === $request->getMethod();
        /** @var list<int> $retryStatuses */
        $retryStatuses = $this->config['retry_statuses'];
        /** @var list<int> $backStatuses */
        $backStatuses = $this->config['back_link_statuses'];

        $retryHref = $get && ($code >= 500 || \in_array($code, $retryStatuses, true)) ? $request->getRequestUri() : null;
        $backHref = null;
        if ($request instanceof Request && \in_array($code, $backStatuses, true)) {
            $referer = (string) $request->headers->get('referer', '');
            $backHref = str_starts_with($referer, $request->getSchemeAndHttpHost().'/') ? $referer : null;
        }
        $autoRetry = $get && $code >= 500 ? max(0, (int) $this->config['auto_retry_seconds']) : 0;

        return new ErrorPage(
            $code,
            $this->illustration($code),
            429 === $code ? $this->retryAfter($exception) : null,
            $retryHref,
            $backHref,
            $autoRetry,
            (string) $this->config['home_path'],
        );
    }

    /**
     * Text of an error page. Per-code fields (`title`, `lead`, `hint`, `image_alt`) of a code that neither the host
     * nor UiKit translates use the generic texts (`<prefix>.generic.<field>`); a known code without its own `title` /
     * `lead` / `image_alt` uses the generic one, without `hint` none (''). Per-code fields receive `%code%`. Any other field (`home`, `retry`, `back`,
     * `retry_after`, `auto_retry`, `skip_to_content`, `document_title`…) is `<prefix>.<field>`. Each key is looked up in the
     * host domain first, then in UiKit's catalogue.
     *
     * @param array<string, mixed> $params
     */
    public function text(string $field, int|string|null $statusCode = null, array $params = []): string
    {
        if (!\in_array($field, self::CODE_FIELDS, true)) {
            return $this->first([$field], $params) ?? $field;
        }
        $code = $this->code($statusCode);
        $params += ['%code%' => $code];
        $known = $code > 0 && null !== $this->first([$code.'.title'], []);
        if (!$known) {
            return $this->first(['generic.'.$field], $params) ?? '';
        }
        $keys = [$code.'.'.$field];
        if ('hint' !== $field) {
            $keys[] = 'generic.'.$field;
        }

        return $this->first($keys, $params) ?? '';
    }

    /**
     * First translated key: each key in the host domain / prefix, then in UiKit's (`NowoUiKitBundle`, `error`).
     *
     * @param list<string>         $keys
     * @param array<string, mixed> $params
     */
    private function first(array $keys, array $params): ?string
    {
        if (!$this->translator instanceof TranslatorInterface) {
            return null;
        }
        $sources = array_unique([
            $this->config['translation_domain'].'|'.$this->config['translation_prefix'],
            self::KIT_DOMAIN.'|'.self::KIT_PREFIX,
        ]);
        foreach ($keys as $key) {
            foreach ($sources as $source) {
                [$domain, $prefix] = explode('|', $source, 2);
                $id = ('' === $prefix ? '' : $prefix.'.').$key;
                $text = $this->translator->trans($id, $params, $domain);
                if ($text !== $id && '' !== $text) {
                    return $text;
                }
            }
        }

        return null;
    }

    private function illustration(int $code): ?ErrorIllustration
    {
        /** @var array<int|string, array{path: string, width?: int|null, height?: int|null, webp?: bool|null, extension?: string|null}> $map */
        $map = $this->config['illustrations'];
        $entry = $map[$code] ?? $map[(string) $code] ?? $map['default'] ?? null;
        if (null === $entry) {
            return null;
        }

        return new ErrorIllustration(
            $entry['path'],
            (int) ($entry['width'] ?? $this->config['illustration_width']),
            (int) ($entry['height'] ?? $this->config['illustration_height']),
            (bool) ($entry['webp'] ?? $this->config['webp']),
            (string) ($entry['extension'] ?? 'png'),
        );
    }

    private function code(int|string|null $statusCode): int
    {
        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }

    /**
     * Seconds of a numeric `Retry-After` header (an HTTP date is ignored), from a FlattenException / HttpException
     * (`getHeaders()`) or a header map.
     */
    private function retryAfter(mixed $exception): ?int
    {
        $headers = \is_object($exception) && method_exists($exception, 'getHeaders') ? $exception->getHeaders() : $exception;
        if (!\is_array($headers)) {
            return null;
        }
        foreach ($headers as $name => $value) {
            if (\is_string($name) && 'retry-after' === strtolower($name)) {
                $value = \is_array($value) ? reset($value) : $value;

                return \is_scalar($value) && 1 === preg_match('/^\d+$/', (string) $value) ? (int) $value : null;
            }
        }

        return null;
    }
}
