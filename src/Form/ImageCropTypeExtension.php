<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use InvalidArgumentException;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * FileType option `nowo_ui_image_crop` (2.43): the file input gets the attributes of the image crop step ({@see ImageCrop}), so a
 * picked image is cropped in the browser (js/nowo-ui-image-crop.js) and the crop is what the form uploads. Values: `false`
 * (default) | `true` (the defaults: a square, 1024 px, PNG) | an array of {@see ImageCrop} options, e.g.
 * `['shape' => 'circle', 'max' => 512, 'format' => 'webp']`. Refused on a `multiple` field. The field's `attr` wins over the
 * printed attributes, and an `accept` is left as the field sets it. Only registered when symfony/form is installed.
 */
final class ImageCropTypeExtension extends AbstractTypeExtension
{
    public const OPTION = 'nowo_ui_image_crop';

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
                throw new InvalidOptionsException(\sprintf('"%s" crops one image: it cannot be used with "multiple".', self::OPTION));
            }
            $value = true === $value ? [] : $value;
            try {
                ImageCrop::normalize($value);
            } catch (InvalidArgumentException $exception) {
                throw new InvalidOptionsException(\sprintf('"%s": %s', self::OPTION, $exception->getMessage()), 0, $exception);
            }

            return $value;
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array<mixed>|false $config */
        $config = $options[self::OPTION];
        if (false === $config) {
            return;
        }
        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'] ?? [];
        $view->vars['attr'] = $attr + ImageCrop::attributes($config, $this->translator);
        $view->vars[self::OPTION] = true;
    }
}
