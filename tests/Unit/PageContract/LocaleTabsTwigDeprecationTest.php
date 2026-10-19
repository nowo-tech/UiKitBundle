<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;

/**
 * 2.5.1: the locale_tabs partial compiles without Twig deprecations (twig/twig 3.15 `??` precedence).
 */
final class LocaleTabsTwigDeprecationTest extends TestCase
{
    public function testCompilesWithoutDeprecations(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $no, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $twig = PageContractTwig::create();
            $twig->compileSource($twig->getLoader()->getSourceContext('@NowoUiKitBundle/partials/_locale_tabs.html.twig'));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }
}
