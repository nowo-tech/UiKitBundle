<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;
use Nowo\UiKitBundle\Shell\SafeHref;

/**
 * Attribute maps of the per-element options added in 2.34 (`Table::$rowAttributes`, `link_attr`, `item_attr`,
 * `footer_attr`, `header_attr`, `foot_attr`, `meter_attr`), through the Twig function `nowo_ui_safe_attrs()`. The older
 * `attr` options only refuse on* handlers ({@see Action::assertAttributes()}). Two rules, on purpose different:
 *
 * - **Names are written in code, so a bad one throws:** on* handlers, an invalid name, `style` (the kit prints no inline
 *   style: CSP) and `srcdoc`.
 * - **Values come from data, so a bad one is dropped (audit HG-M1, HG-L4):** a value that runs script (`javascript:`,
 *   `vbscript:`, `data:`, `blob:`; {@see SafeHref::runsScript()}) in an attribute that holds a URL is not printed, and
 *   one stored value never takes a page down. Those attributes are `href`, `xlink:href`, `src`, `action`, `formaction`, `data`, `poster`, `background`,
 *   `cite`, `ping`, `data-redirect`, `data-location`, and `data-*` attributes whose name ends in `href`, `src`, `url`
 *   (also glued, `data-nexturl`), `uri`, `link` or `path`, also with a Stimulus `-value` suffix. Other values pass,
 *   `tel:`, app schemes and text such as `Madrid: centro` included.
 *   Other attributes hold text, so `data-title="JavaScript: the good parts"` passes.
 *
 * `data-action`, `data-controller` and framework attributes such as `hx-*` take any value: never map user data to them.
 * The check is on the scheme, not the origin: `//host` and `/\host` pass.
 * `null` and `false` values are dropped; `class` comes back under the key `class` for the caller to merge with its slot
 * classes; `$reserved` names (the attributes the partial owns, e.g. `role` of a treeitem) are dropped, so an option can
 * neither print them twice nor change them. All names compare case-insensitively.
 */
final class SafeAttributes
{
    private const URL_ATTRIBUTE = '/^(?:href|xlink:href|src|action|formaction|data|poster|background|cite|ping|data-(?:redirect|location|(?:.*[-_.:])?(?:[a-z]*url|href|src|uri|link|path)(?:-value)?))$/D';

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
            if (!\is_scalar($value) || false === $value || \in_array($lower, $reserved, true)) {
                continue;
            }
            if (\is_string($value) && 1 === preg_match(self::URL_ATTRIBUTE, $lower) && SafeHref::runsScript($value)) {
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
