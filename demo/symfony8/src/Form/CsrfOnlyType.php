<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;

/**
 * Field-less POST form of a confirm-only action (the "Void invoice" dialog of the app shell demo): the template
 * renders it with form_start / form_end (REQ-TWIG-005); the Form adds its CSRF token when symfony/security-csrf is on.
 *
 * @extends AbstractType<null>
 */
final class CsrfOnlyType extends AbstractType
{
}
