# Adoption — consolidating Nowo admin Twig onto UiKit

Phase D of the [roadmap](ROADMAP.md): feature bundles and host apps (especially **symfony-beacon**) should prefer this kit over local `_ui_macros` / duplicated `nowo-ui.css`.

## Table of contents

- [Feature bundles](#feature-bundles)
- [symfony-beacon](#symfony-beacon)
- [Config alignment](#config-alignment)
- [Override vs tokens](#override-vs-tokens)

## Feature bundles

1. Add Composer dependency: `"nowo-tech/ui-kit-bundle": "^1.4"` (`require` or `suggest` during migration).
2. Replace local macros:

```twig
{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}
```

3. Include kit partials (`_pagination`, `_tabs`, `_empty`, `_row_actions`, `_filters`, `_card`, `_modal_shell`, `_confirm`, `_toasts`) instead of copying markup. Row CRUD clusters **MUST** use `_row_actions` ([ROW_ACTIONS.md](ROW_ACTIONS.md)).
4. Load assets once in the admin layout (or document that the host must):

```twig
<link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
<script src="{{ asset('js/nowo-ui-modal.js', 'nowo_ui_kit') }}" defer></script>
{# plus toast / confirm / shell / theme as needed #}
```

5. Delete private `_ui_macros.html.twig` / vendored `nowo-ui.css` copies when no longer referenced.
6. Keep domain routes, ACL, and CRUD in the feature bundle (UiKit has no manage routes).

**Candidates:** DashboardMenu, BreadcrumbKit, CookieConsent admin, RoutingKit, UptimeMonitor, Workflow, Yopass, HttpLog, SiteBackup, Vault, ApiStudio (`as-*` → `nowo-ui-*` when touching UI).

## symfony-beacon

Recommended migration order:

1. Depend on UiKit; keep Beacon `app-shell` as the product chrome initially.
2. Point kit admin layouts at UiKit macros/partials; remap `--nowo-ui-*` under `.kit-admin` (already started).
3. Swap Beacon `_toasts` / confirm / page-loader markup for kit partials when contracts match ([STIMULUS.md](STIMULUS.md)).
4. Prefer kit IIFEs **or** re-export Stimulus peers from `vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/` and delete duplicated host controllers (`clipboard_copy`, `confirm_*`, `page_loader`, `toast_stack`, `tabs`).
5. Optionally thin `_tabs`, filters, cards toward kit equivalents.
6. Leave product-only pieces in Beacon (Mercure, charts, product tour, `password-confirm-mirror`). Thinking Orb is available in the kit via `_thinking_orb` / page loader `visual: orb` + `nowo-ui-orb.js` ([THIRD_PARTY.md](THIRD_PARTY.md)).

## Config alignment

- Prefer a single host setting: `nowo_ui_kit.css_framework` / `icon_set` / `row_actions_display`.
- Use `_row_actions` so hosts switch icon vs text labels without forking list templates.
- Until feature configs are unified, pass the feature’s framework into macros: `ui.btn('primary', null, feature_fw)`.

Beacon-style design system:

```yaml
nowo_ui_kit:
    css_framework: custom
    icon_set: ux_icon
```

## Override vs tokens

| Prefer | Avoid |
|--------|--------|
| Remap `--nowo-ui-*` CSS variables | Forking every kit Twig under `templates/bundles/NowoUiKitBundle/` |
| Surgical override of one partial | Copying entire kitchen sink into the app |

See [USAGE.md](USAGE.md) — Override vs upgrade.
