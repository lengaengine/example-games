# Lenga Samples Guardrails

These projects demonstrate Lenga workflows and are versioned independently from the engine, PHP scripting API, and website repositories.

## Working Agreements

- Keep changes focused on a sample game or a deliberate example of an engine feature. Engine-wide fixes belong in the main Lenga repository.
- Preserve each project's `Assets`, `ProjectSettings`, `bootstrap.php`, `composer.json`, and `composer.lock` as portable source data.
- Keep `ProjectSettings/.asset-imports.json` synchronized when imported assets change; editor and export tooling use it as dependency metadata.
- Keep `ENGINE_VERSION`, every `lenga/engine` Composer constraint, and every lock file aligned. Use `tools/sync-engine-version.php` rather than editing one project independently.
- Do not commit Composer dependencies, build exports, runtime saves, IDE metadata, logs, caches, or `imgui.ini`.
- Do not add third-party media without preserving its source and license terms. Update `THIRD_PARTY_NOTICES.md` when applicable.
- Validate affected PHP with `php -l`, run `composer validate --strict`, and open the affected project in a compatible Lenga Editor build before sharing changes.
- Use Conventional Commits, preferably with the sample as scope, such as `fix(pong): restore paddle input`.

## Remote Branch Policy

- Keep feature, fix, release-preparation, agent, and other topic branches local by default. Never push a non-`develop` branch to GitHub or another remote unless the user explicitly instructs you to push that named branch.
- The normal sharing workflow is to finish or merge local work into local `develop`, then push only `develop` to the remote `develop` branch. Do not push `main` directly unless the user explicitly instructs it.
- A request to implement, commit, continue, prepare a release, or create a branch is not permission to publish a topic branch. Do not infer remote-push permission from the broader task.
- Before every push, verify the current branch and use an explicit `develop:develop` refspec for the normal workflow. Never rely on a bare `git push` when it could publish another branch.
- If the user explicitly authorizes a remote topic branch, remove that remote branch after it is merged or closed unless the user asks to preserve it.

Avoid presenting these projects as engine release gates or as prebuilt public game downloads. Their purpose is to provide editable source examples that help developers understand and combine Lenga features.
