<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$rootDir = dirname(__DIR__);
$publicDir = $rootDir . '/public';

$dotenv = Dotenv::createImmutable($rootDir);
$dotenv->safeLoad();

$dotenv
    ->required([
        'WP_ENVIRONMENT_TYPE',
        'WP_HOME',
        'WP_SITEURL',

        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',

        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ])
    ->notEmpty();

$dotenv
    ->required('WP_ENVIRONMENT_TYPE')
    ->allowedValues([
        'development',
        'staging',
        'production',
    ]);

$env = static function (string $name, mixed $default = null): mixed {
    return $_ENV[$name] ?? $_SERVER[$name] ?? $default;
};

$wpHome = rtrim((string) $env('WP_HOME'), '/');
$wpSiteUrl = rtrim((string) $env('WP_SITEURL'), '/');
$environment = (string) $env('WP_ENVIRONMENT_TYPE');

$config = [
    // Environment.
    'WP_ENVIRONMENT_TYPE' => $environment,

    // URLs.
    'WP_HOME' => $wpHome,
    'WP_SITEURL' => $wpSiteUrl,

    // Content lives outside the WordPress core directory.
    'WP_CONTENT_DIR' => $publicDir . '/wp-content',
    'WP_CONTENT_URL' => $wpHome . '/wp-content',

    // Database.
    'DB_NAME' => (string) $env('DB_NAME'),
    'DB_USER' => (string) $env('DB_USER'),
    'DB_PASSWORD' => (string) $env('DB_PASSWORD'),
    'DB_HOST' => (string) $env('DB_HOST', 'localhost'),
    'DB_CHARSET' => (string) $env('DB_CHARSET', 'utf8mb4'),
    'DB_COLLATE' => (string) $env('DB_COLLATE', ''),

    // Authentication.
    'AUTH_KEY' => (string) $env('AUTH_KEY'),
    'SECURE_AUTH_KEY' => (string) $env('SECURE_AUTH_KEY'),
    'LOGGED_IN_KEY' => (string) $env('LOGGED_IN_KEY'),
    'NONCE_KEY' => (string) $env('NONCE_KEY'),
    'AUTH_SALT' => (string) $env('AUTH_SALT'),
    'SECURE_AUTH_SALT' => (string) $env('SECURE_AUTH_SALT'),
    'LOGGED_IN_SALT' => (string) $env('LOGGED_IN_SALT'),
    'NONCE_SALT' => (string) $env('NONCE_SALT'),

    // Managed application defaults.
    'AUTOMATIC_UPDATER_DISABLED' => true,
    'DISALLOW_FILE_EDIT' => true,
    'DISALLOW_FILE_MODS' => true,

    // Production-safe debug defaults.
    'WP_DEBUG' => false,
    'WP_DEBUG_DISPLAY' => false,
    'WP_DEBUG_LOG' => false,
    'SCRIPT_DEBUG' => false,
];

$environmentFile = __DIR__ . '/environments/' . $environment . '.php';

if (!is_file($environmentFile)) {
    throw new RuntimeException(
        sprintf('Missing configuration for environment "%s".', $environment)
    );
}

$environmentConfig = require $environmentFile;

if (!is_array($environmentConfig)) {
    throw new RuntimeException(
        sprintf('Environment configuration "%s" must return an array.', $environmentFile)
    );
}

$config = array_replace($config, $environmentConfig);

foreach ($config as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

$table_prefix = (string) $env('DB_TABLE_PREFIX', 'wp_');
