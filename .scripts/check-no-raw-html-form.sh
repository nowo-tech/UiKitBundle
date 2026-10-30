#!/usr/bin/env bash
# REQ-TWIG-005 — fail when bundle views or demo templates use raw <form> / <input> / <textarea> / <select> markup
# instead of Symfony Forms (form_start / form_row / form_widget / form_end). Allowed (documented exceptions):
#   - form themes under src/Resources/views/form/ (they implement Symfony form blocks and emit the controls);
#   - the deprecated raw fallbacks of the partials (ALLOWLIST below; only rendered without symfony/form, with a
#     deprecation, removed in 3.0 — docs/UPGRADING.md "To 2.18.0" / "To 2.19.0");
#   - a control that is never submitted, when the line above it carries `{# twig-005-ignore: <reason> #}` (the
#     nameless type-to-confirm input of the confirm partial / page).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ALLOWLIST=(
  src/Resources/views/partials/_action_post_legacy.html.twig
  src/Resources/views/partials/_filters_legacy.html.twig
  src/Resources/views/partials/_locale_switcher_legacy.html.twig
  src/Resources/views/partials/_pagination_per_page_legacy.html.twig
  src/Resources/views/partials/_row_actions_legacy.html.twig
  src/Resources/views/partials/_segmented_legacy_checkbox.html.twig
  src/Resources/views/partials/_user_menu_logout_legacy.html.twig
)

dirs=()
[[ -d src/Resources/views ]] && dirs+=(src/Resources/views)
for d in demo/*/templates; do [[ -d "$d" ]] && dirs+=("$d"); done
if [[ ${#dirs[@]} -eq 0 ]]; then
  echo "check-no-raw-html-form: no Twig views — OK"
  exit 0
fi

matches=""
while IFS= read -r hit; do
  [[ -n "$hit" ]] || continue
  file="${hit%%:*}"; rest="${hit#*:}"; line="${rest%%:*}"
  [[ "$file" == src/Resources/views/form/* ]] && continue
  allowed=0
  for a in "${ALLOWLIST[@]}"; do [[ "$file" == "$a" ]] && allowed=1 && break; done
  [[ "$allowed" -eq 1 ]] && continue
  if [[ "$line" -gt 1 ]] && sed -n "$((line - 1))p" "$file" | grep -q 'twig-005-ignore'; then
    continue
  fi
  matches+="$hit"$'\n'
done < <(grep -RInE '<(form|input|textarea|select)([[:space:]>]|$)' "${dirs[@]}" --include='*.twig' || true)

if [[ -n "$matches" ]]; then
  echo "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):" >&2
  printf '%s' "$matches" >&2
  exit 1
fi

echo "check-no-raw-html-form: OK (${dirs[*]})"
