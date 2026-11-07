<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Emoji picker field: one text input holding one grapheme, rendered by the `nowo_ui_emoji_picker_widget` block
 * (`form/nowo_ui_emoji_picker.html.twig`). Without JavaScript it is a plain text input; `js/nowo-ui-emoji-picker.js` adds a toggle, a
 * search box and a grid. The server checks the value, as the browser's check can be skipped: empty, or exactly one user-perceived
 * character that is not whitespace or a control character. Whether a value is required is the host's: `required` and its constraints.
 *
 * Options:
 *   - `emojis`: list of `[name, emoji]` replacing the built-in table in the grid (null by default)
 *   - `labels`: `open`, `search`, `results`, `none` overrides, already translated or `View\Label`
 *   - `bundle`: bundle name for per-bundle theme exceptions
 *
 * @extends AbstractType<string|null>
 */
final class EmojiPickerType extends AbstractType
{
    public const BLOCK_PREFIX = 'nowo_ui_emoji_picker';

    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
    }

    /**
     * Whether a text is exactly one user-perceived character, not whitespace or a control character. `\X` follows the Unicode grapheme
     * rules, so a family, a flag, a keycap and a skin tone count as one.
     */
    public static function isOneGrapheme(string $value): bool
    {
        return 1 === preg_match('/\A(?![\s\p{Cc}])\X\z/u', $value);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $value = $event->getData();
            if (null === $value || '' === $value) {
                return;
            }
            if (!\is_string($value) || !self::isOneGrapheme($value)) {
                $message = 'emoji_picker.invalid';
                $event->getForm()->addError(new FormError($this->translator?->trans($message, [], 'NowoUiKitBundle') ?? 'Enter one emoji or character.', $message));
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars[self::BLOCK_PREFIX] = [
            'emojis' => $options['emojis'],
            'labels' => $options['labels'],
            'bundle' => $options['bundle'],
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'emojis' => null,
            'labels' => [],
            'bundle' => null,
        ]);
        $resolver->setAllowedTypes('emojis', ['null', 'array']);
        $resolver->setAllowedTypes('labels', 'array');
        $resolver->setAllowedTypes('bundle', ['null', 'string']);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return self::BLOCK_PREFIX;
    }
}
