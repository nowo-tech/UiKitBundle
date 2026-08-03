# Symfony Flex recipe

Ui Kit ships a **recipe stub** under [`.symfony/recipe/nowo-tech/ui-kit-bundle/1.0/`](../.symfony/recipe/nowo-tech/ui-kit-bundle/1.0/) for maintainers and for a future PR to [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib).

Until the recipe is published on recipes-contrib, Flex users must register the bundle and config manually — see [INSTALLATION.md](INSTALLATION.md).

## Recipe contents (1.0)

| File | Purpose |
|------|---------|
| `manifest.json` | Register `NowoUiKitBundle`; copy `config/` into the project (**no routes**) |
| `config/packages/nowo_ui_kit.yaml` | Default `css_framework: bootstrap5` and `icon_set: bootstrap-icons` |
| `post-install.txt` | Short install summary printed by Flex |

## Submit to recipes-contrib

1. Fork [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib).
2. Copy the `1.0/` folder to `nowo-tech/ui-kit-bundle/1.0/` in that fork (same layout as other vendor recipes).
3. Open a PR following the [recipes-contrib contributing guide](https://github.com/symfony/recipes-contrib/blob/main/CONTRIBUTING.md).
4. After merge, Packagist + Flex will apply the recipe on `composer require nowo-tech/ui-kit-bundle`.

Keep the stub in this repository in sync with any upstream recipe changes.

## Local testing (optional)

Copy the YAML into a throwaway Symfony app and verify `bin/console debug:config nowo_ui_kit` plus Twig macros from `@NowoUiKitBundle/macros/ui.html.twig`.
