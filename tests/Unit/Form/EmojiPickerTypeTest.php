<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Form\EmojiPickerType;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The emoji picker field: a plain text input without JavaScript, one grapheme on the server, and opt-in.
 */
final class EmojiPickerTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function values(): iterable
    {
        yield 'emoji' => ['🚀', true];
        yield 'variation selector' => ['❤️', true];
        yield 'skin tone' => ['👍🏽', true];
        yield 'family' => ['👨‍👩‍👧‍👦', true];
        yield 'flag' => ['🇪🇸', true];
        yield 'keycap' => ['1️⃣', true];
        yield 'letter' => ['a', true];
        yield 'two emoji' => ['🚀🚀', false];
        yield 'two letters' => ['ab', false];
        yield 'emoji and text' => ['🚀 x', false];
        yield 'a space' => [' ', false];
        yield 'a newline' => ["\n", false];
        yield 'a control character' => ["\x07", false];
        yield 'empty' => ['', false];
        yield 'invalid UTF-8' => ["\xFF", false];
    }

    #[DataProvider('values')]
    public function testIsOneGrapheme(string $value, bool $valid): void
    {
        self::assertSame($valid, EmojiPickerType::isOneGrapheme($value));
    }

    public function testTheServerChecksTheValue(): void
    {
        $submit = fn (?string $value): bool => $this->factory()->create(EmojiPickerType::class)->submit($value)->isValid();

        self::assertTrue($submit('🚀'));
        self::assertTrue($submit(null));
        self::assertTrue($submit(''));
        self::assertFalse($submit('🚀🚀'));
    }

    public function testTheErrorIsTranslatedAndHasAFallback(): void
    {
        $withTranslator = $this->factory(new EmojiPickerType(new IdentityTranslator()))->create(EmojiPickerType::class)->submit('ab');
        self::assertSame('emoji_picker.invalid', $withTranslator->getErrors()[0]->getMessage());
        $without = $this->factory(new EmojiPickerType())->create(EmojiPickerType::class)->submit('ab');
        self::assertSame('Enter one emoji or character.', $without->getErrors()[0]->getMessage());
    }

    public function testItRendersAPlainInputAndHiddenExtras(): void
    {
        $html = $this->render($this->view(['data' => '🚀', 'attr' => ['class' => 'host', 'data-x' => '1']]));

        self::assertStringContainsString('<input type="text" id="icon" name="icon" class="nowo-ui-input host" value="🚀" autocomplete="off" required data-x="1">', $html);
        self::assertStringContainsString('data-nowo-ui-emoji-picker ', $html);
        self::assertMatchesRegularExpression('/<button type="button"[^>]*data-nowo-ui-emoji-picker-toggle[^>]* hidden/', $html);
        self::assertMatchesRegularExpression('/<div id="icon_emoji-panel"[^>]*role="dialog"[^>]* hidden/', $html);
        self::assertStringContainsString('role="combobox"', $html);
        self::assertStringContainsString('aria-controls="icon_emoji-grid"', $html);
        self::assertStringContainsString('role="grid"', $html);
        self::assertStringContainsString('role="status"', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertSame(1, substr_count($html, ' name='), 'only the field is submitted');
    }

    public function testTheEmojisAndLabelsOptions(): void
    {
        $html = $this->render($this->view(['emojis' => [['cat', '<🐱>']], 'labels' => ['open' => 'Pick <it>', 'results' => '%count% hits']]));

        self::assertStringContainsString('data-nowo-ui-emoji-picker-table="', $html);
        self::assertStringNotContainsString('<🐱>', $html);
        self::assertStringContainsString('aria-label="Pick &lt;it&gt;"', $html);
        self::assertStringContainsString('%count% hits', html_entity_decode($html));
    }

    public function testAFieldWithErrorsAndHelpIsLinked(): void
    {
        $view = $this->factory()->create(EmojiPickerType::class, null, ['help' => 'One only'])->submit('ab')->createView();
        $view->vars['id'] = 'icon';
        $html = $this->render($view);

        self::assertStringContainsString('aria-describedby="icon_help"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
    }

    public function testADisabledFieldSaysSo(): void
    {
        self::assertStringContainsString(' disabled', $this->render($this->view(['disabled' => true, 'required' => false])));
    }

    public function testItIsOptIn(): void
    {
        self::assertNotContains('emoji-picker', ThemeSlots::SCRIPTS);
        self::assertNotContains('show-more', ThemeSlots::SCRIPTS);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function view(array $options = []): FormView
    {
        return $this->factory()->createNamed('icon', EmojiPickerType::class, $options['data'] ?? null, array_diff_key($options, ['data' => true]))->createView();
    }

    private function factory(?EmojiPickerType $type = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType($type ?? new EmojiPickerType())->getFormFactory();
    }

    private function render(FormView $view): string
    {
        $theme = NowoUiKitExtension::EMOJI_PICKER_FORM_THEME;
        $twig = PageContractTwig::create(['t' => "{% form_theme field '".$theme."' %}{{ form_widget(field) }}"], [], 'none');

        return $twig->render('t', ['field' => $view]);
    }
}
