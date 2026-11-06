<?php

declare(strict_types=1);

require_once __DIR__ . '/../scripts/prepare-release.php';

$manifest = file_get_contents(__DIR__ . '/../extension.xml');
$module = file_get_contents(__DIR__ . '/../br-org-extension/module.br-org-extension.php');
$xml = simplexml_load_string($manifest);
$current = trim((string) $xml->version);
$parts = explode('.', $current);
$next = $parts[0] . '.' . $parts[1] . '.' . ((int) $parts[2] + 1);

$tests = [
    'prepare matching release metadata without changing other content' => static function () use ($manifest, $module, $current, $next): void {
        [$updatedManifest, $updatedModule] = prepareReleaseContents($manifest, $module, $next, '2028-02-29');
        $xml = simplexml_load_string($updatedManifest);
        if ((string) $xml->version !== $next || (string) $xml->release_date !== '2028-02-29'
            || $updatedModule !== str_replace("'br-org-extension/$current'", "'br-org-extension/$next'", $module)
            || preg_replace('~<(version|release_date)>.*?</\1>~s', '', $updatedManifest)
                !== preg_replace('~<(version|release_date)>.*?</\1>~s', '', $manifest)) {
            throw new RuntimeException('Unexpected release metadata or unrelated edits.');
        }
    },
];

$cases = [
    'reject malformed version' => [$manifest, $module, 'v3.2.3', '2028-02-29'],
    'reject version with leading zeros' => [$manifest, $module, '03.2.3', '2028-02-29'],
    'reject unchanged version' => [$manifest, $module, $current, '2028-02-29'],
    'reject older version' => [$manifest, $module, '0.0.0', '2028-02-29'],
    'reject invalid calendar date' => [$manifest, $module, $next, '2027-02-29'],
    'reject ambiguous module version' => [$manifest, $module . $module, $next, '2028-02-29'],
    'reject mismatched module version' => [$manifest, str_replace("'br-org-extension/$current'", "'br-org-extension/0.0.0'", $module), $next, '2028-02-29'],
    'reject missing date element' => [preg_replace('~<release_date>.*?</release_date>~s', '', $manifest), $module, $next, '2028-02-29'],
];
foreach ($cases as $name => $arguments) {
    $tests[$name] = static function () use ($arguments): void {
        try {
            prepareReleaseContents(...$arguments);
        } catch (RuntimeException $error) {
            return;
        }
        throw new RuntimeException('Invalid release input was accepted.');
    };
}

$sources = ['extension.xml' => $manifest, 'br-org-extension/module.br-org-extension.php' => $module];
foreach (['de', 'en'] as $language) {
    $path = "br-org-extension/dictionaries/$language.dict.br-org-extension.php";
    $sources[$path] = file_get_contents(__DIR__ . '/../' . $path);
}
$tests['update all release versions and dates while preserving compatibility and history'] =
    static function () use ($sources, $current, $next): void {
        $updated = prepareReleaseFiles($sources, $next, '2028-02-29');
        foreach ($sources as $path => $original) {
            if ($path === 'extension.xml') {
                continue;
            }
            $expected = $original;
            if (str_contains($path, '/module.')) {
                $expected = str_replace("'br-org-extension/$current'", "'br-org-extension/$next'", $expected);
            }
            if ($updated[$path] !== $expected) {
                throw new RuntimeException('Unexpected metadata or unrelated edits in ' . $path);
            }
        }
        $before = simplexml_load_string($sources['extension.xml']);
        $after = simplexml_load_string($updated['extension.xml']);
        if ((string) $before->itop_version_min !== (string) $after->itop_version_min) {
            throw new RuntimeException('The iTop minimum version must remain unchanged.');
        }
    };
foreach (['extension.xml', 'br-org-extension/module.br-org-extension.php'] as $path) {
    $tests['reject missing release source ' . $path] = static function () use ($sources, $next, $path): void {
        unset($sources[$path]);
        try {
            prepareReleaseFiles($sources, $next, '2028-02-29');
        } catch (RuntimeException $error) {
            return;
        }
        throw new RuntimeException('Missing release source was accepted.');
    };
}

return $tests;
