<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

/**
 * Locale display names and writing direction for the `locale_tabs` partial (2.5): `nowo_ui_locale_name()` /
 * `nowo_ui_locale_dir()`. Uses ext-intl when the extension is loaded (`es` → "Spanish" in English, "español" in
 * Spanish), else the upper-cased code. `symfony/polyfill-intl-icu` is not enough: it defines `\Locale` but its
 * `getDisplayName()` throws, so the gate is `extension_loaded('intl')` and any resolver failure falls back to the code
 * (2.5.2). Stateless.
 */
final class LocaleNames
{
    /** Primary language subtags written right to left. */
    public const RTL_LANGUAGES = ['ar', 'arc', 'ckb', 'dv', 'fa', 'he', 'iw', 'khw', 'ks', 'ku', 'ps', 'sd', 'syr', 'ug', 'ur', 'yi'];

    private readonly bool $intl;

    /** @var \Closure(string, string): string */
    private readonly \Closure $displayName;

    /**
     * @param bool|null                               $intl        null: `extension_loaded('intl')`
     * @param (\Closure(string, string): string)|null $displayName display-name resolver `(locale, displayLocale)`;
     *                                                             null: `\Locale::getDisplayName()`
     */
    public function __construct(?bool $intl = null, ?\Closure $displayName = null)
    {
        $this->intl = $intl ?? \extension_loaded('intl');
        $this->displayName = $displayName ?? static fn (string $locale, string $displayLocale): string => (string) \Locale::getDisplayName($locale, $displayLocale);
    }

    /**
     * Human name of a locale in the display locale, first letter upper-cased ("Español", "Portuguese (Brazil)");
     * the upper-cased code when intl is missing, does not know the locale or throws (polyfill-intl-icu).
     */
    public function name(string $locale, ?string $displayLocale = null): string
    {
        $fallback = strtoupper($locale);
        if (!$this->intl || '' === $locale) {
            return $fallback;
        }
        try {
            $name = ($this->displayName)($locale, $displayLocale ?? \Locale::getDefault());
        } catch (\Throwable) {
            return $fallback;
        }
        if ('' === $name || 0 === strcasecmp(str_replace('-', '_', $name), str_replace('-', '_', $locale))) {
            return $fallback;
        }

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    /**
     * `rtl` for right-to-left languages (Arabic, Hebrew, Persian, Urdu…), else `ltr`.
     */
    public function dir(string $locale): string
    {
        $language = strtolower((string) preg_replace('/[-_].*$/', '', $locale));

        return \in_array($language, self::RTL_LANGUAGES, true) ? 'rtl' : 'ltr';
    }
}
