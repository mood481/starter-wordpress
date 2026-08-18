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

function releaseWordPressConfig(): array
{
    $code = <<<'PHP'
$config = require "{{current_path}}/config/wordpress.php";

if (!is_array($config)) {
    fwrite(STDERR, "config/wordpress.php must return an array.\n");
    exit(1);
}

echo json_encode($config, JSON_THROW_ON_ERROR);
PHP;

    $json = trim(run('{{bin/php}} -r ' . escapeshellarg($code)));
    $config = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($config)) {
        throw new \RuntimeException('Invalid config/wordpress.php.');
    }

    return $config;
}

function wordpressCli(bool $loadExtensions = false): string
{
    $command = '{{bin/php}} {{bin/wp}} --path={{current_path}}/public/wp';

    if (!$loadExtensions) {
        $command .= ' --skip-plugins --skip-themes';
    }

    return $command;
}

function wordpressConfigList(mixed $value, string $name): array
{
    if ($value === null) {
        return [];
    }

    if (!is_array($value)) {
        throw new \RuntimeException(
            "{$name} in config/wordpress.php must be an array."
        );
    }

    $items = [];

    foreach ($value as $item) {
        if (!is_string($item) || trim($item) === '') {
            throw new \RuntimeException(
                "{$name} in config/wordpress.php must contain non-empty strings."
            );
        }

        $items[] = trim($item);
    }

    if (count($items) !== count(array_unique($items))) {
        throw new \RuntimeException(
            "{$name} in config/wordpress.php contains duplicate entries."
        );
    }

    return $items;
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
        'config/wordpress.php',
        'vendor',
        'public',
        'public/index.php',
        'public/wp-config.php',
        'public/wp',
        'public/wp-content',
        'public/wp-content/mu-plugins',
        'public/wp-content/plugins',
        'public/wp-content/themes',
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
    $wp = wordpressCli();

    if (test($wp . ' core is-installed')) {
        info('WordPress is already installed');
        return;
    }

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

task('gear:wordpress:configure', function () {
    $wp = wordpressCli();
    $runtimeWp = wordpressCli(true);

    if (!test($wp . ' core is-installed')) {
        throw new \RuntimeException(
            'WordPress is not installed. Run the install task first.'
        );
    }

    $config = releaseWordPressConfig();

    /*
     * Theme state.
     */
    $theme = $config['theme'] ?? null;

    if (!is_array($theme)) {
        throw new \RuntimeException(
            'Missing theme configuration in config/wordpress.php.'
        );
    }

    $parent = trim((string) ($theme['parent'] ?? ''));
    $activeTheme = trim((string) ($theme['active'] ?? ''));

    if ($parent === '') {
        throw new \RuntimeException(
            'Missing theme.parent in config/wordpress.php.'
        );
    }

    if ($activeTheme === '') {
        throw new \RuntimeException(
            'Missing theme.active in config/wordpress.php.'
        );
    }

    if (!test($wp . ' theme is-installed ' . escapeshellarg($parent))) {
        throw new \RuntimeException(
            "Configured parent theme is not installed: {$parent}"
        );
    }

    if (!test($wp . ' theme is-installed ' . escapeshellarg($activeTheme))) {
        throw new \RuntimeException(
            "Configured active theme is not installed: {$activeTheme}"
        );
    }

    $currentTheme = trim(run($wp . ' option get stylesheet'));

    if ($currentTheme !== $activeTheme) {
        run($wp . ' theme activate ' . escapeshellarg($activeTheme));
    }

    $actualTheme = trim(run($wp . ' option get stylesheet'));
    $actualParent = trim(run($wp . ' option get template'));

    if ($actualTheme !== $activeTheme) {
        throw new \RuntimeException(
            "Unexpected active theme: expected {$activeTheme}, got {$actualTheme}."
        );
    }

    if ($actualParent !== $parent) {
        throw new \RuntimeException(
            "Unexpected parent theme: expected {$parent}, got {$actualParent}."
        );
    }

    /*
     * Standard plugin state.
     */
    $plugins = $config['plugins'] ?? [];

    if (!is_array($plugins)) {
        throw new \RuntimeException(
            'plugins in config/wordpress.php must be an array.'
        );
    }

    $activePlugins = wordpressConfigList(
        $plugins['active'] ?? [],
        'plugins.active'
    );

    $inactivePlugins = wordpressConfigList(
        $plugins['inactive'] ?? [],
        'plugins.inactive'
    );

    $overlap = array_values(array_intersect($activePlugins, $inactivePlugins));

    if ($overlap !== []) {
        throw new \RuntimeException(
            'Plugins cannot be both active and inactive: ' .
            implode(', ', $overlap)
        );
    }

    foreach ($activePlugins as $plugin) {
        if (!test($wp . ' plugin is-installed ' . escapeshellarg($plugin))) {
            throw new \RuntimeException(
                "Configured plugin is not installed: {$plugin}"
            );
        }

        if (!test($wp . ' plugin is-active ' . escapeshellarg($plugin))) {
            run($wp . ' plugin activate ' . escapeshellarg($plugin));
        }
    }

    foreach ($inactivePlugins as $plugin) {
        if (!test($wp . ' plugin is-installed ' . escapeshellarg($plugin))) {
            throw new \RuntimeException(
                "Configured plugin is not installed: {$plugin}"
            );
        }

        if (test($wp . ' plugin is-active ' . escapeshellarg($plugin))) {
            run($wp . ' plugin deactivate ' . escapeshellarg($plugin));
        }
    }

    foreach ($activePlugins as $plugin) {
        if (!test($wp . ' plugin is-active ' . escapeshellarg($plugin))) {
            throw new \RuntimeException(
                "Expected plugin to be active: {$plugin}"
            );
        }
    }

    foreach ($inactivePlugins as $plugin) {
        if (test($wp . ' plugin is-active ' . escapeshellarg($plugin))) {
            throw new \RuntimeException(
                "Expected plugin to be inactive: {$plugin}"
            );
        }
    }

    /*
     * MU-plugin state.
     *
     * Unlike the commands above, this intentionally loads the WordPress
     * runtime so WP-CLI can observe the actual must-use plugins.
     */
    $muPlugins = wordpressConfigList(
        $config['mu_plugins'] ?? [],
        'mu_plugins'
    );

    if ($muPlugins !== []) {
        $loadedMuPlugins = preg_split(
            '/\R/',
            trim(run(
                $runtimeWp .
                ' plugin list --status=must-use --field=name --skip-update-check'
            ))
        );

        $loadedMuPlugins = array_values(array_filter(
            array_map('trim', $loadedMuPlugins ?: []),
            static fn (string $plugin): bool => $plugin !== ''
        ));

        foreach ($muPlugins as $plugin) {
            if (!in_array($plugin, $loadedMuPlugins, true)) {
                throw new \RuntimeException(
                    "Expected MU-plugin to be loaded: {$plugin}"
                );
            }
        }
    }

    info("WordPress theme configured: {$activeTheme} (parent: {$parent})");

    if ($activePlugins !== []) {
        info('active plugins: ' . implode(', ', $activePlugins));
    }

    if ($inactivePlugins !== []) {
        info('inactive plugins: ' . implode(', ', $inactivePlugins));
    }

    if ($muPlugins !== []) {
        info('MU-plugins loaded: ' . implode(', ', $muPlugins));
    }
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
]);

desc('Deploys a previously built Gear artifact');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'gear:runtime:require',
    'gear:release',
    'gear:wordpress:configure',
    'gear:healthcheck',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
]);

fail('deploy', 'deploy:unlock');

desc('Installs a Gear artifact and initializes WordPress if necessary');
task('install', [
    'deploy:info',
    'deploy:setup',
    'gear:runtime:prepare',
    'gear:release',
    'gear:wordpress:install',
    'gear:wordpress:configure',
    'gear:healthcheck',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
]);

fail('install', 'deploy:unlock');

task('gear:rollback:validate', [
    'gear:wordpress:configure',
    'gear:healthcheck',
]);

after('rollback', 'gear:rollback:validate');
