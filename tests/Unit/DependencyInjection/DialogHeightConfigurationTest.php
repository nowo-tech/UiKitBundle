<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use InvalidArgumentException;
use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\Theme\Theme;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * 2.31 `nowo_ui_kit.theme.dialog.height` (dialog rule v2): `content` (default) = every kit dialog is as tall as its
 * content, capped; `size` = the 2.29 / 2.30 size's height, printed as `--nowo-ui-dialog-fill: 1` on :root.
 */
final class DialogHeightConfigurationTest extends TestCase
{
    public function testContentIsTheDefaultAndPrintsNoToken(): void
    {
        $config = $this->process([]);
        self::assertSame(['height' => 'content'], $config['theme']['dialog']);
        $theme = Theme::fromConfig('bootstrap5', $config['theme']);
        self::assertSame('content', $theme->dialogHeight());
        self::assertFalse($theme->hasTokens());
        self::assertSame('', $theme->tokensCss());
        self::assertSame('content', Theme::fromConfig('bootstrap5', [])->dialogHeight(), 'a theme array without dialog (e.g. StubHost) is content');
        self::assertSame('content', (new Theme())->dialogHeight());
    }

    public function testSizeKeepsThe230HeightThroughTheFillToken(): void
    {
        $theme = Theme::fromConfig('bootstrap5', $this->process(['theme' => ['dialog' => ['height' => 'size']]])['theme']);
        self::assertSame('size', $theme->dialogHeight());
        self::assertTrue($theme->hasTokens());
        self::assertSame(":root {\n  --nowo-ui-dialog-fill: 1;\n}", $theme->tokensCss());

        // An explicit token wins (e.g. 0 to switch it off again from a bundle-free host config).
        self::assertSame(":root {\n  --nowo-ui-dialog-fill: 0;\n}", (new Theme(tokens: ['dialog-fill' => '0'], dialogHeight: 'size'))->tokensCss());
        self::assertSame(":root {\n  --nowo-ui-dialog-fill: 0;\n}", (new Theme(tokens: ['--nowo-ui-dialog-fill' => '0'], dialogHeight: 'size'))->tokensCss());
    }

    public function testInvalidValuesAreRejected(): void
    {
        try {
            $this->process(['theme' => ['dialog' => ['height' => 'fixed']]]);
            self::fail('an unknown dialog height must be rejected');
        } catch (InvalidConfigurationException $e) {
            self::assertStringContainsString('"content", "size"', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UiKit theme dialog.height "fixed": use "content" or "size".');
        new Theme(dialogHeight: 'fixed');
    }

    public function testFromConfigRejectsANonStringHeight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UiKit theme dialog.height "int": use "content" or "size".');
        Theme::fromConfig('bootstrap5', ['dialog' => ['height' => 1]]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(), [$config]);

        return $processed;
    }
}
