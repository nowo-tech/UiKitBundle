<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * One section (tab) of a {@see SettingsPage}: its own form, or bundle markup in `nowo_ui_settings_section`.
 * `formThemes` (2.1) are applied to the section form with `form_theme` before the scaffold renders it.
 * `actions` (2.2): extra {@see Action}s rendered inline right after the submit button of the section form.
 * `icon` (2.17): the section tab's icon (canonical kind or icon name), shown per the host's tab style.
 */
final readonly class SettingsSection
{
    /** @var list<string> */
    public array $formThemes;

    /** @var list<Action> */
    public array $actions;

    /**
     * @param iterable<string> $formThemes form theme templates applied to `form` (`form_theme`)
     * @param iterable<Action> $actions    actions after the submit button (same row, inline)
     */
    public function __construct(
        public string $id,
        public Label|string $label,
        public ?string $href = null,
        public ?FormView $form = null,
        public Label|string|null $intro = null,
        public Label|string|null $submitLabel = null,
        iterable $formThemes = [],
        iterable $actions = [],
        public ?string $icon = null,
    ) {
        $this->actions = ViewList::of($actions, Action::class, 'SettingsSection actions');
        $this->formThemes = FormPage::themes($formThemes, 'SettingsSection formThemes');
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid SettingsSection id "%s".', $id));
        }
    }
}
