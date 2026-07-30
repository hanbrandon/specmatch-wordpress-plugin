<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_get_device(int $post_id): ?object
{
    global $wpdb;
    $table = pc_table('devices');
    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d", $post_id)
    ) ?: null;
}

function pc_public_text(?string $text): string
{
    if (!$text) {
        return '';
    }
    return trim((string) preg_replace('/\bGSMArena(?:\.com)?\b/i', '', $text));
}

function pc_public_image_url(?object $device): ?string
{
    if (!$device || !$device->image_url) {
        return null;
    }
    return home_url('/phone-media/' . (int) $device->source_id . '/');
}

function pc_register_media_routes(): void
{
    add_rewrite_rule(
        '^phone-media/([0-9]+)/?$',
        'index.php?pc_phone_media=$matches[1]',
        'top'
    );
}

function pc_media_query_vars(array $vars): array
{
    $vars[] = 'pc_phone_media';
    return $vars;
}

function pc_serve_phone_media(): void
{
    $source_id = (int) get_query_var('pc_phone_media');
    if (!$source_id) {
        return;
    }

    global $wpdb;
    $table = pc_table('devices');
    $device = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT source_id, image_url FROM {$table} WHERE source_id = %d",
            $source_id
        )
    );
    if (!$device || !$device->image_url) {
        status_header(404);
        exit;
    }

    $uploads = wp_upload_dir();
    $directory = trailingslashit($uploads['basedir']) . 'phone-catalog';
    $filename = $source_id . '.jpg';
    $path = trailingslashit($directory) . $filename;

    if (!is_file($path)) {
        wp_mkdir_p($directory);
        $response = wp_safe_remote_get($device->image_url, [
            'timeout' => 20,
            'redirection' => 3,
            'headers' => ['User-Agent' => 'PhoneCatalog/1.0'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            status_header(404);
            exit;
        }
        $content_type = (string) wp_remote_retrieve_header($response, 'content-type');
        if (!str_starts_with(strtolower($content_type), 'image/')) {
            status_header(415);
            exit;
        }
        file_put_contents($path, wp_remote_retrieve_body($response), LOCK_EX);
    }

    $htaccess = trailingslashit($directory) . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents(
            $htaccess,
            "Options -Indexes\n<IfModule mod_headers.c>\nHeader set X-Robots-Tag \"noindex\"\n</IfModule>\n",
            LOCK_EX
        );
    }

    $image_info = @getimagesize($path);
    $content_type = $image_info['mime'] ?? 'image/jpeg';
    $modified = (int) filemtime($path);
    $etag = '"' . md5((string) filesize($path) . ':' . $modified) . '"';
    header('X-Robots-Tag: noindex');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
    if (
        trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag
        || strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) >= $modified
    ) {
        status_header(304);
        exit;
    }
    status_header(200);
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=604800, immutable');
    readfile($path);
    exit;
}

function pc_newest_query_args(): array
{
    return [
        'meta_key' => '_catalog_release_date',
        'orderby' => ['meta_value' => 'DESC', 'title' => 'ASC'],
        'order' => 'DESC',
    ];
}

function pc_order_phone_archives_newest_first(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_type = $query->get('post_type');
    $is_phone_search = $query->is_search()
        && ($post_type === 'phone' || $post_type === ['phone']);
    if (
        $query->is_post_type_archive('phone')
        || $query->is_tax(['phone_brand', 'phone_year'])
        || $is_phone_search
    ) {
        foreach (pc_newest_query_args() as $key => $value) {
            $query->set($key, $value);
        }
    }
}

function pc_get_device_by_slug(string $slug): ?object
{
    $post = get_page_by_path(sanitize_title($slug), OBJECT, 'phone');
    return $post ? pc_get_device((int) $post->ID) : null;
}

function pc_get_specs(int $device_id): array
{
    global $wpdb;
    $table = pc_table('specs');
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, section_name, field_name, field_value, data_spec
             FROM {$table}
             WHERE device_id = %d
             ORDER BY section_order, row_order, id",
            $device_id
        )
    );
}

function pc_group_specs(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        if (!$row->field_name && !empty($groups[$row->section_name])) {
            $previous_index = array_key_last($groups[$row->section_name]);
            $previous = $groups[$row->section_name][$previous_index];
            $previous->field_value = trim((string) $previous->field_value)
                . "\n"
                . trim((string) $row->field_value);
            continue;
        }
        $groups[$row->section_name][] = $row;
    }
    return $groups;
}

function pc_get_offers(int $device_id): array
{
    global $wpdb;
    $table = pc_table('offers');
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE device_id = %d AND active = 1
             ORDER BY price IS NULL, price ASC",
            $device_id
        )
    );
}

function pc_spec_value(int $device_id, string $section, string $field): ?string
{
    global $wpdb;
    $table = pc_table('specs');
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT field_value FROM {$table}
             WHERE device_id = %d AND section_name = %s AND field_name = %s
             ORDER BY row_order LIMIT 1",
            $device_id,
            $section,
            $field
        )
    ) ?: null;
}

function pc_sidebar_phone_posts(string $type, int $limit = 5, int $exclude_post_id = 0): array
{
    $base = [
        'post_type' => 'phone',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'post__not_in' => $exclude_post_id ? [$exclude_post_id] : [],
        'no_found_rows' => true,
    ];

    if ($type === 'popular') {
        if (function_exists('pc_popular_posts_from_events')) {
            $measured = pc_popular_posts_from_events('phone', $limit, $exclude_post_id);
            if ($measured) {
                return $measured;
            }
        }
        $query = new WP_Query(array_merge($base, [
            'meta_query' => [
                'popularity_clause' => ['key' => '_pc_popularity', 'compare' => 'EXISTS', 'type' => 'NUMERIC'],
                'release_clause' => ['key' => '_catalog_release_date', 'compare' => 'EXISTS', 'type' => 'DATE'],
            ],
            'orderby' => ['popularity_clause' => 'DESC', 'release_clause' => 'DESC'],
        ]));
        if ($query->posts) {
            return $query->posts;
        }
    }

    return (new WP_Query(array_merge($base, pc_newest_query_args())))->posts;
}
