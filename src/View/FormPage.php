<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * View model of `@NowoUiKit/page/form.html.twig`: header, form (`form_start` / child loop / `form_end`),
 * submit / cancel / secondary actions and an optional delete zone. Bundles may override `nowo_ui_form_body`
 * (default: REQ-TWIG-003 child loop) and `nowo_ui_form_aside`.
 *
 * `secondaryActionsInHeader` (default true) renders the secondary actions in the page-header toolbar after the
 * header actions; false keeps them in a toolbar below the form. `dangerZoneTitle` / `dangerZoneIntro` label the
 * delete zone (default title `form.danger_zone`). `formThemes` (2.1) are applied to the form with `form_theme` before
 * the scaffold renders it (e.g. the bundle's FormKit theme), so bundles need not override `nowo_ui_content`.
 * Body-only mode (2.2): `form` null, or `formTag: false`, renders only the `nowo_ui_form_body` block inside the form
 * panel — no `<form>`, no submit / cancel row — for a component that renders its own form (e.g. a Live component).
 * `formAttributes` (2.3): extra `<form>` attributes (`novalidate`, `data-turbo`…; `class` is appended to the `form`
 * slot).
 */
final readonly class FormPage
{
    /** @var list<Action> */
    public array $secondaryActions;

    /** @var list<string> */
    public array $formThemes;

    /**
     * @param iterable<Action>                          $secondaryActions
     * @param array<string, string|int|float|bool|null> $attributes       extra attributes of the scaffold root (data-testid…; no on* handlers)
     * @param iterable<string>                          $formThemes       form theme templates applied to `form` (`form_theme`)
     * @param array<string, string|int|float|bool|null> $formAttributes   extra `<form>` attributes (2.3; `class` is appended, no on* handlers)
     */
    public function __construct(
        public PageHeader $header,
        public ?FormView $form,
        public Label|string|null $submitLabel = null,
        public ?string $cancelHref = null,
        public Label|string|null $cancelLabel = null,
        iterable $secondaryActions = [],
        public ?Action $deleteAction = null,
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
        public bool $secondaryActionsInHeader = true,
        public Label|string|null $dangerZoneTitle = null,
        public Label|string|null $dangerZoneIntro = null,
        iterable $formThemes = [],
        public bool $formTag = true,
        public array $formAttributes = [],
    ) {
        Action::assertAttributes($attributes);
        Action::assertAttributes($formAttributes);
        $this->formThemes = self::themes($formThemes, 'FormPage formThemes');
        $this->secondaryActions = ViewList::of($secondaryActions, Action::class, 'FormPage secondaryActions');
    }

    /**
     * @internal validated list of form theme template names (also used by {@see SettingsSection})
     *
     * @param iterable<mixed> $themes
     *
     * @return list<string>
     */
    public static function themes(iterable $themes, string $what): array
    {
        $list = [];
        foreach ($themes as $theme) {
            if (!\is_string($theme) || '' === trim($theme)) {
                throw new \InvalidArgumentException(\sprintf('%s: expected template names (non-empty strings), got %s.', $what, get_debug_type($theme)));
            }
            $list[] = $theme;
        }

        return $list;
    }
}
