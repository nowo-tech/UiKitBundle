<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Enum;

use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\Enum\IconSet;
use PHPUnit\Framework\TestCase;

final class EnumTest extends TestCase
{
    public function testCssFrameworkNormalizesBootstrapAlias(): void
    {
        self::assertSame(CssFramework::Bootstrap5, CssFramework::Bootstrap->normalized());
        self::assertSame(CssFramework::Tailwind, CssFramework::Tailwind->normalized());
    }

    public function testIconSetValues(): void
    {
        self::assertContains('bootstrap-icons', IconSet::values());
        self::assertContains('ux_icon', IconSet::values());
    }
}
