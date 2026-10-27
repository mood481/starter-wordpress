<?php
/**
 * Plugin Name: Gear Headless API
 * Description: Minimal versioned, read-only API for Gear headless sites.
 * Version: 0.1.0
 * Requires PHP: 8.4
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action('rest_api_init', static function (): void {
    register_rest_route('gear/v1', '/health', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => static fn (): array => ['status' => 'ok'],
    ]);

    register_rest_route('gear/v1', '/posts', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'args' => [
            'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
            'per_page' => ['default' => 10, 'sanitize_callback' => 'absint'],
        ],
        'callback' => static function (WP_REST_Request $request): array {
            $page = max(1, (int) $request->get_param('page'));
            $perPage = min(50, max(1, (int) $request->get_param('per_page')));
            $query = new WP_Query([
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $perPage,
                'paged' => $page,
                'ignore_sticky_posts' => true,
            ]);
            return [
                'page' => $page,
                'total' => (int) $query->found_posts,
                'total_pages' => (int) $query->max_num_pages,
                'items' => array_map('gear_headless_post', $query->posts),
            ];
        },
    ]);

    register_rest_route('gear/v1', '/posts/(?P<id>\\d+)', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'args' => ['id' => ['sanitize_callback' => 'absint']],
        'callback' => static function (WP_REST_Request $request): array|WP_Error {
            $post = get_post((int) $request['id']);
            if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
                return new WP_Error('gear_not_found', 'Post not found.', ['status' => 404]);
            }
            return gear_headless_post($post);
        },
    ]);
});

function gear_headless_post(WP_Post $post): array
{
    return [
        'id' => (int) $post->ID,
        'slug' => $post->post_name,
        'title' => get_the_title($post),
        'excerpt' => apply_filters('the_excerpt', $post->post_excerpt),
        'content' => apply_filters('the_content', $post->post_content),
        'date_gmt' => get_post_time('c', true, $post),
        'modified_gmt' => get_post_modified_time('c', true, $post),
        'author_id' => (int) $post->post_author,
        'featured_media' => (int) get_post_thumbnail_id($post),
        'category_ids' => wp_get_post_categories($post->ID),
        'tag_ids' => wp_get_post_tags($post->ID, ['fields' => 'ids']),
    ];
}
