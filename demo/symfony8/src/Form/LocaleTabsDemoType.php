<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Translated title / body of the kitchen sink locale tabs demo: `title[<locale>]` and `body[<locale>]` under a
 * nameless GET root (REQ-TWIG-005); `entries` = list of {locale, title, body}, `required_locales` mark their title
 * required.
 *
 * @extends AbstractType<null>
 */
final class LocaleTabsDemoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $title = $builder->create('title', FormType::class, ['label' => false]);
        $body = $builder->create('body', FormType::class, ['label' => false]);
        foreach ($options['entries'] as $entry) {
            $title->add($entry['locale'], TextType::class, ['data' => $entry['title'], 'label' => 'Title', 'required' => \in_array($entry['locale'], $options['required_locales'], true)]);
            $body->add($entry['locale'], TextareaType::class, ['data' => $entry['body'], 'label' => 'Body', 'required' => false, 'attr' => ['rows' => 2]]);
        }
        $builder->add($title)->add($body);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entries' => [],
            'required_locales' => [],
            'method' => 'GET',
            'csrf_protection' => false,
            'translation_domain' => false,
        ]);
        $resolver->setAllowedTypes('entries', 'array');
        $resolver->setAllowedTypes('required_locales', 'string[]');
    }
}
