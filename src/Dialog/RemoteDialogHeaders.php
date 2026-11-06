<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use InvalidArgumentException;

/**
 * The three header names of the remote form dialog contract (2.33, `nowo_ui_kit.remote_dialog.*_header`): the request
 * header the dialog sends (`X-Nowo-Dialog: 1`), the success answer that closes it and navigates
 * (`204` + `X-Nowo-Redirect`) and the step answer that loads the next step into the same dialog
 * (`204` + `X-Nowo-Dialog-Location`). A host that already speaks its own names (`X-Acme-Dialog`…) sets them once; the
 * responder, the redirect listener, `nowo_ui_dialog_request()` and the `remote_form_dialog` partial read them.
 *
 * Names are HTTP header tokens of letters, digits and `-` (at most 64 characters), all three different
 * (case-insensitive). Immutable.
 */
final readonly class RemoteDialogHeaders
{
    public const DEFAULT_REQUEST = 'X-Nowo-Dialog';

    public const DEFAULT_REDIRECT = 'X-Nowo-Redirect';

    public const DEFAULT_LOCATION = 'X-Nowo-Dialog-Location';

    private const NAME = '/^[A-Za-z][A-Za-z0-9-]{0,63}$/';

    /**
     * @throws InvalidArgumentException when a name is not a header token or two names are the same
     */
    public function __construct(
        public string $request = self::DEFAULT_REQUEST,
        public string $redirect = self::DEFAULT_REDIRECT,
        public string $location = self::DEFAULT_LOCATION,
    ) {
        $error = self::invalid($request, $redirect, $location);
        if (null !== $error) {
            throw new InvalidArgumentException($error);
        }
    }

    /** The kit's own names (`X-Nowo-*`). */
    public static function defaults(): self
    {
        return new self();
    }

    /** Whether these are the kit's own names (the partial then prints nothing extra). */
    public function isDefault(): bool
    {
        return self::DEFAULT_REQUEST === $this->request && self::DEFAULT_REDIRECT === $this->redirect && self::DEFAULT_LOCATION === $this->location;
    }

    /**
     * The `data-nowo-ui-remote-form-*-header` attributes of a remote form `<dialog>` for the names that differ from
     * the defaults (empty with the defaults, so the default markup is unchanged). Both drivers read them.
     *
     * @return array<string, string>
     */
    public function dialogAttributes(): array
    {
        return array_filter([
            'data-nowo-ui-remote-form-request-header' => self::DEFAULT_REQUEST === $this->request ? null : $this->request,
            'data-nowo-ui-remote-form-redirect-header' => self::DEFAULT_REDIRECT === $this->redirect ? null : $this->redirect,
            'data-nowo-ui-remote-form-location-header' => self::DEFAULT_LOCATION === $this->location ? null : $this->location,
        ], static fn (?string $value): bool => null !== $value);
    }

    /** The validation error of a set of names, or null when they are valid (also used by the configuration). */
    public static function invalid(string $request, string $redirect, string $location): ?string
    {
        foreach (['request' => $request, 'redirect' => $redirect, 'location' => $location] as $role => $name) {
            if (1 !== preg_match(self::NAME, $name)) {
                return \sprintf('The remote dialog %s header "%s" must be a header name of letters, digits and "-" (at most 64 characters, starting with a letter).', $role, $name);
            }
        }
        if (3 !== \count(array_unique(array_map(strtolower(...), [$request, $redirect, $location])))) {
            return 'The remote dialog request, redirect and location headers must be three different names.';
        }

        return null;
    }
}
