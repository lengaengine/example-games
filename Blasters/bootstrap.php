<?php

declare(strict_types=1);

$projectRoot = __DIR__;
$vendorAutoloadPath = $projectRoot . '/vendor/autoload.php';

if (is_file($vendorAutoloadPath)) {
    require_once $vendorAutoloadPath;
}

spl_autoload_register(static function (string $className) use ($projectRoot): void {
    static $prefixes = null;

    if ($prefixes === null) {
        $prefixes = [];
        $composerJsonPath = $projectRoot . '/composer.json';
        if (is_file($composerJsonPath)) {
            $composerConfig = json_decode((string) file_get_contents($composerJsonPath), true);
            $psr4Mappings = is_array($composerConfig) ? ($composerConfig['autoload']['psr-4'] ?? []) : [];
            if (is_array($psr4Mappings)) {
                foreach ($psr4Mappings as $prefix => $paths) {
                    $resolvedPaths = [];
                    foreach ((array) $paths as $path) {
                        if (!is_string($path) || $path === '') {
                            continue;
                        }

                        $resolvedPath = $projectRoot . '/' . ltrim($path, '/');
                        if (is_dir($resolvedPath) || is_file($resolvedPath)) {
                            $resolvedPaths[] = rtrim($resolvedPath, '/');
                        }
                    }

                    if ($resolvedPaths !== []) {
                        $prefixes[(string) $prefix] = $resolvedPaths;
                    }
                }
            }
        }
    }

    foreach ($prefixes as $prefix => $paths) {
        if (!str_starts_with($className, $prefix)) {
            continue;
        }

        $relativeClass = substr($className, strlen($prefix));
        if (!is_string($relativeClass)) {
            continue;
        }

        $relativeFile = str_replace('\\', '/', $relativeClass) . '.php';
        foreach ($paths as $path) {
            $candidate = $path . '/' . $relativeFile;
            if (is_file($candidate)) {
                require_once $candidate;
                return;
            }
        }
    }
});

if (!defined('LENGA_PROJECT_ROOT')) {
    define('LENGA_PROJECT_ROOT', $projectRoot);
}

echo "Lenga PHP bootstrap OK\n";
