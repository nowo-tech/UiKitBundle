<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * GET search of the kitchen sink host-chrome filter bar (`?q=…` under a nameless root, REQ-TWIG-005 rule 6).
 *
 * @extends AbstractType<null>
 */
final class HostChromeFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('q', SearchType::class, ['data' => 'beta', 'required' => false, 'label' => false, 'attr' => ['aria-label' => 'Search']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['method' => 'GET', 'csrf_protection' => false, 'translation_domain' => false]);
    }
}
