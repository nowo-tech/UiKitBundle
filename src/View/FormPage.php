<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * View model of `@NowoUiKit/page/form.html.twig`: header, form (`form_start` / child loop / `form_end`),
 * submit / cancel / secondary actions and an optional delete zone. Bundles may override `nowo_ui_form_body`
 * (default: REQ-TWIG-003 child loop) and `nowo_ui_form_aside`.
 */
final readonly class FormPage
{
    /** @var list<Action> */
    public array $secondaryActions;

    /**
     * @param iterable<Action> $secondaryActions
     */
    public function __construct(
        public PageHeader $header,
        public FormView $form,
        public Label|string|null $submitLabel = null,
        public ?string $cancelHref = null,
        public Label|string|null $cancelLabel = null,
        iterable $secondaryActions = [],
        public ?Action $deleteAction = null,
        public ?string $bundle = null,
        public ?string $layout = null,
    ) {
        $this->secondaryActions = ViewList::of($secondaryActions, Action::class, 'FormPage secondaryActions');
    }
}
