<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.60 component catalogue on the demo platform shell: the reference every bundle demo follows. One page per
 * group (layout, cards and tables, forms, feedback states, navigation, dialogs, badges and tokens), each built only from
 * the blocks the rollout brief tells demos to use, with UiKit's default theme (no host tokens, no demo CSS). Stateless:
 * the group comes from the route, the form is built per request and never submitted. The profiler toolbar is off for
 * these pages, so the axe and layout checks only see the page.
 */
final class CatalogueController extends AbstractController
{
    /** What a demo's FormKit `demo` profile gives every control. */
    private const INPUT = ['class' => 'nowo-ui-input'];

    public const GROUPS = ['layout', 'cards-tables', 'forms', 'feedback', 'navigation', 'dialogs', 'badges-tokens'];

    public function __construct(
        #[Autowire(service: 'profiler')]
        private readonly ?Profiler $profiler = null,
    ) {
    }

    #[Route('/catalogue/{group}', name: 'catalogue', requirements: ['group' => 'layout|cards-tables|forms|feedback|navigation|dialogs|badges-tokens'], defaults: ['group' => 'layout'])]
    public function __invoke(string $group, FormFactoryInterface $forms): Response
    {
        $this->profiler?->disable();
        $context = ['group' => $group];
        if ('forms' === $group) {
            $context['catalogue_form'] = $this->contactForm($forms, 'catalogue_contact')->createView();
            // The same form after a failed submit: Symfony marks the fields aria-invalid and lists their errors.
            $invalid = $this->contactForm($forms, 'catalogue_invalid');
            $invalid->submit(['name' => '', 'email' => 'ada@', 'topic' => 'support', 'message' => '', 'consent' => null]);
            $invalid->get('name')->addError(new FormError('Enter your name.'));
            $invalid->get('email')->addError(new FormError('Enter a valid email address, like ada@example.com.'));
            $context['catalogue_invalid'] = $invalid->createView();
            $context['catalogue_states'] = $forms->createNamedBuilder('catalogue_states', options: ['attr' => ['class' => 'nowo-ui-form']])
                ->add('reference', TextType::class, ['label' => 'Reference (read-only)', 'data' => 'INV-1042', 'attr' => self::INPUT + ['readonly' => true], 'required' => false])
                ->add('owner', TextType::class, ['label' => 'Owner (disabled)', 'data' => 'Ada Lovelace', 'disabled' => true, 'attr' => self::INPUT, 'required' => false])
                ->getForm()
                ->createView();
        }

        return $this->render('catalogue/'.str_replace('-', '_', $group).'.html.twig', $context);
    }

    private function contactForm(FormFactoryInterface $forms, string $name): \Symfony\Component\Form\FormInterface
    {
        return $forms->createNamedBuilder($name, options: ['attr' => ['class' => 'nowo-ui-form']])
            ->add('name', TextType::class, ['label' => 'Name', 'help' => 'As it appears on the invoice.', 'attr' => self::INPUT])
            ->add('email', EmailType::class, ['label' => 'Email', 'attr' => self::INPUT])
            ->add('topic', ChoiceType::class, ['label' => 'Topic', 'choices' => ['Billing' => 'billing', 'Support' => 'support', 'Sales' => 'sales'], 'attr' => self::INPUT])
            ->add('message', TextareaType::class, ['label' => 'Message', 'required' => false, 'help' => 'Optional.', 'attr' => self::INPUT])
            ->add('consent', CheckboxType::class, ['label' => 'I agree to be contacted'])
            ->getForm();
    }
}
