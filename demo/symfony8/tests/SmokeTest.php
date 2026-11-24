<?php

declare(strict_types=1);

use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testAutoload(): void
    {
        self::assertTrue(class_exists(NowoUiKitBundle::class));
    }
}
