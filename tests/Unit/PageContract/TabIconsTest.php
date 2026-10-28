<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;

/**
 * `_tabs` item icons (2.16): an item with `icon` renders an icon span, a label span and `data-rail-label`; the host's
 * `data-tab-style` decides in CSS which shows. Items without an icon render as before.
 */
final class TabIconsTest extends TestCase
{
    public function testItemWithIconRendersIconLabelAndRailLabel(): void
    {
        $twig = PageContractTwig::create();
        $html = $twig->render('@NowoUiKitBundle/partials/_tabs.html.twig', ['items' => [
            ['label' => 'Issues', 'href' => '/i', 'current' => true, 'icon' => 'view'],
            ['label' => 'Settings', 'href' => '/s'],
        ]]);

        self::assertMatchesRegularExpression('#data-rail-label="Issues"\s*>\s*<span class="nowo-ui-tabs__icon" aria-hidden="true"><i [^>]*></i></span><span class="nowo-ui-tabs__label">Issues</span></a>#', $html);
        self::assertMatchesRegularExpression('#href="/s"\s*>Settings</a>#', $html);
        self::assertStringNotContainsString('data-rail-label="Settings"', $html);
    }

    public function testIconSetNoneKeepsTheLabelOnly(): void
    {
        $twig = PageContractTwig::create();
        $twig->addGlobal('nowo_ui_kit_icon_set', 'none');
        $html = $twig->render('@NowoUiKitBundle/partials/_tabs.html.twig', ['items' => [['label' => 'Issues', 'href' => '/i', 'icon' => 'view']]]);

        self::assertStringNotContainsString('nowo-ui-tabs__icon', $html);
        self::assertStringNotContainsString('data-rail-label', $html);
        self::assertMatchesRegularExpression('#href="/i"\s*>Issues</a>#', $html);
    }

    public function testStylesheetsCarryTheThreeTabStyles(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/'.$file);
            self::assertStringContainsString(":is([data-tab-style='icon-text'], [data-tab-style='icon']) .nowo-ui-tabs__icon", $css, $file);
            self::assertStringContainsString("[data-tab-style='icon'] .nowo-ui-tabs__item[data-rail-label]", $css, $file);
            self::assertStringContainsString('content: attr(data-rail-label);', $css, $file);
            self::assertStringContainsString("[data-tab-style='icon'] .nowo-ui-tabs__item[data-rail-label]:first-child:not(.nowo-ui-tabs__item--active, .is-active, [aria-current='page']):is(:hover, :focus-visible)::after", $css, $file);
        }
    }
}
