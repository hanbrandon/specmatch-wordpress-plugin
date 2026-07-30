<?php
/**
 * Plugin Name: SpecMatch Catalog
 * Description: 휴대폰 스펙, 비교, 제휴 상품과 프로그램매틱 SEO 페이지를 관리합니다.
 * Version: 0.1.1
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: SpecMatch
 * Text Domain: phone-catalog
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PC_VERSION', '0.1.1');
define('PC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PC_PLUGIN_DIR . 'includes/schema.php';
require_once PC_PLUGIN_DIR . 'includes/content.php';
require_once PC_PLUGIN_DIR . 'includes/catalog.php';
require_once PC_PLUGIN_DIR . 'includes/translations.php';
require_once PC_PLUGIN_DIR . 'includes/insights.php';
require_once PC_PLUGIN_DIR . 'includes/compare.php';
require_once PC_PLUGIN_DIR . 'includes/seo.php';
require_once PC_PLUGIN_DIR . 'includes/rest.php';
require_once PC_PLUGIN_DIR . 'includes/sitemap.php';
require_once PC_PLUGIN_DIR . 'includes/series.php';
require_once PC_PLUGIN_DIR . 'includes/metrics.php';

register_activation_hook(__FILE__, 'pc_activate');

function pc_activate(): void
{
    pc_install_schema();
    pc_register_content_types();
    pc_register_compare_routes();
    pc_register_series_routes();
    pc_register_media_routes();
    pc_ensure_pages();
    flush_rewrite_rules();
    update_option('pc_rewrite_version', PC_VERSION, false);
}

add_action('init', 'pc_register_content_types');
add_action('init', 'pc_register_series_routes');
add_action('init', 'pc_schedule_metrics_cleanup');
add_action('pc_cleanup_old_metrics', 'pc_cleanup_old_metrics');
add_action('init', 'pc_register_compare_routes');
add_action('init', 'pc_register_media_routes');
add_action('init', 'pc_maybe_refresh_rewrite_rules', 99);
add_action('pre_get_posts', 'pc_order_phone_archives_newest_first');
add_action('template_redirect', 'pc_serve_phone_media');
add_action('rest_api_init', 'pc_register_rest_routes');
add_action('rest_api_init', 'pc_register_metrics_routes');
add_action('init', 'pc_register_comparison_sitemap', 20);
add_filter('query_vars', 'pc_compare_query_vars');
add_filter('query_vars', 'pc_series_query_vars');
add_filter('query_vars', 'pc_media_query_vars');
add_filter('template_include', 'pc_compare_template');
add_filter('template_include', 'pc_series_template');
add_filter('wp_robots', 'pc_prevent_public_image_indexing');
add_filter('document_title_parts', 'pc_document_title_parts');
add_action('wp_head', 'pc_output_seo', 20);
add_action('template_redirect', 'pc_redirect_legacy_hardware_brand_url', 5);
add_action('template_redirect', 'pc_catalog_empty_filter_status', 20);

function pc_maybe_refresh_rewrite_rules(): void
{
    if (get_option('pc_rewrite_version') === PC_VERSION) {
        return;
    }
    flush_rewrite_rules(false);
    update_option('pc_rewrite_version', PC_VERSION, false);
}

if (defined('WP_CLI') && WP_CLI) {
    require_once PC_PLUGIN_DIR . 'includes/cli.php';
    WP_CLI::add_command('phone-catalog import', 'PC_Import_Command');
    WP_CLI::add_command('phone-catalog seo-audit', 'PC_SEO_Audit_Command');
}
