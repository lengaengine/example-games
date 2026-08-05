# Sample Release Automation

The sample projects track one exact `lenga/engine` dependency version after every published engine release. During active development, `main` may use `dev-develop`; `ENGINE_VERSION`, every project manifest, and every project lock file must always agree.

## Automated Flow

1. A release is published or marked as a prerelease in `lengaengine/php-engine`.
2. The PHP API release workflow verifies that its tag matches its `VERSION` file.
3. That workflow sends an `engine-released` repository dispatch event to the samples repository.
4. `sync-engine-release.yml` runs `tools/sync-engine-version.php` with the released version.
5. The synchronizer updates all Composer constraints, regenerates all lock files from the public PHP API repository, and updates `ENGINE_VERSION`.
6. Every sample is installed and validated before the workflow commits the synchronized files to `main`.
7. Normal samples CI runs again on that commit.

The dispatch is idempotent. Repeating it for a version already in use produces no commit.

## GitHub Configuration

Configure the following in the `lengaengine/php-engine` repository:

- Secret `SAMPLES_REPOSITORY_TOKEN`: a fine-grained token with permission to dispatch workflows in the samples repository.
- Optional variable `LENGA_SAMPLES_REPOSITORY`: the target in `owner/repository` form. It defaults to `lengaengine/samples`.

The samples repository must allow GitHub Actions to write repository contents because the receiving workflow commits regenerated dependency locks.

## Manual Recovery

If a dispatch fails after an engine release, run the `Sync Released Engine Version` workflow manually and provide the exact released version. A maintainer can also perform the same operation locally:

```bash
php tools/sync-engine-version.php 0.9.0
php tools/sync-engine-version.php --check
```

Review and commit `ENGINE_VERSION` plus every changed `composer.json` and `composer.lock`. Never substitute a local Composer `path` repository; public users must be able to install each sample from its published dependencies.
