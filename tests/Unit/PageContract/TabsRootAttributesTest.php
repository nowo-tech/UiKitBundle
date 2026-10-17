<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * `_tabs` root `attr` (2.1): bundles keep 1.x `<nav>` attributes (e.g. `data-turbo="false"`), merged over the `tabs`
 * slot attributes, with and without the ThemeExtension.
 */
final class TabsRootAttributesTest extends TestCase
{
    private const ITEMS = [['label' => 'General', 'href' => '/g', 'current' => true], ['label' => 'Mail', 'href' => '/m']];

    public function testRootAttributesMergeOverSlotAttributes(): void
    {
        $twig = PageContractTwig::create([], ['attributes' => ['tabs' => ['data-host' => 'tabs', 'data-turbo' => 'true']]]);
        $html = $twig->render('@NowoUiKitBundle/partials/_tabs.html.twig', ['items' => self::ITEMS, 'aria_label' => 'Sections', 'attr' => ['data-turbo' => 'false', 'data-flag' => true, 'data-off' => false, 'data-null' => null]]);

        self::assertStringContainsString('aria-label="Sections" data-host="tabs" data-turbo="false" data-flag>', $html);
        self::assertStringNotContainsString('data-off', $html);
        self::assertStringNotContainsString('data-null', $html);
    }

    public function testOnHandlersAreRefused(): void
    {
        $this->expectException(\Twig\Error\RuntimeError::class);
        PageContractTwig::create()->render('@NowoUiKitBundle/partials/_tabs.html.twig', ['items' => self::ITEMS, 'attr' => ['onclick' => 'x()']]);
    }

    public function testBareTwigRendersRootAttributes(): void
    {
        $fs = new FilesystemLoader();
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment($fs);
        $twig->addFilter(new \Twig\TwigFilter('trans', static fn (string $id): string => $id));

        $html = $twig->render('@NowoUiKitBundle/partials/_tabs.html.twig', ['items' => self::ITEMS, 'aria_label' => 'Sections', 'attr' => ['data-turbo' => 'false', 'hidden' => true, 'data-off' => false]]);

        self::assertStringContainsString('aria-label="Sections" data-turbo="false" hidden>', $html);
        self::assertStringNotContainsString('data-off', $html);
    }
}
