<?php

use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\warning;

require __DIR__ . '/vendor/autoload.php';

$outputDir = '/Users/adams/Framecap/Videos/' . date('Y-m-d_H-i-s');

exec('framecap sources', $output);

$sources = [];

foreach ($output as $line) {
    if (str_starts_with($line, '  ')) {
        $line = preg_replace('/\x1B\[[0-9;]*m/', '', $line);
        $sources[] = trim($line);
    }
}

$finalSources = [
    'done' => 'Done, start recording',
];

$defaultScreen = null;
$defaultScreenLabel = null;

$appSources = [];

foreach ($sources as $index => $source) {
    if (str_contains($sources[$index + 1] ?? '', 'App ID:')) {
        $appId = trim(str_replace('App ID:', '', $sources[$index + 1]));
        $finalSources[$appId] =  $source;
        $appSources[] = $appId;
    }

    if (str_contains($source, '→')) {
        [$id, $label] = explode('→', $source);
        $finalSources[trim($id)] = trim($label);

        if (str_contains($id, 'screen:')) {
            $defaultScreen ??= trim($id);
            $defaultScreenLabel ??= trim($label);
        }
    }
}

$tracks = [];

do {
    $selected = multisearch(
        label: 'Select sources',
        options: function ($value) use ($finalSources) {
            $results = [];

            foreach ($finalSources as $id => $label) {
                if (str_contains(strtolower($label), strtolower($value)) || str_contains(strtolower($id), strtolower($value))) {
                    $results[$id] = $label;
                }
            }

            return $results;
        },
        required: true,
    );

    if ($selected !== ['done']) {
        $foundScreenOrAudio = false;

        foreach ($selected as $key => $value) {
            if (str_starts_with($value, 'screen:') || str_starts_with($value, 'audio:') || str_starts_with($value, 'camera:')) {
                $foundScreenOrAudio = true;
                break;
            }
        }

        if (!$foundScreenOrAudio) {
            $selected[$defaultScreenLabel] = $defaultScreen;
        }

        $tracks[] = $selected;
    }
} while ($selected !== ['done']);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$command[] = 'framecap';

foreach ($tracks as $index => $trackSources) {
    $trackAppSources = [];
    $trackRegularSources = [];

    foreach ($trackSources as $sourceId) {

        if (in_array($sourceId, $appSources)) {
            $trackAppSources[] = $sourceId;
        } else {
            $trackRegularSources[] = $sourceId;
        }
    }

    if (!empty($trackAppSources)) {
        $trackRegularSources[] = 'app:[' . implode(',', $trackAppSources) . ']';
    }

    $command[] = '--track ' . escapeshellarg(implode(',', $trackRegularSources) . ',filename:track-' . ($index + 1));
}

$command[] = '--output ' . escapeshellarg($outputDir);

$commandString = implode(' ', $command);

warning('Running Command:');
warning($commandString);

passthru($commandString);
