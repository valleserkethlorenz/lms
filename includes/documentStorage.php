<?php
function documentStorageDirectory(): string
{
    $directory = dirname(__DIR__) . '/storage/private/documents';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The protected document directory could not be created.');
    }
    return realpath($directory) ?: $directory;
}

function documentStoredPath(string $storedName, bool $allowLegacy = true): ?string
{
    $safeName = basename($storedName);
    if ($safeName === '' || $safeName !== $storedName) return null;
    $private = documentStorageDirectory() . DIRECTORY_SEPARATOR . $safeName;
    if (is_file($private)) return $private;
    if ($allowLegacy) {
        $legacy = dirname(__DIR__) . '/assets/uploads/' . $safeName;
        if (is_file($legacy)) return $legacy;
    }
    return null;
}
