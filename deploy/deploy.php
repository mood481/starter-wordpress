<?php

declare(strict_types=1);

namespace Deployer;

require __DIR__ . '/recipe/wordpress-artifact.php';

function requiredEnv(string $name): string
{
    $value = getenv($name);

    if ($value === false || trim($value) === '') {
        throw new \RuntimeException("Missing required environment variable: {$name}");
    }

    return $value;
}

$adapter = getenv('DEPLOY_ADAPTER') ?: 'hostinger';

if (!in_array($adapter, ['shared', 'hostinger'], true)) {
    throw new \RuntimeException("Unsupported deploy adapter: {$adapter}");
}

require __DIR__ . "/targets/{$adapter}.php";

$identityFile = '/run/secrets/deploy-key';

$host = host(getenv('DEPLOY_ENV') ?: 'prod')
    ->setHostname(requiredEnv('DEPLOY_HOST'))
    ->setRemoteUser(requiredEnv('DEPLOY_USER'))
    ->setPort((int) (getenv('DEPLOY_PORT') ?: 22))
    ->set('deploy_path', requiredEnv('DEPLOY_ROOT'))
    ->set('document_root', requiredEnv('DEPLOY_DOCUMENT_ROOT'))
    ->set('keep_releases', (int) (getenv('DEPLOY_KEEP_RELEASES') ?: 5))
    ->set('artifact', getenv('DEPLOY_ARTIFACT') ?: '')
    ->set('runtime_env', getenv('DEPLOY_RUNTIME_ENV') ?: '')
    ->set('install_site_title', getenv('INSTALL_SITE_TITLE') ?: '')
    ->set('install_admin_user', getenv('INSTALL_ADMIN_USER') ?: '')
    ->set('install_admin_password', getenv('INSTALL_ADMIN_PASSWORD') ?: '')
    ->set('install_admin_email', getenv('INSTALL_ADMIN_EMAIL') ?: '')
    ->set('install_blog_public', getenv('INSTALL_BLOG_PUBLIC') ?: '0')
    ->set('identity_file', $identityFile)
    ->set('forward_agent', false)
    ->set('ssh_arguments', [
        '-o BatchMode=yes',
        '-o IdentitiesOnly=yes',
        '-o StrictHostKeyChecking=accept-new',
    ]);

$phpBinary = getenv('DEPLOY_PHP_BINARY');

if ($phpBinary !== false && $phpBinary !== '') {
    $host->set('bin/php', $phpBinary);
}

set('bin/wp', function () {
    return which('wp');
});
