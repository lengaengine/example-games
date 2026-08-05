<?php

declare(strict_types=1);

const ENGINE_PACKAGE = 'lenga/engine';
const ENGINE_REPOSITORY = 'https://github.com/lengaengine/php-engine.git';

$root = dirname(__DIR__);
$argument = trim($argv[1] ?? '');

if ($argument === '--check') {
    checkAlignment($root);
    exit(0);
}

$version = ltrim($argument, 'v');
if (!isReleaseVersion($version)) {
    fwrite(STDERR, "Usage: php tools/sync-engine-version.php <major.minor.patch>\n");
    exit(1);
}

$manifests = projectManifests($root);
foreach ($manifests as $manifest) {
    updateManifest($manifest, $version);
}

$composer = getenv('COMPOSER_BINARY') ?: 'composer';
foreach ($manifests as $manifest) {
    $projectDirectory = dirname($manifest);
    fwrite(STDOUT, sprintf("Updating %s to %s %s\n", basename($projectDirectory), ENGINE_PACKAGE, $version));

    $exitCode = runProcess([
        $composer,
        'update',
        ENGINE_PACKAGE,
        '--no-install',
        '--no-interaction',
        '--prefer-dist',
        '--no-progress',
    ], $projectDirectory);

    if ($exitCode !== 0) {
        fwrite(STDERR, sprintf("Composer failed for %s.\n", basename($projectDirectory)));
        exit($exitCode);
    }
}

if (file_put_contents($root . DIRECTORY_SEPARATOR . 'ENGINE_VERSION', $version . PHP_EOL) === false) {
    fwrite(STDERR, "Failed to update ENGINE_VERSION.\n");
    exit(1);
}

checkAlignment($root);
fwrite(STDOUT, sprintf("All samples now target Lenga Engine %s.\n", $version));

function isReleaseVersion(string $version): bool
{
    return preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) === 1;
}

function isSupportedVersion(string $version): bool
{
    return $version === 'dev-develop' || isReleaseVersion($version);
}

/**
 * @return list<string>
 */
function projectManifests(string $root): array
{
    $manifests = glob($root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'composer.json') ?: [];
    sort($manifests, SORT_STRING);

    if ($manifests === []) {
        fwrite(STDERR, "No sample Composer manifests were found.\n");
        exit(1);
    }

    return array_values($manifests);
}

function updateManifest(string $manifest, string $version): void
{
    $contents = file_get_contents($manifest);
    if ($contents === false) {
        fwrite(STDERR, "Failed to read {$manifest}.\n");
        exit(1);
    }

    try {
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Invalid JSON in {$manifest}: {$exception->getMessage()}\n");
        exit(1);
    }

    if (!is_array($decoded['require'] ?? null) || !array_key_exists(ENGINE_PACKAGE, $decoded['require'])) {
        fwrite(STDERR, sprintf("%s does not require %s.\n", $manifest, ENGINE_PACKAGE));
        exit(1);
    }

    $replacement = json_encode($version, JSON_THROW_ON_ERROR);
    $updated = preg_replace_callback(
        '/("lenga\\/engine"\s*:\s*)"[^"]+"/',
        static fn(array $matches): string => $matches[1] . $replacement,
        $contents,
        1,
        $count,
    );

    if (!is_string($updated) || $count !== 1) {
        fwrite(STDERR, "Could not update the engine constraint in {$manifest}.\n");
        exit(1);
    }

    if ($updated !== $contents && file_put_contents($manifest, $updated) === false) {
        fwrite(STDERR, "Failed to write {$manifest}.\n");
        exit(1);
    }
}

function checkAlignment(string $root): void
{
    $versionFile = $root . DIRECTORY_SEPARATOR . 'ENGINE_VERSION';
    $expected = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
    $errors = [];

    if (!isSupportedVersion($expected)) {
        $errors[] = 'ENGINE_VERSION must contain dev-develop or an exact semantic release version.';
    }

    foreach (projectManifests($root) as $manifest) {
        $project = basename(dirname($manifest));
        $composer = readJson($manifest, $errors);
        $constraint = $composer['require'][ENGINE_PACKAGE] ?? null;
        if ($constraint !== $expected) {
            $errors[] = sprintf('%s requires %s; expected %s.', $project, (string) $constraint, $expected);
        }

        $repositories = is_array($composer['repositories'] ?? null) ? $composer['repositories'] : [];
        $hasPublicRepository = false;
        foreach ($repositories as $repository) {
            if (($repository['type'] ?? null) === 'vcs' && ($repository['url'] ?? null) === ENGINE_REPOSITORY) {
                $hasPublicRepository = true;
                break;
            }
        }
        if (!$hasPublicRepository) {
            $errors[] = sprintf('%s must resolve %s from %s.', $project, ENGINE_PACKAGE, ENGINE_REPOSITORY);
        }

        $lock = readJson(dirname($manifest) . DIRECTORY_SEPARATOR . 'composer.lock', $errors);
        $lockedPackage = null;
        foreach ($lock['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) === ENGINE_PACKAGE) {
                $lockedPackage = $package;
                break;
            }
        }

        if (!is_array($lockedPackage)) {
            $errors[] = sprintf('%s/composer.lock does not contain %s.', $project, ENGINE_PACKAGE);
            continue;
        }

        if (($lockedPackage['version'] ?? null) !== $expected) {
            $errors[] = sprintf('%s locks %s; expected %s.', $project, (string) ($lockedPackage['version'] ?? ''), $expected);
        }
        if (($lockedPackage['source']['url'] ?? null) !== ENGINE_REPOSITORY) {
            $errors[] = sprintf('%s lock file does not use the public engine source.', $project);
        }
        if (($lockedPackage['dist']['type'] ?? null) === 'path') {
            $errors[] = sprintf('%s lock file still contains a local path distribution.', $project);
        }
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, "- {$error}\n");
        }
        exit(1);
    }

    fwrite(STDOUT, sprintf("All sample projects target %s through public Composer sources.\n", $expected));
}

/**
 * @param list<string> $errors
 * @return array<string, mixed>
 */
function readJson(string $path, array &$errors): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        $errors[] = "Could not read {$path}.";
        return [];
    }

    try {
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $errors[] = "Invalid JSON in {$path}: {$exception->getMessage()}";
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param list<string> $command
 */
function runProcess(array $command, string $workingDirectory): int
{
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ],
        $pipes,
        $workingDirectory,
    );

    if (!is_resource($process)) {
        fwrite(STDERR, sprintf("Failed to start %s.\n", $command[0]));
        return 1;
    }

    fclose($pipes[0]);
    return proc_close($process);
}
