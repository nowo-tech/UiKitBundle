<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * GET search filter of the `filters` partial (2.19, REQ-TWIG-005): one search field named `q_name` (default `q`)
 * under a nameless root, so the query stays `?q=…` (bookmarks keep working). No CSRF (GET filter). The input keeps
 * the 2.x id `nowo-ui-filter-q` (`input_id`), its class and placeholder; the label is visually hidden.
 *
 * @extends AbstractType<null>
 */
final class SearchFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add($options['q_name'], SearchType::class, [
            'data' => $options['q'],
            'required' => false,
            'mapped' => false,
            'label' => $options['placeholder'],
            'label_attr' => ['class' => 'visually-hidden'],
            'attr' => ['class' => $options['input_class'], 'placeholder' => $options['placeholder'], 'autocomplete' => 'off'],
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->children[$options['q_name']]->vars['id'] = $options['input_id'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'q_name' => 'q',
            'q' => '',
            'placeholder' => '',
            'input_class' => '',
            'input_id' => 'nowo-ui-filter-q',
            'method' => 'GET',
            'csrf_protection' => false,
            'data_class' => null,
            'translation_domain' => false,
        ]);
        $resolver->setAllowedTypes('q_name', 'string');
        $resolver->setAllowedTypes('q', ['null', 'string']);
        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('input_class', 'string');
        $resolver->setAllowedTypes('input_id', 'string');
    }

    public function getBlockPrefix(): string
    {
        return 'nowo_ui_search_filter';
    }
}
