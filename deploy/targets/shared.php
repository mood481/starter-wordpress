<?php

declare(strict_types=1);

namespace Deployer;

set('target_placeholders', []);

task('gear:target:publish', function () {
    $documentRoot = (string) get('document_root');

    if ($documentRoot === '') {
        throw new \RuntimeException('Missing document_root configuration.');
    }

    run('mkdir -p ' . escapeshellarg($documentRoot));

    $managed = [
        'index.php',
        'wp-config.php',
        'wp',
        'wp-content',
    ];

    $placeholders = (array) get('target_placeholders');

    $output = trim(run(
        'find ' . escapeshellarg($documentRoot) .
        ' -mindepth 1 -maxdepth 1 -print'
    ));

    if ($output !== '') {
        foreach (preg_split('/\R/', $output) as $entry) {
            $name = basename($entry);

            if ($name === '.well-known') {
                continue;
            }

            if (in_array($name, $managed, true)) {
                if (!test('[ -L ' . escapeshellarg($entry) . ' ]')) {
                    throw new \RuntimeException(
                        "Document root contains unmanaged path: {$entry}"
                    );
                }
                continue;
            }

            if (in_array($name, $placeholders, true)) {
                if (!test('[ -f ' . escapeshellarg($entry) . ' ]') ||
                    test('[ -L ' . escapeshellarg($entry) . ' ]')) {
                    throw new \RuntimeException(
                        "Unexpected placeholder type: {$entry}"
                    );
                }
                continue;
            }

            throw new \RuntimeException(
                "Document root contains unmanaged path: {$entry}"
            );
        }
    }

    foreach ($placeholders as $placeholder) {
        $path = rtrim($documentRoot, '/') . '/' . $placeholder;

        if (test('[ -f ' . escapeshellarg($path) . ' ]') &&
            !test('[ -L ' . escapeshellarg($path) . ' ]')) {
            info("removing hosting placeholder {$placeholder}");
            run('rm -f ' . escapeshellarg($path));
        }
    }

    $currentPublic = parse('{{current_path}}') . '/public';

    foreach ($managed as $name) {
        $destination = rtrim($documentRoot, '/') . '/' . $name;
        $target = $currentPublic . '/' . $name;

        run(
            'ln -sfn ' .
            escapeshellarg($target) . ' ' .
            escapeshellarg($destination)
        );
    }

    info("document root published: {$documentRoot}");
});
