<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\BorderStrength;
use Nowo\UiKitBundle\Appearance\BuiltInAppearancePresets;
use Nowo\UiKitBundle\Appearance\CornerStyle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Optional admin form for the appearance, bound to an array (`Appearance::toArray()` shape):
 * map it with `Appearance::fromArray($form->getData(), $current)` and persist it in your storage.
 * Choosing a preset id does not recolour by itself — call `AppearancePresetRegistry::apply()` on submit if
 * the preset should win over the colour fields. Only registered when symfony/form is installed.
 *
 * Options: `with_brand` (default true), `with_colors` (default true), `with_footer` (default true).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class AppearanceType extends AbstractType
{
    public function __construct(
        private readonly AppearancePresetRegistry $registry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $label = static fn (string $key): array => ['label' => 'appearance.form.'.$key, 'translation_domain' => 'NowoUiKitBundle'];

        if ($options['with_brand']) {
            $builder
                ->add('brand_name', TextType::class, $label('brand_name') + ['required' => false, 'empty_data' => ''])
                ->add('brand_eyebrow', TextType::class, $label('brand_eyebrow') + ['required' => false, 'empty_data' => '']);
        }

        $builder
            ->add('theme_id', ChoiceType::class, $label('theme_id') + ['choices' => $this->presetChoices(AppearancePreset::MODE_LIGHT)])
            ->add('theme_id_dark', ChoiceType::class, $label('theme_id_dark') + ['choices' => $this->presetChoices(AppearancePreset::MODE_DARK)]);

        if ($options['with_colors']) {
            $builder
                ->add('light', AppearancePaletteType::class, $label('light'))
                ->add('dark', AppearancePaletteType::class, $label('dark'));
        }

        $builder
            ->add('corner_style', ChoiceType::class, $label('corner_style') + [
                'choices' => array_combine(
                    array_map(static fn (string $v): string => 'appearance.corner.'.$v, CornerStyle::values()),
                    CornerStyle::values(),
                ),
            ])
            ->add('border_strength', ChoiceType::class, $label('border_strength') + [
                'choices' => array_combine(
                    array_map(static fn (string $v): string => 'appearance.border.'.$v, BorderStrength::values()),
                    BorderStrength::values(),
                ),
            ]);

        if ($options['with_footer']) {
            $builder->add('footer_fixed', CheckboxType::class, $label('footer_fixed') + ['required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'NowoUiKitBundle',
            'with_brand' => true,
            'with_colors' => true,
            'with_footer' => true,
        ]);
        $resolver->setAllowedTypes('with_brand', 'bool');
        $resolver->setAllowedTypes('with_colors', 'bool');
        $resolver->setAllowedTypes('with_footer', 'bool');
    }

    /**
     * @return array<string, string> label => preset id (presets of the mode, then `custom`)
     */
    private function presetChoices(string $mode): array
    {
        $choices = [];
        foreach ($this->registry->byMode($mode) as $preset) {
            // Built-ins are translated; host presets show a readable id (or add the key to your translations).
            $label = \in_array($preset->id, BuiltInAppearancePresets::IDS, true)
                ? 'appearance.preset.'.$preset->id
                : ucfirst(str_replace(['-', '_'], ' ', $preset->id));
            $choices[$label] = $preset->id;
        }
        $choices['appearance.preset.'.Appearance::CUSTOM] = Appearance::CUSTOM;

        return $choices;
    }
}
