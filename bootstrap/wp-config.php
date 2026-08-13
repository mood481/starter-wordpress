<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

require_once $rootDir . '/vendor/autoload.php';
require_once $rootDir . '/config/application.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wp/');
}

require_once ABSPATH . 'wp-settings.php';
