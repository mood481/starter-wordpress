<?php
/**
 * Plugin Name: Gear Headless API
 * Description: Versioned API extension point for WordPress Gear consumers.
 * Version: 0.2.0
 * Requires PHP: 8.4
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * The starter owns the infrastructure, not a project's content model.
 * Project plugins register their own namespaces on this hook.
 */
add_action('rest_api_init', static function (): void {
    register_rest_route('gear/v1', '/health', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => static fn (): array => ['status' => 'ok'],
    ]);

    do_action('gear_headless_register_routes');
}, 10);
