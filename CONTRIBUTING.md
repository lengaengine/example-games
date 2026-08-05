# Contributing To Lenga Samples

The sample projects exist to demonstrate complete, understandable Lenga workflows. Keep changes focused on a game or on a deliberate example of an engine feature.

## Before You Submit

1. Open the affected project in a compatible Lenga Editor build.
2. Confirm its configured entry scene loads and runs without new console errors.
3. Run `composer validate --strict` in the project directory.
4. Lint changed PHP files with `php -l`.
5. Keep `ProjectSettings/.asset-imports.json` in sync when imported assets change.
6. Run `php tools/sync-engine-version.php --check` from the repository root.

Do not commit `vendor/`, exported builds, saved runtime data, IDE metadata, `imgui.ini`, or other machine-local state. Retain source and license information for every third-party asset you add.

Use a Conventional Commit subject such as `fix(pong): preserve paddle input after pause` or `feat(rollerworld): add collectible feedback`.

Changes to a sample game are versioned here, independently of the Lenga engine. Engine defects and engine-wide feature work belong in the main Lenga repository.

Do not edit one sample's `lenga/engine` constraint independently. `ENGINE_VERSION`, all Composer manifests, and all lock files are synchronized together after an engine release.
