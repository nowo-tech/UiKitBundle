<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * One section (tab) of a {@see SettingsPage}: its own form, or bundle markup in `nowo_ui_settings_section`.
 * `formThemes` (2.1) are applied to the section form with `form_theme` before the scaffold renders it.
 */
final readonly class SettingsSection
{
    /** @var list<string> */
    public array $formThemes;

    /**
     * @param iterable<string> $formThemes form theme templates applied to `form` (`form_theme`)
     */
    public function __construct(
        public string $id,
        public Label|string $label,
        public ?string $href = null,
        public ?FormView $form = null,
        public Label|string|null $intro = null,
        public Label|string|null $submitLabel = null,
        iterable $formThemes = [],
    ) {
        $this->formThemes = FormPage::themes($formThemes, 'SettingsSection formThemes');
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid SettingsSection id "%s".', $id));
        }
    }
}
