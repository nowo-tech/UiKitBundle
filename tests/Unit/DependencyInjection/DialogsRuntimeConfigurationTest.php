<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * 2.70 `nowo_ui_kit.dialogs.runtime`: iife (default, the 2.68 markup) | stimulus (the on-demand dialog shells carry the
 * Stimulus peers).
 */
final class DialogsRuntimeConfigurationTest extends TestCase
{
    public function testIifeIsTheDefault(): void
    {
        self::assertSame(['runtime' => 'iife'], $this->process([])['dialogs']);
    }

    public function testStimulusIsAccepted(): void
    {
        self::assertSame('stimulus', $this->process(['dialogs' => ['runtime' => 'stimulus']])['dialogs']['runtime']);
    }

    public function testAnyOtherValueIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['dialogs' => ['runtime' => 'auto']]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
