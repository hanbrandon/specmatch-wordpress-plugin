<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_detect_product_series(string $post_type, string $name, string $slug = ''): ?array
{
    $text = trim($name . ' ' . str_replace(['_', '-'], ' ', $slug));
    if ($post_type === 'phone') {
        if (preg_match('/iphone\s*(\d{1,2})/i', $text, $m)) {
            return ['slug' => 'iphone-' . $m[1], 'label' => 'iPhone ' . $m[1] . ' 시리즈'];
        }
        if (preg_match('/galaxy\s*(s|a|m|note)\s*(\d{1,2})/i', $text, $m)) {
            return ['slug' => 'galaxy-' . strtolower($m[1]) . $m[2], 'label' => 'Galaxy ' . strtoupper($m[1]) . $m[2] . ' 시리즈'];
        }
        if (preg_match('/galaxy\s*z\s*(fold|flip)\s*(\d{1,2})/i', $text, $m)) {
            return ['slug' => 'galaxy-z-' . strtolower($m[1]) . '-' . $m[2], 'label' => 'Galaxy Z ' . ucfirst(strtolower($m[1])) . ' ' . $m[2] . ' 시리즈'];
        }
        if (preg_match('/pixel\s*(\d{1,2})/i', $text, $m)) {
            return ['slug' => 'pixel-' . $m[1], 'label' => 'Google Pixel ' . $m[1] . ' 시리즈'];
        }
    }
    if ($post_type === 'laptop') {
        if (preg_match('/macbook\s*(air|pro).*?\bm([1-9])\b/i', $text, $m)) {
            return ['slug' => 'macbook-' . strtolower($m[1]) . '-m' . $m[2], 'label' => 'MacBook ' . ucfirst(strtolower($m[1])) . ' M' . $m[2] . ' 시리즈'];
        }
    }
    if ($post_type === 'cpu') {
        if (preg_match('/ryzen\s*[3579]\s*(\d{4})/i', $text, $m)) {
            $generation = (int) floor(((int) $m[1]) / 1000) * 1000;
            return ['slug' => 'ryzen-' . $generation, 'label' => 'AMD Ryzen ' . $generation . ' 시리즈'];
        }
        if (preg_match('/core\s*ultra\s*[3579]\s*(\d{3})/i', $text, $m)) {
            $series = (int) floor(((int) $m[1]) / 100) * 100;
            return ['slug' => 'core-ultra-' . $series, 'label' => 'Intel Core Ultra ' . $series . ' 시리즈'];
        }
        if (preg_match('/core\s*i[3579][\s-]*(\d{4,5})/i', $text, $m)) {
            $digits = $m[1];
            $generation = strlen($digits) === 5 ? (int) substr($digits, 0, 2) : (int) substr($digits, 0, 1);
            return ['slug' => 'intel-core-' . $generation, 'label' => 'Intel Core ' . $generation . '세대'];
        }
    }
    if ($post_type === 'gpu') {
        if (preg_match('/\b(rtx|gtx)\s*(\d{4})/i', $text, $m)) {
            $series = substr($m[2], 0, 2);
            return ['slug' => strtolower($m[1]) . '-' . $series, 'label' => 'NVIDIA ' . strtoupper($m[1]) . ' ' . $series . ' 시리즈'];
        }
        if (preg_match('/\brx\s*(\d{4})/i', $text, $m)) {
            $series = (int) floor(((int) $m[1]) / 1000) * 1000;
            return ['slug' => 'radeon-rx-' . $series, 'label' => 'AMD Radeon RX ' . $series . ' 시리즈'];
        }
        if (preg_match('/\bapple\s*m([1-9])\b/i', $text, $m)) {
            return ['slug' => 'apple-m' . $m[1] . '-gpu', 'label' => 'Apple M' . $m[1] . ' GPU 시리즈'];
        }
    }
    return null;
}

function pc_assign_product_series(int $post_id): void
{
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, ['phone', 'laptop', 'cpu', 'gpu'], true)) {
        return;
    }
    $series = pc_detect_product_series($post->post_type, $post->post_title, $post->post_name);
    if ($series) {
        update_post_meta($post_id, '_catalog_series_slug', $series['slug']);
        update_post_meta($post_id, '_catalog_series_label', $series['label']);
    } else {
        delete_post_meta($post_id, '_catalog_series_slug');
        delete_post_meta($post_id, '_catalog_series_label');
    }
}

function pc_series_url(string $post_type, string $slug): string
{
    return home_url('/series/' . sanitize_key($post_type) . '/' . sanitize_title($slug) . '/');
}

function pc_register_series_routes(): void
{
    add_rewrite_rule(
        '^series/(phone|laptop|cpu|gpu)/([^/]+)/page/([0-9]+)/?$',
        'index.php?pc_series=1&pc_series_type=$matches[1]&pc_series_slug=$matches[2]&paged=$matches[3]',
        'top'
    );
    add_rewrite_rule(
        '^series/(phone|laptop|cpu|gpu)/([^/]+)/?$',
        'index.php?pc_series=1&pc_series_type=$matches[1]&pc_series_slug=$matches[2]',
        'top'
    );
}

function pc_series_query_vars(array $vars): array
{
    return array_merge($vars, ['pc_series', 'pc_series_type', 'pc_series_slug']);
}

function pc_is_series(): bool
{
    return (bool) get_query_var('pc_series');
}

function pc_series_posts(int $limit = 80): array
{
    if (!pc_is_series()) {
        return [];
    }
    return get_posts([
        'post_type' => sanitize_key((string) get_query_var('pc_series_type')),
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_query' => [
            'series_clause' => [
                'key' => '_catalog_series_slug',
                'value' => sanitize_title((string) get_query_var('pc_series_slug')),
            ],
            'release_clause' => [
                'key' => '_catalog_release_date',
                'compare' => 'EXISTS',
                'type' => 'DATE',
            ],
        ],
        'orderby' => [
            'release_clause' => 'DESC',
            'date' => 'DESC',
        ],
        'no_found_rows' => true,
    ]);
}

function pc_series_template(string $template): string
{
    if (!pc_is_series()) {
        return $template;
    }
    $series_template = locate_template('series.php');
    return $series_template ?: $template;
}
