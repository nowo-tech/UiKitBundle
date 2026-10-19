<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

/**
 * Locale display names and writing direction for the `locale_tabs` partial (2.5): `nowo_ui_locale_name()` /
 * `nowo_ui_locale_dir()`. Uses ext-intl when present (`es` → "Spanish" in English, "español" in Spanish), else the
 * upper-cased code. Stateless.
 */
final class LocaleNames
{
    /** Primary language subtags written right to left. */
    public const RTL_LANGUAGES = ['ar', 'arc', 'ckb', 'dv', 'fa', 'he', 'iw', 'khw', 'ks', 'ku', 'ps', 'sd', 'syr', 'ug', 'ur', 'yi'];

    private readonly bool $intl;

    public function __construct(?bool $intl = null)
    {
        $this->intl = $intl ?? class_exists(\Locale::class);
    }

    /**
     * Human name of a locale in the display locale, first letter upper-cased ("Español", "Portuguese (Brazil)");
     * the upper-cased code when intl is missing or does not know the locale.
     */
    public function name(string $locale, ?string $displayLocale = null): string
    {
        $fallback = strtoupper($locale);
        if (!$this->intl || '' === $locale) {
            return $fallback;
        }
        $name = \Locale::getDisplayName($locale, $displayLocale ?? \Locale::getDefault());
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
