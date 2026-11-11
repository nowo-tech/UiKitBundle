<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Closure;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * FileType option `nowo_ui_image_input` (2.47): the field renders as the image input box ({@see ImageInput}; block
 * `nowo_ui_image_input_widget` of `form/nowo_ui_image_input.html.twig`, partial `image_input`, js/nowo-ui-image-input.js). Values:
 * `false` (default: the FileType renders exactly as before) | `true` | an array of {@see ImageInput} options, e.g.
 * `['current_url' => $user->avatarUrl(), 'default_url' => '/img/avatar.svg', 'remove_field' => 'removeAvatar']`. Refused on a
 * `multiple` field. Combine with `nowo_ui_image_crop`: the crop's preview then targets the box. Stateless (worker safe): every
 * value lives in the form view. Only registered when symfony/form is installed.
 */
final class ImageInputTypeExtension extends AbstractTypeExtension
{
    public const OPTION = 'nowo_ui_image_input';

    public const BLOCK_PREFIX = 'nowo_ui_image_input';

    /** English wording when no translator is given. */
    public const ENGLISH = [
        'replace' => 'Replace image',
        'choose' => 'Choose image',
        'remove' => 'Remove image',
        'undo' => 'Undo',
        'default' => 'Default',
        'empty' => 'No image',
        'selected' => 'Selected: %name%',
        'removed' => 'The image will be removed when you save.',
        'restored' => 'Change undone.',
        'drop' => 'Drop an image here',
        'not_image' => 'This file is not an image.',
    ];

    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FileType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault(self::OPTION, false);
        $resolver->setAllowedTypes(self::OPTION, ['bool', 'array']);
        $resolver->setNormalizer(self::OPTION, static function (Options $options, bool|array $value): array|false {
            if (false === $value) {
                return false;
            }
            if (true === ($options['multiple'] ?? false)) {
                throw new InvalidOptionsException(\sprintf('"%s" shows one image: it cannot be used with "multiple".', self::OPTION));
            }
            try {
                return ImageInput::normalize(true === $value ? [] : $value);
            } catch (InvalidArgumentException $exception) {
                throw new InvalidOptionsException(\sprintf('"%s": %s', self::OPTION, $exception->getMessage()), 0, $exception);
            }
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array{current_url: string|Closure|null, default_url: ?string, remove_field: ?string, backdrop: string, labels: array<string, string>}|false $config */
        $config = $options[self::OPTION];
        if (false === $config) {
            return;
        }
        $current = $config['current_url'];
        if ($current instanceof Closure) {
            $current = ImageInput::url($current($form), 'current_url');
        }
        $remove = $config['remove_field'];
        if (null !== $remove && !($form->getParent()?->has($remove) ?? false)) {
            throw new LogicException(\sprintf('"%s": the remove_field "%s" is not a field of the parent form of "%s".', self::OPTION, $remove, $form->getName()));
        }

        /** @var list<string> $prefixes */
        $prefixes = $view->vars['block_prefixes'];
        array_splice($prefixes, max(0, \count($prefixes) - 1), 0, [self::BLOCK_PREFIX]);
        $view->vars['block_prefixes'] = $prefixes;

        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'] ?? [];
        $view->vars['attr'] = $attr + ['accept' => 'image/*'];
        $view->vars[self::OPTION] = [
            'current_url' => $current,
            'default_url' => $config['default_url'],
            'remove_field' => $remove,
            'backdrop' => $config['backdrop'],
            'labels' => $this->labels($config['labels']),
        ];
    }

    /**
     * Every label: the field's override, else the translation of `image_input.<key>` (domain NowoUiKitBundle), else English.
     *
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function labels(array $overrides): array
    {
        $labels = [];
        foreach (self::ENGLISH as $key => $english) {
            if (isset($overrides[$key])) {
                $labels[$key] = $overrides[$key];
                continue;
            }
            $id = 'image_input.'.$key;
            $text = $this->translator?->trans($id, [], 'NowoUiKitBundle');
            $labels[$key] = null === $text || $text === $id ? $english : $text;
        }

        return $labels;
    }
}
