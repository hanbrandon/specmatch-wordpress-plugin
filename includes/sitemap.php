<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_selected_comparisons(int $limit = 100): array
{
    $posts = (new WP_Query(array_merge([
        'post_type' => 'phone',
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'no_found_rows' => true,
    ], pc_newest_query_args())))->posts;
    $devices = array_values(array_filter(array_map(
        static fn(WP_Post $post): ?object => pc_get_device((int) $post->ID),
        $posts
    )));
    $candidates = [];

    for ($i = 0, $total = count($devices); $i < $total; $i++) {
        for ($j = $i + 1; $j < $total; $j++) {
            $a = $devices[$i];
            $b = $devices[$j];
            $candidates[] = [
                'a' => $a,
                'b' => $b,
                'url' => pc_compare_url($a, $b),
                'lastmod' => max((string) $a->updated_at, (string) $b->updated_at),
                'score' => pc_phone_comparison_score($a, $b),
            ];
        }
    }
    usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $ranked = [];
    foreach ($candidates as $candidate) {
        if (!pc_compare_is_indexable($candidate['a'], $candidate['b'])) {
            continue;
        }
        $ranked[] = $candidate;
        if (count($ranked) >= $limit) {
            break;
        }
    }
    return array_map(static function (array $item): array {
        unset($item['score']);
        return $item;
    }, $ranked);
}

function pc_selected_tech_comparisons(int $limit_per_type = 25): array
{
    $comparisons = [];
    foreach (['laptop', 'cpu', 'gpu', 'ssd'] as $type) {
        $query_args = [
            'post_type' => $type,
            'post_status' => 'publish',
            'posts_per_page' => 18,
            'order' => 'DESC',
            'no_found_rows' => true,
        ];
        if ($type === 'ssd') {
            $query_args['meta_key'] = '_catalog_release_date';
            $query_args['orderby'] = ['meta_value' => 'DESC', 'date' => 'DESC'];
        } else {
            $query_args['meta_key'] = '_tech_score';
            $query_args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
        }
        $posts = get_posts($query_args);
        $type_count = 0;
        for ($i = 0, $total = count($posts); $i < $total; $i++) {
            for ($j = $i + 1; $j < min($total, $i + 3); $j++) {
                if (!pc_compare_tech_is_indexable($posts[$i], $posts[$j])) {
                    continue;
                }
                $comparisons[] = [
                    'url' => pc_compare_tech_url($posts[$i], $posts[$j]),
                    'lastmod' => max($posts[$i]->post_modified_gmt, $posts[$j]->post_modified_gmt),
                ];
                $type_count++;
                if ($type_count >= $limit_per_type) {
                    break 2;
                }
            }
        }
    }
    return $comparisons;
}

function pc_hardware_brand_sitemap_urls(): array
{
    global $wpdb;
    $urls = [];
    foreach (['laptop', 'cpu', 'gpu', 'ssd'] as $type) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.slug, MAX(p.post_modified_gmt) AS lastmod
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id=p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='hardware_brand'
             INNER JOIN {$wpdb->terms} t ON t.term_id=tt.term_id
             WHERE p.post_type=%s AND p.post_status='publish'
             GROUP BY t.term_id, t.slug",
            $type
        ));
        foreach ($rows as $row) {
            $urls[] = [
                'loc' => pc_hardware_brand_url($type, $row->slug),
                'lastmod' => mysql2date(DATE_W3C, $row->lastmod, false),
            ];
        }
    }
    return $urls;
}

function pc_series_sitemap_urls(): array
{
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT p.post_type, slug.meta_value AS series_slug, MAX(p.post_modified_gmt) AS lastmod
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} slug ON slug.post_id=p.ID AND slug.meta_key='_catalog_series_slug'
         WHERE p.post_status='publish' AND p.post_type IN ('phone','laptop','cpu','gpu','ssd') AND slug.meta_value <> ''
         GROUP BY p.post_type, slug.meta_value"
    );
    return array_map(static fn($row): array => [
        'loc' => pc_series_url($row->post_type, $row->series_slug),
        'lastmod' => mysql2date(DATE_W3C, $row->lastmod, false),
    ], $rows);
}

function pc_register_comparison_sitemap(): void
{
    add_rewrite_rule('^ssd-landing-sitemap\.xml$', 'index.php?pc_ssd_sitemap=landing', 'top');
    add_rewrite_rule('^ssd-comparison-sitemap\.xml$', 'index.php?pc_ssd_sitemap=comparison', 'top');
    if (!function_exists('wp_register_sitemap_provider') || !class_exists('WP_Sitemaps_Provider')) {
        return;
    }

    $provider = new class extends WP_Sitemaps_Provider {
        public function __construct()
        {
            $this->name = 'phone-comparisons';
            $this->object_type = 'comparison';
        }

        public function get_url_list($page_num, $object_subtype = ''): array
        {
            if ((int) $page_num !== 1) {
                return [];
            }
            return array_map(
                static fn(array $comparison): array => [
                    'loc' => $comparison['url'],
                    'lastmod' => mysql2date(DATE_W3C, $comparison['lastmod'], false),
                ],
                pc_selected_comparisons()
            );
        }

        public function get_max_num_pages($object_subtype = ''): int
        {
            return pc_selected_comparisons() ? 1 : 0;
        }
    };

    wp_register_sitemap_provider('phone-comparisons', $provider);

    $tech_provider = new class extends WP_Sitemaps_Provider {
        public function __construct()
        {
            $this->name = 'hardware-comparisons';
            $this->object_type = 'comparison';
        }
        public function get_url_list($page_num, $object_subtype = ''): array
        {
            if ((int) $page_num !== 1) {
                return [];
            }
            return array_map(static fn(array $item): array => [
                'loc' => $item['url'],
                'lastmod' => mysql2date(DATE_W3C, $item['lastmod'], false),
            ], pc_selected_tech_comparisons());
        }
        public function get_max_num_pages($object_subtype = ''): int
        {
            return pc_selected_tech_comparisons() ? 1 : 0;
        }
    };
    wp_register_sitemap_provider('hardware-comparisons', $tech_provider);

    $brand_provider = new class extends WP_Sitemaps_Provider {
        public function __construct()
        {
            $this->name = 'hardware-brands';
            $this->object_type = 'brand';
        }
        public function get_url_list($page_num, $object_subtype = ''): array
        {
            return (int) $page_num === 1 ? pc_hardware_brand_sitemap_urls() : [];
        }
        public function get_max_num_pages($object_subtype = ''): int
        {
            return pc_hardware_brand_sitemap_urls() ? 1 : 0;
        }
    };
    wp_register_sitemap_provider('hardware-brands', $brand_provider);

    $series_provider = new class extends WP_Sitemaps_Provider {
        public function __construct()
        {
            $this->name = 'product-series';
            $this->object_type = 'series';
        }
        public function get_url_list($page_num, $object_subtype = ''): array
        {
            return (int) $page_num === 1 ? pc_series_sitemap_urls() : [];
        }
        public function get_max_num_pages($object_subtype = ''): int
        {
            return pc_series_sitemap_urls() ? 1 : 0;
        }
    };
    wp_register_sitemap_provider('product-series', $series_provider);
}

function pc_ssd_sitemap_query_vars(array $vars): array
{
    $vars[] = 'pc_ssd_sitemap';
    return $vars;
}
add_filter('query_vars', 'pc_ssd_sitemap_query_vars');

function pc_render_ssd_sitemap(): void
{
    $type = sanitize_key((string) get_query_var('pc_ssd_sitemap'));
    if (!$type) {
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if ($path === 'ssd-landing-sitemap.xml') $type = 'landing';
        elseif ($path === 'ssd-comparison-sitemap.xml') $type = 'comparison';
    }
    if (!in_array($type, ['landing', 'comparison'], true)) return;
    $urls = [];
    if ($type === 'landing') {
        $slugs = ['1tb', '2tb', '4tb', 'nvme-gen4', 'nvme-gen5', 'sata', 'ps5-compatible', 'tlc', 'qlc', 'dram', 'hmb', 'high-endurance'];
        $lastmod = get_lastpostmodified('GMT') ?: gmdate('Y-m-d H:i:s');
        foreach ($slugs as $slug) $urls[] = ['loc' => home_url('/ssds/' . $slug . '/'), 'lastmod' => mysql2date(DATE_W3C, $lastmod, false)];
    } else {
        foreach (pc_selected_tech_comparisons(40) as $item) {
            if (str_contains($item['url'], '/compare/ssd/')) $urls[] = ['loc' => $item['url'], 'lastmod' => mysql2date(DATE_W3C, $item['lastmod'], false)];
        }
    }
    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow', true);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $url) echo '<url><loc>' . esc_url($url['loc']) . '</loc><lastmod>' . esc_html($url['lastmod']) . '</lastmod></url>';
    echo '</urlset>';
    exit;
}
add_action('template_redirect', 'pc_render_ssd_sitemap', 0);

function pc_add_ssd_sitemaps_to_yoast(string $index): string
{
    $date = esc_html(mysql2date(DATE_W3C, get_lastpostmodified('GMT') ?: gmdate('Y-m-d H:i:s'), false));
    foreach (['ssd-landing-sitemap.xml', 'ssd-comparison-sitemap.xml'] as $file) {
        $index .= '<sitemap><loc>' . esc_url(home_url('/' . $file)) . '</loc><lastmod>' . $date . '</lastmod></sitemap>';
    }
    return $index;
}
add_filter('wpseo_sitemap_index', 'pc_add_ssd_sitemaps_to_yoast');
