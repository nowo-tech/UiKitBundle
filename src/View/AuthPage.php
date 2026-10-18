<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * View model of `@NowoUiKit/page/auth.html.twig` (guest layout): brand, panel heading, form, footer links.
 * Bundles may override `nowo_ui_auth_fields` (default: REQ-TWIG-003 child loop), `nowo_ui_auth_actions` and
 * `nowo_ui_auth_footer`.
 * `scripts` (2.2): false renders the page without any UiKit script nor the theme boot (JS-free guest pages such as
 * a public 503; the UiKit document layout reads `nowo_ui_scripts_enabled`).
 *
 * 2.3: `formThemes` (applied to `form` with `form_theme`), `formAttributes` (extra `<form>` attributes, `class`
 * appended to the `form` slot), `documentTitle` (`<title>` when it differs from the panel heading); the root form
 * errors (CSRF, global errors) render above the fields in the `form_errors` slot; data blocks `nowo_ui_auth_body`
 * (the whole form region) and `nowo_ui_auth_after_form`. Without a form the scaffold needs no symfony/form.
 */
final readonly class AuthPage
{
    /** @var list<Link> */
    public array $links;

    /** @var list<string> */
    public array $formThemes;

    /**
     * @param iterable<Link>                            $links          footer links (forgot password, register, back to site…)
     * @param array<string, string|int|float|bool|null> $attributes     extra attributes of the scaffold root (data-testid…; no on* handlers)
     * @param iterable<string>                          $formThemes     form theme templates applied to `form` (2.3)
     * @param array<string, string|int|float|bool|null> $formAttributes extra `<form>` attributes (2.3; `class` is appended, no on* handlers)
     * @param Label|string|null                         $documentTitle  document `<title>` (2.3; default: `title`)
     */
    public function __construct(
        public Label|string $title,
        public ?FormView $form = null,
        public Label|string|null $intro = null,
        public Label|string|null $brand = null,
        public Label|string|null $error = null,
        public Label|string|null $submitLabel = null,
        iterable $links = [],
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
        public bool $scripts = true,
        iterable $formThemes = [],
        public array $formAttributes = [],
        public Label|string|null $documentTitle = null,
    ) {
        Action::assertAttributes($attributes);
        Action::assertAttributes($formAttributes);
        $this->formThemes = FormPage::themes($formThemes, 'AuthPage formThemes');
        $this->links = ViewList::of($links, Link::class, 'AuthPage links');
    }
}
