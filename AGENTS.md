# Lenga Samples Guardrails

These projects demonstrate Lenga workflows and are versioned independently from the engine, PHP scripting API, and website repositories.

## Working Agreements

- Keep changes focused on a sample game or a deliberate example of an engine feature. Engine-wide fixes belong in the main Lenga repository.
- Preserve each project's `Assets`, `ProjectSettings`, `bootstrap.php`, `composer.json`, and `composer.lock` as portable source data.
- Keep `ProjectSettings/.asset-imports.json` synchronized when imported assets change; editor and export tooling use it as dependency metadata.
- Do not commit Composer dependencies, build exports, runtime saves, IDE metadata, logs, caches, or `imgui.ini`.
- Do not add third-party media without preserving its source and license terms. Update `THIRD_PARTY_NOTICES.md` when applicable.
- Validate affected PHP with `php -l`, run `composer validate --strict`, and open the affected project in a compatible Lenga Editor build before sharing changes.
- Use Conventional Commits, preferably with the sample as scope, such as `fix(pong): restore paddle input`.

Avoid presenting these projects as engine release gates or as prebuilt public game downloads. Their purpose is to provide editable source examples that help developers understand and combine Lenga features.
