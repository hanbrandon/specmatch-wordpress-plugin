<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_register_rest_routes(): void
{
    register_rest_route('phone-catalog/v1', '/search', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'args' => [
            'q' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'type' => [
                'required' => false,
                'default' => 'phone',
                'sanitize_callback' => 'sanitize_key',
            ],
        ],
        'callback' => function (WP_REST_Request $request): WP_REST_Response {
            $post_type = (string) $request->get_param('type');
            if (!in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu'], true)) {
                $post_type = 'phone';
            }
            $matched_ids = pc_search_post_ids((string) $request->get_param('q'), $post_type, 50);
            if (!$matched_ids) {
                return new WP_REST_Response([]);
            }
            $query = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'publish',
                'post__in' => $matched_ids,
                'posts_per_page' => 10,
                'orderby' => 'post__in',
                'no_found_rows' => true,
            ]);
            $items = array_map(static function (WP_Post $post) use ($post_type): array {
                $device = $post_type === 'phone' ? pc_get_device((int) $post->ID) : null;
                $brands = $post_type === 'phone'
                    ? []
                    : wp_get_post_terms($post->ID, 'hardware_brand', ['fields' => 'names']);
                return [
                    'id' => $post->ID,
                    'name' => pc_product_name((int) $post->ID),
                    'originalName' => pc_product_original_name((int) $post->ID),
                    'slug' => $post->post_name,
                    'brand' => $post_type === 'phone' ? $device?->brand : (!is_wp_error($brands) ? ($brands[0] ?? '') : ''),
                    'image' => $post_type === 'phone' ? pc_public_image_url($device) : pc_public_tech_image_url((int) $post->ID),
                    'url' => get_permalink($post),
                ];
            }, $query->posts);
            return new WP_REST_Response($items);
        },
    ]);
}
