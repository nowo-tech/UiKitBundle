<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * View model of `@NowoUiKit/page/wizard.html.twig`: steps indicator, the current step's form, back / next.
 * Bundles may override `nowo_ui_wizard_step` (default: REQ-TWIG-003 child loop of `form`).
 */
final readonly class WizardPage
{
    /** @var list<WizardStep> */
    public array $steps;

    /**
     * @param iterable<WizardStep>                      $steps
     * @param array<string, string|int|float|bool|null> $attributes extra attributes of the scaffold root (data-testid…; no on* handlers)
     */
    public function __construct(
        public PageHeader $header,
        iterable $steps,
        public string $current,
        public ?FormView $form = null,
        public ?string $backHref = null,
        public Label|string|null $nextLabel = null,
        public Label|string|null $backLabel = null,
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
        $this->steps = ViewList::of($steps, WizardStep::class, 'WizardPage steps');
        if (null === $this->currentIndex()) {
            throw new \InvalidArgumentException(\sprintf('WizardPage current step "%s" is not one of its steps.', $current));
        }
    }

    /**
     * 0-based index of the current step (null when unknown).
     */
    public function currentIndex(): ?int
    {
        foreach ($this->steps as $index => $step) {
            if ($step->id === $this->current) {
                return $index;
            }
        }

        return null;
    }

    /**
     * `done`, `current` or `upcoming`.
     */
    public function status(WizardStep $step): string
    {
        $index = array_search($step, $this->steps, true);
        $current = (int) $this->currentIndex();

        return match (true) {
            false === $index, $index > $current => 'upcoming',
            $index === $current => 'current',
            default => 'done',
        };
    }
}
