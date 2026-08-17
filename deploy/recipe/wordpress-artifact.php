<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/common.php';

set('shared_files', ['.env']);
set('shared_dirs', ['public/wp-content/uploads']);

function requireLocalFile(string $path, string $description): void
{
    if ($path === '' || !is_file($path)) {
        throw new \RuntimeException("{$description} not found: {$path}");
    }
}

function releaseRuntimeValue(string $constant): string
{
    $code = <<<PHP
require "{{current_path}}/vendor/autoload.php";
require "{{current_path}}/config/application.php";
echo {$constant};
PHP;

    return trim(run('{{bin/php}} -r ' . escapeshellarg($code)));
}

task('gear:runtime:prepare', function () {
    $runtimeEnv = (string) get('runtime_env');
    requireLocalFile($runtimeEnv, 'Runtime environment');

    $secretNames = [
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ];

    $contents = file_get_contents($runtimeEnv);

    if ($contents === false) {
        throw new \RuntimeException("Unable to read runtime environment: {$runtimeEnv}");
    }

    $generated = 0;

    foreach ($secretNames as $name) {
        $pattern = '/^' . preg_quote($name, '/') . '=(.*)$/m';

        if (preg_match($pattern, $contents, $matches) === 1) {
            $current = trim($matches[1], " \t\n\r\0\x0B\"'");

            if ($current !== '') {
                continue;
            }

            $value = bin2hex(random_bytes(48));
            $contents = preg_replace($pattern, $name . '=' . $value, $contents, 1);
        } else {
            $value = bin2hex(random_bytes(48));
            $contents = rtrim($contents) . PHP_EOL . $name . '=' . $value . PHP_EOL;
        }

        $generated++;
    }

    if ($generated > 0) {
        if (file_put_contents($runtimeEnv, $contents) === false) {
            throw new \RuntimeException("Unable to update runtime environment: {$runtimeEnv}");
        }

        chmod($runtimeEnv, 0600);
        info("generated {$generated} WordPress authentication keys/salts");
    } else {
        info('WordPress authentication keys/salts already configured');
    }

    run('mkdir -p {{deploy_path}}/shared');

    $remoteTemp = '{{deploy_path}}/shared/.env.next';
    upload($runtimeEnv, $remoteTemp);

    if (test('[ -f {{deploy_path}}/shared/.env ]')) {
        if (!test('cmp -s {{deploy_path}}/shared/.env ' . $remoteTemp)) {
            run('rm -f ' . $remoteTemp);
            throw new \RuntimeException(
                'Remote shared/.env differs from the supplied runtime environment. ' .
                'Configuration changes must be handled explicitly.'
            );
        }

        run('rm -f ' . $remoteTemp);
        info('remote runtime environment already configured');
        return;
    }

    run('chmod 600 ' . $remoteTemp);
    run('mv ' . $remoteTemp . ' {{deploy_path}}/shared/.env');
    info('installed remote runtime environment');
});

task('gear:runtime:require', function () {
    if (!test('[ -f {{deploy_path}}/shared/.env ]')) {
        throw new \RuntimeException(
            'Missing {{deploy_path}}/shared/.env. Run the install task first.'
        );
    }
});

task('gear:artifact:upload', function () {
    $artifact = (string) get('artifact');
    requireLocalFile($artifact, 'Release artifact');
    requireLocalFile($artifact . '.sha256', 'Release checksum');

    $name = basename($artifact);
    $checksumName = basename($artifact . '.sha256');

    info("uploading {$name}");

    upload($artifact, "{{release_path}}/{$name}");
    upload($artifact . '.sha256', "{{release_path}}/{$checksumName}");

    run('cd {{release_path}} && sha256sum --check ' . escapeshellarg($checksumName));
    run('cd {{release_path}} && tar -xzf ' . escapeshellarg($name));
    run(
        'cd {{release_path}} && rm -f ' .
        escapeshellarg($name) . ' ' . escapeshellarg($checksumName)
    );

    $revision = 'artifact';

    if (preg_match('/-([0-9a-f]{12})\.tar\.gz$/', $name, $matches) === 1) {
        $revision = $matches[1];
    }

    run(
        'printf %s ' . escapeshellarg($revision) .
        ' > {{release_path}}/REVISION'
    );
});

task('gear:artifact:validate', function () {
    $required = [
        'config',
        'vendor',
        'public',
        'public/index.php',
        'public/wp-config.php',
        'public/wp',
        'public/wp-content',
    ];

    foreach ($required as $path) {
        if (!test('[ -e {{release_path}}/' . $path . ' ]')) {
            throw new \RuntimeException("Invalid release: missing {$path}");
        }
    }
});

task('gear:target:publish', function () {
    // Adapter extension point.
});

task('gear:wordpress:install', function () {
    $requiredConfig = [
        'install_site_title',
        'install_admin_user',
        'install_admin_password',
        'install_admin_email',
    ];

    foreach ($requiredConfig as $name) {
        if ((string) get($name) === '') {
            throw new \RuntimeException("Missing install configuration: {$name}");
        }
    }

    $wp = '{{bin/php}} {{bin/wp}} --path={{current_path}}/public/wp --skip-plugins --skip-themes';

    if (test($wp . ' core is-installed')) {
        info('WordPress is already installed');
        return;
    }

    $home = releaseRuntimeValue('WP_HOME');
    $siteUrl = releaseRuntimeValue('WP_SITEURL');

    info("installing WordPress at {$home}");

    run(
        $wp .
        ' core install' .
        ' --url=' . escapeshellarg($home) .
        ' --title=' . escapeshellarg((string) get('install_site_title')) .
        ' --admin_user=' . escapeshellarg((string) get('install_admin_user')) .
        ' --admin_password=%admin_password%' .
        ' --admin_email=' . escapeshellarg((string) get('install_admin_email')) .
        ' --skip-email',
        secrets: ['admin_password' => (string) get('install_admin_password')]
    );

    run($wp . ' option update home ' . escapeshellarg($home));
    run($wp . ' option update siteurl ' . escapeshellarg($siteUrl));
    run(
        $wp . ' option update blog_public ' .
        escapeshellarg((string) get('install_blog_public'))
    );
});

task('gear:healthcheck', function () {
    $siteUrl = rtrim(releaseRuntimeValue('WP_SITEURL'), '/');
    $url = $siteUrl . '/wp-login.php';

    $info = [];
    fetch($url, info: $info, nothrow: true);

    $status = (int) ($info['http_code'] ?? 0);

    if ($status < 200 || $status >= 400) {
        throw new \RuntimeException("Healthcheck failed: {$url} returned HTTP {$status}");
    }

    info("healthcheck passed: {$url} ({$status})");
});

task('gear:release', [
    'deploy:lock',
    'deploy:release',
    'gear:artifact:upload',
    'gear:artifact:validate',
    'deploy:shared',
    'deploy:symlink',
    'gear:target:publish',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
]);

fail('gear:release', 'deploy:unlock');

desc('Deploys a previously built Gear artifact');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'gear:runtime:require',
    'gear:release',
    'gear:healthcheck',
]);

desc('Installs a Gear artifact and initializes WordPress if necessary');
task('install', [
    'deploy:info',
    'deploy:setup',
    'gear:runtime:prepare',
    'gear:release',
    'gear:wordpress:install',
    'gear:healthcheck',
]);

after('rollback', 'gear:healthcheck');
