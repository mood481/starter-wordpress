<?php

declare(strict_types=1);

/**
 * Plugin Name: Mood WordPress Policies
 * Description: Project-controlled login, app-password and REST security policies
 */

defined('ABSPATH') || exit;

require_once WPMU_PLUGIN_DIR . '/wordpress-policies/mood-wordpress-policies.php';

/*
 * Load project-owned policies from the versioned release config, rather than
 * from a mutable wp-admin option. Each installation can override the profile.
 */
add_filter('mood_wordpress_policies_config', static function (array $defaults): array {
    $path = dirname(__DIR__, 3) . '/config/wordpress.php';
    if (!is_file($path)) {
        return $defaults;
    }
    $config = require $path;
    $policies = is_array($config) ? ($config['policies'] ?? []) : [];
    return is_array($policies)
        ? array_replace_recursive($defaults, $policies)
        : $defaults;
}, 10, 1);
