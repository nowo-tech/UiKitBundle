<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * GET per-page form of the compact pagination (2.19, REQ-TWIG-005): the other query parameters as hidden fields
 * (`fields`, see {@see HiddenFieldsType}) and one select named `per_page_param` (default `per_page`) with `choices`,
 * `per_page` selected. Nameless root: the request stays `?status=x&per_page=25`. The select keeps its 2.x id
 * (`select_id`), attributes (`select_attr`: class, `data-nowo-ui-autosubmit`, theme slot attributes) and label (`select_label`, `select_label_class`).
 *
 * @extends AbstractType<null>
 */
final class PerPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<int|string> $choices */
        $choices = $options['choices'];
        $selected = \in_array((string) $options['per_page'], array_map(strval(...), $choices), true) ? $options['per_page'] : null;
        $builder->add($options['per_page_param'], ChoiceType::class, [
            'choices' => array_combine(array_map(strval(...), $choices), $choices),
            'data' => $selected,
            'required' => false,
            'placeholder' => null,
            'mapped' => false,
            'choice_translation_domain' => false,
            'label' => $options['select_label'],
            'label_attr' => ['class' => $options['select_label_class']],
            'attr' => $options['select_attr'],
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->children[$options['per_page_param']]->vars['id'] = $options['select_id'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'per_page_param' => 'per_page',
            'choices' => [],
            'per_page' => null,
            'select_id' => 'nowo-ui-pagination-per-page',
            'select_attr' => [],
            'select_label' => false,
            'select_label_class' => '',
            'method' => 'GET',
        ]);
        $resolver->setAllowedTypes('per_page_param', 'string');
        $resolver->setAllowedTypes('choices', 'array');
        $resolver->setAllowedTypes('per_page', ['null', 'int', 'string']);
        $resolver->setAllowedTypes('select_id', 'string');
        $resolver->setAllowedTypes('select_attr', 'array');
        $resolver->setAllowedTypes('select_label', ['bool', 'string']);
        $resolver->setAllowedTypes('select_label_class', 'string');
    }

    public function getParent(): string
    {
        return HiddenFieldsType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'nowo_ui_per_page';
    }
}
