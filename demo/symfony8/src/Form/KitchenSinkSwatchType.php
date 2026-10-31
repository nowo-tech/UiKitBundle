<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Control swatches of the kitchen sink toolbars (a checkbox and a search input styled by the UiKit macros), rendered
 * with form_start / form_widget / form_end (REQ-TWIG-005). Never submitted: a styling sample.
 *
 * @extends AbstractType<null>
 */
final class KitchenSinkSwatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('check', CheckboxType::class, ['data' => true, 'required' => false, 'label' => 'Checkbox'])
            ->add('search', SearchType::class, ['required' => false, 'label' => false, 'attr' => ['placeholder' => 'Input placeholder', 'aria-label' => 'Input']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['method' => 'GET', 'csrf_protection' => false, 'translation_domain' => false]);
    }
}
