# Release

Maintainers: checklist before creating a new tag.

## Pre-release

1. Update [CHANGELOG.md](CHANGELOG.md) (version section + compare links) and [UPGRADING.md](UPGRADING.md) if behaviour changes.
2. Run quality checks:

   ```bash
   composer qa
   composer phpstan
   composer test
   ```

3. Commit on a clean tree (no Cursor co-author trailers — REQ-GIT-001):

   ```bash
   git status
   git add -A && git commit -m "Release vX.Y.Z"
   git push origin main
   ```

## Tag and publish

4. Annotated tag and push:

   ```bash
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   git push origin vX.Y.Z
   ```

5. Create the GitHub Release from the tag (notes from the version section in CHANGELOG), e.g.:

   ```bash
   gh -R nowo-tech/UiKitBundle release create vX.Y.Z --title "vX.Y.Z" --notes "$(sed -n '/^## \[X.Y.Z\]/,/^## \[/p' docs/CHANGELOG.md | head -n -1)"
   ```

   Prefer `gh -R nowo-tech/UiKitBundle …` if the local remote hostname is not recognized by `gh`.

6. Packagist: first publish submit `https://github.com/nowo-tech/UiKitBundle`; later tags update automatically (or use “Update” on Packagist).
