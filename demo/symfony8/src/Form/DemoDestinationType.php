<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * UiKit 2.28 remote form dialog demo: a notification destination with a required label (checked in the controller:
 * the demo has no symfony/validator).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class DemoDestinationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['required' => false, 'label' => 'Label'])
            ->add('channel', TextType::class, ['required' => false, 'label' => 'Channel']);
    }
}
