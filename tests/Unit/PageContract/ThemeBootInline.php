<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

/**
 * Byte-identical fixtures embed the inline pre-paint theme-boot script (partials/_theme_boot.html.twig). Its body is a
 * build output (2.72: it carries the "script loaded" line with the build time of build-info.json), so the BC tests
 * compare the markup around it: the body of `<script … data-nowo-ui-theme-boot>` is replaced by a marker on both sides.
 * The body itself is covered by UiMacrosTwigTest (inline copy = built IIFE) and the Vitest suite.
 */
final class ThemeBootInline
{
    public static function normalize(string $html): string
    {
        return (string) preg_replace('#(<script[^>]*\sdata-nowo-ui-theme-boot>).*?(</script>)#s', '$1/* theme-boot */$2', $html);
    }
}
