<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;
use Nowo\UiKitBundle\Shell\SafeHref;

/**
 * Attribute maps of the per-element options added in 2.34 (`Table::$rowAttributes`, `link_attr`, `item_attr`,
 * `footer_attr`, `header_attr`, `foot_attr`, `meter_attr`), through the Twig function `nowo_ui_safe_attrs()`. The older
 * `attr` options only refuse on* handlers ({@see Action::assertAttributes()}); this also refuses:
 *
 * - `style` (the kit prints no inline style: CSP) and `srcdoc`;
 * - a `javascript:` URL, or any other scheme {@see SafeHref} does not allow, in an attribute that holds a URL: `href`,
 *   `xlink:href`, `src`, `action`, `formaction`, `data`, `poster`, `background`, `cite`, `ping`, and `data-*` attributes
 *   whose name ends in `href`, `src` or `url` (also with a Stimulus `-value` suffix). Other attributes hold text, so
 *   `data-title="JavaScript: the good parts"` passes and a task title never turns into an exception.
 *
 * A refused map throws, as an on* handler does: these names come from the template, so it is a programming error.
 * `null` and `false` values are dropped; `class` comes back under the key `class` for the caller to merge with its slot
 * classes; `$reserved` names (the attributes the partial owns, e.g. `role` of a treeitem) are dropped, so an option can
 * neither print them twice nor change them. All names compare case-insensitively.
 */
final class SafeAttributes
{
    private const URL_ATTRIBUTE = '/^(?:href|xlink:href|src|action|formaction|data|poster|background|cite|ping|data-(?:.*[-_.:])?(?:href|src|url)(?:-value)?)$/D';

    /**
     * @param array<array-key, mixed> $attributes
     * @param list<string>            $reserved   lower-case names the partial owns
     *
     * @return array<string, string|int|float|bool>
     */
    public static function of(array $attributes, array $reserved = []): array
    {
        Action::assertAttributes($attributes);
        $safe = [];
        foreach ($attributes as $name => $value) {
            $name = (string) $name;
            $lower = strtolower($name);
            if (\in_array($lower, ['style', 'srcdoc'], true)) {
                throw new InvalidArgumentException(\sprintf('Unsafe attribute "%s" (no inline style or srcdoc: CSP).', $name));
            }
            if (\is_string($value) && 1 === preg_match(self::URL_ATTRIBUTE, $lower) && !SafeHref::isSafe($value) && '' !== trim($value)) {
                throw new InvalidArgumentException(\sprintf('Unsafe URL in attribute "%s" (relative, http, https or mailto only).', $name));
            }
            if (!\is_scalar($value) || false === $value || \in_array($lower, $reserved, true)) {
                continue;
            }
            $safe['class' === $lower ? 'class' : $name] = $value;
        }

        return $safe;
    }

    /**
     * The attributes without `class`, for `nowo_ui_attrs()` / `nowo_ui_html_attrs()`.
     *
     * @param array<string, string|int|float|bool> $attributes
     *
     * @return array<string, string|int|float|bool>
     */
    public static function withoutClass(array $attributes): array
    {
        unset($attributes['class']);

        return $attributes;
    }
}
