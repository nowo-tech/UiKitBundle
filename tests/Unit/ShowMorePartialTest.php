<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The `show_more` partial and the `emoji_picker` partial used directly: markup, slots, no style attribute.
 */
final class ShowMorePartialTest extends TestCase
{
    public function testTheMarkup(): void
    {
        $html = $this->render(['id' => 'd', 'content' => '<em>Text</em>']);

        self::assertStringContainsString('class="nowo-ui-show-more nowo-ui-show-more--lines-3" data-nowo-ui-show-more', $html);
        self::assertStringContainsString('<div id="d" class="nowo-ui-show-more__content" data-nowo-ui-show-more-content><em>Text</em></div>', $html);
        self::assertMatchesRegularExpression('/<button type="button" class="[^"]*nowo-ui-show-more__toggle"[^>]*aria-expanded="false" aria-controls="d" hidden/', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function lines(): iterable
    {
        yield 'one' => [1, 1];
        yield 'six' => [6, 6];
        yield 'too many' => [7, 3];
        yield 'not a number' => ['" onclick="x', 3];
    }

    #[DataProvider('lines')]
    public function testLinesAreLimited(mixed $lines, int $expected): void
    {
        $html = $this->render(['id' => 'd', 'lines' => $lines]);

        self::assertStringContainsString('nowo-ui-show-more--lines-'.$expected.'"', $html);
        self::assertStringNotContainsString('onclick', $html);
    }

    public function testLabelsAndAttributes(): void
    {
        $html = $this->render(['id' => 'd', 'labels' => ['more' => 'Más <b>', 'less' => 'Menos'], 'attr' => ['class' => 'mine', 'data-a' => '1'], 'content_attr' => ['class' => 'c', 'data-b' => '2']]);

        self::assertStringContainsString('nowo-ui-show-more--lines-3 mine"', $html);
        self::assertStringContainsString('data-a="1"', $html);
        self::assertStringContainsString('nowo-ui-show-more__content c"', $html);
        self::assertStringContainsString('data-b="2"', $html);
        self::assertStringContainsString('data-nowo-ui-show-more-less="Menos"', $html);
        self::assertStringContainsString('>Más &lt;b&gt;</button>', $html);
    }

    public function testACssRuleExistsForEveryNumberOfLines(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/nowo-ui.css');
        foreach (range(1, 6) as $n) {
            self::assertStringContainsString('.nowo-ui-show-more--lines-'.$n.'[data-nowo-ui-show-more-ready]', $css);
        }
    }

    public function testThePartialsAreRegistered(): void
    {
        self::assertSame('@NowoUiKitBundle/partials/_show_more.html.twig', ThemeSlots::PARTIALS['show_more']);
        self::assertSame('@NowoUiKitBundle/partials/_emoji_picker.html.twig', ThemeSlots::PARTIALS['emoji_picker']);
    }

    public function testThePickerWrapsAFieldOfTheHost(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKitBundle/partials/_emoji_picker.html.twig', {id: 'x', input: '<input id=\"mine\">', attr: {class: 'c', 'data-a': '1'}}) }}"], [], 'none');
        $html = $twig->render('t');

        self::assertStringContainsString('<input id="mine">', $html);
        self::assertStringContainsString('class="nowo-ui-emoji-picker c"', $html);
        self::assertStringContainsString('data-a="1"', $html);
        self::assertStringNotContainsString('data-nowo-ui-emoji-picker-table', $html);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function render(array $options): string
    {
        return PageContractTwig::create(['t' => "{{ include('@NowoUiKitBundle/partials/_show_more.html.twig', o) }}"], [], 'none')->render('t', ['o' => $options]);
    }
}
