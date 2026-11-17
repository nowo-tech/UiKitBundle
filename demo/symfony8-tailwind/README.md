# Ui Kit Bundle — Symfony 8 demo (Tailwind)

Same kitchen sink as `symfony8`, with `nowo_ui_kit.css_framework: tailwind`.

```bash
cp .env.example .env   # PORT=8093
make up
# open http://localhost:8093/
```

## Tailwind stylesheet

The demo serves `public/css/tailwind.css`, prebuilt by the Tailwind CLI (3.4.17, pinned in `package.json`, config in
`tailwind.config.js`; the same major as the former Play CDN) instead of loading the runtime JIT from
`cdn.tailwindcss.com`. The file is committed; `make build-css` rebuilds it (throwaway node container) and `make up`
builds it when it is missing. Rebuild after a template or component class change.
