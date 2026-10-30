<?php
/**
 * Plugin Name: __PROJECT_NAME__ API
 * Version: 0.1.0
 * Requires PHP: 8.4
 */
declare(strict_types=1);
defined('ABSPATH') || exit;
add_action('gear_headless_register_routes', static function (): void {
    register_rest_route('__SLUG__/v1', '/example', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => static fn (): array => ['example' => true],
    ]);
});
