<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_register_rest_routes(): void
{
    register_rest_route('phone-catalog/v1', '/ai/device', [
        'methods' => 'GET',
        'permission_callback' => static fn(): bool => current_user_can('manage_options'),
        'args' => [
            'slug' => ['required' => true, 'sanitize_callback' => 'sanitize_title'],
        ],
        'callback' => function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $device = pc_get_device_by_slug((string) $request->get_param('slug'));
            if (!$device) return new WP_Error('pc_ai_device_missing', '제품을 찾을 수 없습니다.', ['status' => 404]);
            return new WP_REST_Response([
                'post_id' => (int) $device->post_id,
                'device_id' => (int) $device->id,
                'url' => get_permalink((int) $device->post_id),
                'facts' => pc_device_ai_facts($device),
                'editorial' => pc_device_editorial((int) $device->id),
            ]);
        },
    ]);

    register_rest_route('phone-catalog/v1', '/ai/device/(?P<id>\d+)', [
        'methods' => 'POST',
        'permission_callback' => static fn(): bool => current_user_can('manage_options'),
        'callback' => function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $device_id = absint($request->get_param('id'));
            global $wpdb;
            $device_exists = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . pc_table('devices') . ' WHERE id = %d', $device_id));
            if (!$device_exists) return new WP_Error('pc_ai_device_missing', '제품을 찾을 수 없습니다.', ['status' => 404]);
            $content = $request->get_json_params();
            if (!is_array($content)) return new WP_Error('pc_ai_invalid_payload', 'JSON 콘텐츠가 필요합니다.', ['status' => 400]);
            $saved = pc_save_device_editorial(
                $device_id,
                $content,
                is_array($content['facts'] ?? null) ? $content['facts'] : [],
                sanitize_text_field((string) ($content['model'] ?? PC_OPENROUTER_MODEL))
            );
            if (is_wp_error($saved)) return $saved;
            return new WP_REST_Response(['saved' => true, 'device_id' => $device_id]);
        },
    ]);

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
            if (!in_array($post_type, ['all', 'phone', 'laptop', 'cpu', 'gpu', 'ssd'], true)) {
                $post_type = 'all';
            }
            $matched_ids = pc_search_post_ids((string) $request->get_param('q'), $post_type, 50);
            if (!$matched_ids) {
                return new WP_REST_Response([]);
            }
            $query = new WP_Query([
                'post_type' => $post_type === 'all' ? ['phone', 'laptop', 'cpu', 'gpu', 'ssd'] : $post_type,
                'post_status' => 'publish',
                'post__in' => $matched_ids,
                'posts_per_page' => 10,
                'orderby' => 'post__in',
                'no_found_rows' => true,
            ]);
            $items = array_map(static function (WP_Post $post): array {
                $item_type = (string) get_post_type($post);
                $device = $item_type === 'phone' ? pc_get_device((int) $post->ID) : null;
                $brands = $item_type === 'phone'
                    ? []
                    : wp_get_post_terms($post->ID, 'hardware_brand', ['fields' => 'names']);
                return [
                    'id' => $post->ID,
                    'type' => $item_type,
                    'name' => pc_product_name((int) $post->ID),
                    'originalName' => pc_product_original_name((int) $post->ID),
                    'slug' => $post->post_name,
                    'brand' => $item_type === 'phone'
                        ? pc_apply_name_mappings((string) ($device?->brand ?? ''))
                        : (!is_wp_error($brands) ? ($brands[0] ?? '') : ''),
                    'image' => $item_type === 'phone' ? pc_public_image_url($device) : pc_public_tech_image_url((int) $post->ID),
                    'url' => get_permalink($post),
                ];
            }, $query->posts);
            return new WP_REST_Response($items);
        },
    ]);
}
