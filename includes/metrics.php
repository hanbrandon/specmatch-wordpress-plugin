<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_register_metrics_routes(): void
{
    register_rest_route('phone-catalog/v1', '/event', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => [
            'post_id' => ['required' => true, 'sanitize_callback' => 'absint'],
            'event' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
            'session' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        ],
        'callback' => function (WP_REST_Request $request): WP_REST_Response {
            global $wpdb;
            $post_id = (int) $request->get_param('post_id');
            $event = (string) $request->get_param('event');
            $session = substr((string) $request->get_param('session'), 0, 80);
            $post = get_post($post_id);
            if (
                !$post
                || $post->post_status !== 'publish'
                || !in_array($post->post_type, ['phone', 'laptop', 'cpu', 'gpu'], true)
                || !in_array($event, ['view', 'compare', 'affiliate', 'search_click'], true)
                || strlen($session) < 12
            ) {
                return new WP_REST_Response(['stored' => false], 400);
            }
            $stored = $wpdb->query($wpdb->prepare(
                'INSERT IGNORE INTO ' . pc_table('events') . '
                 (post_id, post_type, event_type, session_hash, event_day, created_at)
                 VALUES (%d, %s, %s, %s, %s, %s)',
                $post_id,
                $post->post_type,
                $event,
                hash_hmac('sha256', $session, wp_salt('nonce')),
                current_time('Y-m-d', true),
                current_time('mysql', true)
            ));
            if ($stored) {
                wp_cache_delete('pc_popular_' . $post->post_type, 'phone-catalog');
            }
            return new WP_REST_Response(['stored' => (bool) $stored]);
        },
    ]);
}

function pc_popular_posts_from_events(string $post_type, int $limit = 5, int $exclude_post_id = 0): array
{
    global $wpdb;
    if (!in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu'], true)) {
        return [];
    }
    $cache_key = 'pc_popular_' . $post_type . '_' . $limit . '_' . $exclude_post_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return array_values(array_filter(array_map('get_post', $cached)));
    }
    $ids = $wpdb->get_col($wpdb->prepare(
        'SELECT e.post_id
         FROM ' . pc_table('events') . ' e
         INNER JOIN ' . $wpdb->posts . ' p ON p.ID=e.post_id
         WHERE e.post_type=%s AND e.event_day >= %s AND p.post_status=%s AND e.post_id <> %d
         GROUP BY e.post_id
         ORDER BY SUM(CASE e.event_type WHEN "affiliate" THEN 5 WHEN "compare" THEN 3 WHEN "search_click" THEN 2 ELSE 1 END) DESC,
                  MAX(e.created_at) DESC
         LIMIT %d',
        $post_type,
        gmdate('Y-m-d', strtotime('-30 days')),
        'publish',
        $exclude_post_id,
        $limit
    ));
    set_transient($cache_key, array_map('intval', $ids), 300);
    return array_values(array_filter(array_map('get_post', $ids)));
}

function pc_schedule_metrics_cleanup(): void
{
    if (!wp_next_scheduled('pc_cleanup_old_metrics')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'pc_cleanup_old_metrics');
    }
}

function pc_cleanup_old_metrics(): void
{
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . pc_table('events') . ' WHERE event_day < %s',
        gmdate('Y-m-d', strtotime('-180 days'))
    ));
}
