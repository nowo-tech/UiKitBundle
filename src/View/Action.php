<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * Page / row action (REQ-UI-003). Rendered by `partials/_action.html.twig` (toolbar button or row action) and
 * accepted by `_row_actions` next to the 1.x hashes.
 *
 * Target: `href`, or `route` + `params` (resolved with `path()` by the partial). Non-GET actions render a POST
 * form: preferably a CSRF-only `form` (FormView, REQ-TWIG-005), else `csrfToken` as a hidden field; methods other
 * than GET/POST are spoofed with `_method`. With `confirm`, the action opens the UiKit confirm `<dialog>` and the
 * dialog's confirm button submits (no inline `confirm()`).
 *
 * Implements `ArrayAccess` with the 1.x `_row_actions` keys of {@see self::toArray()} (read-only: `csrf_token`,
 * `confirm_id`, `modal_id`, `attr`…), so host overrides of `_row_actions` written for 1.x read them from the object.
 *
 * `display` (`icon` | `text` | `icon_text`, {@see self::DISPLAYS}): null = the context default (toolbar: icon + text
 * when an icon is set, else text; row: `nowo_ui_kit.row_actions_display`). Icon-only buttons keep the label
 * visually hidden plus `aria-label` / `title`, and get the `btn_icon` slot in toolbar context.
 *
 * Confirm dialog id ({@see self::confirmId()}): an explicit {@see Confirm::$id} is used as is; otherwise it is
 * derived from the action target, the CSRF token and the whole Confirm content ({@see self::confirmKey()}) plus the
 * `confirmScope` — the list scaffold scopes every row action by its row key ({@see ListPage::rowActions()}), so two
 * rows never share a dialog id.
 *
 * `fallbackHref` (2.1): no-JS fallback of an action with a Confirm — a `<noscript>` link to that URL (usually a
 * `page/confirm` page of the same action) is printed next to the dialog toggle, in toolbars and row actions alike.
 * `dialogId` (2.2): the action opens the UiKit `dialog` partial with that id — a `<button type="button">` with
 * `data-nowo-ui-dialog-open`, `aria-haspopup="dialog"` and `aria-controls` (nowo-ui-dialog.js). Add
 * `data-nowo-ui-dialog-replace` to `attributes` when the button sits inside another dialog it should replace. With
 * an `href` as well, the opener is a link (no-JS fallback) whose URL is fetched into the dialog body
 * (`data-nowo-ui-dialog-src`, same origin only).
 * `title` (2.2): translatable tooltip (`title` attribute; the accessible name stays the label), also shown on
 * disabled actions (wrapped in a `<span title>`).
 *
 * @implements \ArrayAccess<array-key, mixed>
 */
final readonly class Action implements \ArrayAccess
{
    /** Canonical kinds (REQ-UI-003) plus the 1.x row-action kinds (docs/ROW_ACTIONS.md). */
    public const KINDS = ['create', 'edit', 'delete', 'view', 'export', 'import', 'custom', 'copy', 'save', 'cancel', 'restore', 'share', 'config', 'filter', 'prev', 'next', 'close'];

    /** `neutral` = the default of kinds without a variant of their own (painted like secondary, own row slot). */
    public const VARIANTS = ['primary', 'secondary', 'danger', 'neutral', 'outline', 'link'];

    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** Display modes: icon only (visually-hidden label + aria-label), text only, icon + text. */
    public const DISPLAYS = ['icon', 'text', 'icon_text'];

    public string $method;

    /**
     * @param array<string, mixed>                      $params     route parameters
     * @param array<string, string|int|float|bool|null> $attributes extra HTML attributes (no on* handlers)
     */
    public function __construct(
        public string $kind = 'custom',
        public Label|string|null $label = null,
        public ?string $href = null,
        public ?string $route = null,
        public array $params = [],
        string $method = 'GET',
        public ?string $csrfToken = null,
        public ?Confirm $confirm = null,
        public ?string $icon = null,
        public ?string $variant = null,
        public array $attributes = [],
        public ?FormView $form = null,
        public string $csrfField = '_token',
        public ?string $modalId = null,
        public bool $disabled = false,
        public bool $visible = true,
        public ?string $display = null,
        public ?string $confirmScope = null,
        public ?string $fallbackHref = null,
        public ?string $dialogId = null,
        public Label|string|null $title = null,
    ) {
        if (null !== $dialogId && 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_:.-]*$/', $dialogId)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Action dialogId "%s".', $dialogId));
        }
        if (!\in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Action kind "%s" (%s).', $kind, implode('|', self::KINDS)));
        }
        if (null !== $variant && !\in_array($variant, self::VARIANTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Action variant "%s" (%s).', $variant, implode('|', self::VARIANTS)));
        }
        $method = strtoupper($method);
        if (!\in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Action method "%s" (%s).', $method, implode('|', self::METHODS)));
        }
        if (null !== $display && !\in_array($display, self::DISPLAYS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Action display "%s" (%s).', $display, implode('|', self::DISPLAYS)));
        }
        if (null !== $href && null !== $route) {
            throw new \InvalidArgumentException('An Action takes either href or route, not both.');
        }
        self::assertAttributes($attributes);
        $this->method = $method;
    }

    /**
     * @return self Fluent interface
     */
    public static function create(string $href, Label|string|null $label = null): self
    {
        return new self('create', $label, $href, variant: 'primary');
    }

    /**
     * @return self Fluent interface
     */
    public static function edit(string $href, Label|string|null $label = null): self
    {
        return new self('edit', $label, $href);
    }

    /**
     * @return self Fluent interface
     */
    public static function view(string $href, Label|string|null $label = null): self
    {
        return new self('view', $label, $href);
    }

    /**
     * POST delete behind the UiKit confirm dialog ({@see Confirm::delete()} when no confirm is given).
     *
     * @return self Fluent interface
     */
    public static function delete(string $href, ?string $csrfToken = null, ?Confirm $confirm = null, Label|string|null $label = null, ?FormView $form = null): self
    {
        return new self('delete', $label, $href, method: 'POST', csrfToken: $csrfToken, confirm: $confirm ?? Confirm::delete(), form: $form);
    }

    /**
     * Route-based action (the partial resolves `path(route, params)`).
     *
     * @param array<string, mixed> $params
     *
     * @return self Fluent interface
     */
    public static function route(string $kind, string $route, array $params = [], Label|string|null $label = null): self
    {
        return new self($kind, $label, route: $route, params: $params);
    }

    public function isPost(): bool
    {
        return 'GET' !== $this->method;
    }

    /**
     * Button variant: explicit, else danger for delete, primary for create/save, neutral otherwise (neutral paints
     * like secondary — toolbar `btn_secondary` — but row actions read the `action_neutral` slot, so an explicit
     * `secondary` can be styled apart).
     */
    public function resolvedVariant(): string
    {
        return $this->variant ?? match ($this->kind) {
            'delete' => 'danger',
            'create', 'save' => 'primary',
            default => 'neutral',
        };
    }

    /**
     * Stable id of the confirm dialog: the explicit {@see Confirm::$id}, else `nowo-ui-confirm-` + a hash of
     * {@see self::confirmKey()} and {@see self::$confirmScope}. Null without a Confirm.
     */
    public function confirmId(): ?string
    {
        if (!$this->confirm instanceof Confirm) {
            return null;
        }

        return $this->confirm->id ?? 'nowo-ui-confirm-'.substr(md5($this->confirmKey().'|'.($this->confirmScope ?? '')), 0, 12);
    }

    /**
     * Fingerprint of everything the confirm dialog renders (kind, method, target, CSRF token / field, disabled and
     * the whole {@see Confirm}): two actions with the same key render identical dialogs (the list scaffold renders
     * one dialog per id, {@see ListPage::confirmDialogs()}). Null without a Confirm.
     */
    public function confirmKey(): ?string
    {
        if (!$this->confirm instanceof Confirm) {
            return null;
        }
        $formToken = null;
        if ($this->form instanceof FormView && isset($this->form->children['_token'])) {
            $formToken = $this->form->children['_token']->vars['value'] ?? null;
        }

        return md5((string) json_encode([
            $this->kind, $this->method, $this->href, $this->route, $this->params, $this->csrfToken, $this->csrfField,
            $formToken, $this->disabled, $this->confirm,
        ], \JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /**
     * Copy scoped to a row (or any other discriminator) for the derived confirm dialog id.
     *
     * @return self Fluent interface
     */
    public function withConfirmScope(int|string|null $scope): self
    {
        return new self(
            $this->kind, $this->label, $this->href, $this->route, $this->params, $this->method, $this->csrfToken,
            $this->confirm, $this->icon, $this->variant, $this->attributes, $this->form, $this->csrfField,
            $this->modalId, $this->disabled, $this->visible, $this->display, null === $scope ? null : (string) $scope,
            $this->fallbackHref, $this->dialogId, $this->title,
        );
    }

    /**
     * 1.x `_row_actions` hash (labels stay {@see Label} objects; `_row_actions` translates them).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'href' => $this->href,
            'route' => $this->route,
            'params' => $this->params,
            'method' => $this->method,
            'csrf_token' => $this->csrfToken,
            'csrf_field' => $this->csrfField,
            'confirm_id' => $this->confirmId(),
            'icon' => $this->icon,
            'variant' => $this->variant,
            'attr' => $this->attributes,
            'modal_id' => $this->modalId,
            'disabled' => $this->disabled,
            'visible' => $this->visible,
            'fallback_href' => $this->fallbackHref,
            'dialog_id' => $this->dialogId,
            'title' => $this->title,
        ];
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    public static function assertAttributes(array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            $name = (string) $name;
            if (1 !== preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:.-]*$/', $name) || str_starts_with(strtolower($name), 'on')) {
                throw new \InvalidArgumentException(\sprintf('Invalid or unsafe attribute "%s" (no on* handlers: CSP).', $name));
            }
            if (null !== $value && !\is_scalar($value)) {
                throw new \InvalidArgumentException(\sprintf('Attribute "%s" must be a scalar, bool or null.', $name));
            }
        }
    }

    /**
     * 1.x `_row_actions` hash key (`action['csrf_token']` / `action.csrf_token` in Twig): see {@see self::toArray()}.
     */
    public function offsetExists(mixed $offset): bool
    {
        return null !== $this->offsetGet($offset);
    }

    /**
     * Value of a 1.x key ({@see self::toArray()}), null when unknown.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return \is_string($offset) ? ($this->toArray()[$offset] ?? null) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException(self::class.' is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException(self::class.' is immutable.');
    }
}
