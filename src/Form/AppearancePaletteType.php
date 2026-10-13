<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePalette;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The seven colours of one mode, as an array (`AppearancePalette::toArray()` shape).
 * Only registered when symfony/form is installed.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class AppearancePaletteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (AppearancePalette::KEYS as $key) {
            $field = [
                'label' => 'appearance.form.color.'.$key,
                'translation_domain' => 'NowoUiKitBundle',
                'html5' => true,
            ];
            if (class_exists(Callback::class)) {
                $field['constraints'] = [new Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                    if (!\is_string($value) || !AppearanceCssEmitter::isColor($value)) {
                        $context->buildViolation('appearance.form.invalid_color')->setTranslationDomain('NowoUiKitBundle')->addViolation();
                    }
                })];
            }
            $builder->add($key, ColorType::class, $field);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'NowoUiKitBundle',
        ]);
    }
}
