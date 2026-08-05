<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$projectName = trim($argv[1] ?? '');
$projectRoot = realpath($root . DIRECTORY_SEPARATOR . $projectName);

if ($projectName === '' || $projectRoot === false || dirname($projectRoot) !== $root) {
    fwrite(STDERR, "Usage: php tools/validate-project.php <project-directory>\n");
    exit(1);
}

$requiredFiles = [
    'bootstrap.php',
    'composer.json',
    'composer.lock',
    'ProjectSettings/lenga.json',
    'vendor/autoload.php',
];

$errors = [];
foreach ($requiredFiles as $relativePath) {
    if (!is_file($projectRoot . DIRECTORY_SEPARATOR . $relativePath)) {
        $errors[] = "Missing {$relativePath}.";
    }
}

$settingsPath = $projectRoot . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'lenga.json';
$settings = readProjectJson($settingsPath, $errors);
foreach ($settings['build']['scenes'] ?? [] as $scene) {
    $scenePath = $scene['path'] ?? null;
    if (!is_string($scenePath) || $scenePath === '') {
        $errors[] = 'ProjectSettings/lenga.json contains a build scene without a path.';
        continue;
    }

    if (!is_file($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $scenePath))) {
        $errors[] = "Configured scene does not exist: {$scenePath}.";
    }
}

$excludedDirectories = ['.git', '.idea', '.vscode', 'build', 'dist', 'node_modules', 'out', 'output', 'Saved', 'vendor'];
$directory = new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator(
    $directory,
    static fn(SplFileInfo $file): bool => !$file->isDir() || !in_array($file->getFilename(), $excludedDirectories, true),
);
$files = new RecursiveIteratorIterator($filter);

foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    if ($file->getExtension() === 'json') {
        readProjectJson($file->getPathname(), $errors);
        continue;
    }

    if ($file->getExtension() === 'php') {
        $exitCode = runLint($file->getPathname());
        if ($exitCode !== 0) {
            $errors[] = 'PHP syntax check failed: ' . substr($file->getPathname(), strlen($projectRoot) + 1);
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("%s project validation passed.\n", $projectName));

/**
 * @param list<string> $errors
 * @return array<string, mixed>
 */
function readProjectJson(string $path, array &$errors): array
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

function runLint(string $path): int
{
    $process = proc_open(
        [PHP_BINARY, '-l', $path],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => STDERR,
        ],
        $pipes,
    );

    if (!is_resource($process)) {
        return 1;
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 && is_string($output)) {
        fwrite(STDERR, $output);
    }

    return $exitCode;
}
