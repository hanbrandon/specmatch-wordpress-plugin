<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_register_compare_routes(): void
{
    add_rewrite_rule(
        '^compare/(laptop|cpu|gpu)/([^/]+)-vs-([^/]+)/?$',
        'index.php?pc_compare=1&pc_compare_type=$matches[1]&pc_phone_a=$matches[2]&pc_phone_b=$matches[3]',
        'top'
    );
    add_rewrite_rule(
        '^compare/([^/]+)-vs-([^/]+)/?$',
        'index.php?pc_compare=1&pc_phone_a=$matches[1]&pc_phone_b=$matches[2]',
        'top'
    );
}

function pc_compare_query_vars(array $vars): array
{
    $vars[] = 'pc_compare';
    $vars[] = 'pc_phone_a';
    $vars[] = 'pc_phone_b';
    $vars[] = 'pc_compare_type';
    return $vars;
}

function pc_compare_type(): string
{
    $type = sanitize_key((string) get_query_var('pc_compare_type'));
    return in_array($type, ['laptop', 'cpu', 'gpu'], true) ? $type : 'phone';
}

function pc_is_compare(): bool
{
    return (bool) get_query_var('pc_compare');
}

function pc_compare_template(string $template): string
{
    if (!pc_is_compare()) {
        return $template;
    }
    status_header(200);
    if (pc_compare_type() !== 'phone') {
        $tech_template = locate_template('compare-tech.php');
        return $tech_template ?: PC_PLUGIN_DIR . 'templates/compare-tech.php';
    }
    $theme_template = locate_template('compare-phone.php');
    return $theme_template ?: PC_PLUGIN_DIR . 'templates/compare-phone.php';
}

function pc_compare_tech_posts(): array
{
    $type = pc_compare_type();
    if ($type === 'phone') {
        return [null, null];
    }
    $find = static function (string $slug) use ($type): ?WP_Post {
        $posts = get_posts([
            'name' => sanitize_title($slug),
            'post_type' => $type,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);
        return $posts[0] ?? null;
    };
    return [
        $find((string) get_query_var('pc_phone_a')),
        $find((string) get_query_var('pc_phone_b')),
    ];
}

function pc_compare_tech_url(WP_Post $a, WP_Post $b): string
{
    if ($a->ID < $b->ID) {
        [$a, $b] = [$b, $a];
    }
    return home_url('/compare/' . $a->post_type . '/' . $a->post_name . '-vs-' . $b->post_name . '/');
}

function pc_compare_tech_is_indexable(WP_Post $a, WP_Post $b): bool
{
    if ($a->post_type !== $b->post_type || !in_array($a->post_type, ['laptop', 'cpu', 'gpu'], true)) {
        return false;
    }
    $a_specs = json_decode((string) get_post_meta($a->ID, '_tech_specs', true), true) ?: [];
    $b_specs = json_decode((string) get_post_meta($b->ID, '_tech_specs', true), true) ?: [];
    if (count($a_specs) < 20 || count($b_specs) < 20) {
        return false;
    }
    $date_a = (string) get_post_meta($a->ID, '_catalog_release_date', true);
    $date_b = (string) get_post_meta($b->ID, '_catalog_release_date', true);
    if (!$date_a || !$date_b || abs((int) substr($date_a, 0, 4) - (int) substr($date_b, 0, 4)) > 4) {
        return false;
    }
    return get_post_meta($a->ID, '_tech_score', true) !== ''
        && get_post_meta($b->ID, '_tech_score', true) !== '';
}

function pc_compare_tech_current_url_is_canonical(WP_Post $a, WP_Post $b): bool
{
    $expected_a = $a->ID >= $b->ID ? $a->post_name : $b->post_name;
    $expected_b = $a->ID >= $b->ID ? $b->post_name : $a->post_name;
    return get_query_var('pc_phone_a') === $expected_a && get_query_var('pc_phone_b') === $expected_b;
}

function pc_compare_tech_rows(WP_Post $a, WP_Post $b): array
{
    $a_specs = json_decode((string) get_post_meta($a->ID, '_tech_specs', true), true) ?: [];
    $b_specs = json_decode((string) get_post_meta($b->ID, '_tech_specs', true), true) ?: [];
    $rows = [];
    foreach ([['a', $a_specs], ['b', $b_specs]] as [$side, $specs]) {
        foreach ($specs as $spec) {
            $section = (string) ($spec['section'] ?? 'Specifications');
            $field = (string) ($spec['field'] ?? '정보');
            $key = $section . '|' . $field;
            if (!isset($rows[$key])) {
                $rows[$key] = ['section' => $section, 'field' => $field, 'a' => null, 'b' => null, 'same' => false];
            }
            $rows[$key][$side] = (string) ($spec['value'] ?? '');
        }
    }
    foreach ($rows as &$row) {
        $row['same'] = $row['a'] !== null && $row['b'] !== null
            && trim((string) $row['a']) === trim((string) $row['b']);
    }
    unset($row);
    return array_values($rows);
}

function pc_compare_devices(): array
{
    return [
        pc_get_device_by_slug((string) get_query_var('pc_phone_a')),
        pc_get_device_by_slug((string) get_query_var('pc_phone_b')),
    ];
}

function pc_compare_url(object $a, object $b): string
{
    if ((int) $a->source_id < (int) $b->source_id) {
        [$a, $b] = [$b, $a];
    }
    return home_url('/compare/' . get_post_field('post_name', $a->post_id) . '-vs-' . get_post_field('post_name', $b->post_id) . '/');
}

function pc_device_release_year(object $device): ?int
{
    if (!empty($device->release_year)) {
        return (int) $device->release_year;
    }
    if (!empty($device->announced) && preg_match('/\b(19|20)\d{2}\b/', $device->announced, $match)) {
        return (int) $match[0];
    }
    return null;
}

function pc_compare_is_indexable(object $a, object $b): bool
{
    global $wpdb;
    $specs = pc_table('specs');
    $counts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT device_id, COUNT(*) AS total FROM {$specs}
             WHERE device_id IN (%d, %d) GROUP BY device_id",
            $a->id,
            $b->id
        ),
        OBJECT_K
    );
    if (
        empty($counts[$a->id]) || empty($counts[$b->id])
        || (int) $counts[$a->id]->total < 25 || (int) $counts[$b->id]->total < 25
    ) {
        return false;
    }

    $year_a = pc_device_release_year($a);
    $year_b = pc_device_release_year($b);
    $recent_cutoff = (int) gmdate('Y') - 3;
    $recent_pair = $year_a && $year_b
        && max($year_a, $year_b) >= $recent_cutoff
        && abs($year_a - $year_b) <= 4;
    $popular_pair = (float) get_post_meta($a->post_id, '_pc_popularity', true) >= 0.5
        && (float) get_post_meta($b->post_id, '_pc_popularity', true) >= 0.5;

    return $recent_pair || $popular_pair;
}

function pc_compare_current_url_is_canonical(object $a, object $b): bool
{
    $expected_a = (string) get_post_field('post_name', (int) ((int) $a->source_id >= (int) $b->source_id ? $a->post_id : $b->post_id));
    $expected_b = (string) get_post_field('post_name', (int) ((int) $a->source_id >= (int) $b->source_id ? $b->post_id : $a->post_id));
    return get_query_var('pc_phone_a') === $expected_a && get_query_var('pc_phone_b') === $expected_b;
}

function pc_compare_rows(object $a, object $b): array
{
    $a_specs = pc_get_specs((int) $a->id);
    $b_specs = pc_get_specs((int) $b->id);
    $rows = [];

    foreach ($a_specs as $spec) {
        $key = $spec->section_name . '|' . ($spec->field_name ?: '메모');
            $rows[$key] = [
                'section' => $spec->section_name,
                'field' => $spec->field_name ?: '메모',
                'a' => $spec->field_value,
                'b' => null,
                'same' => false,
            ];
    }
    foreach ($b_specs as $spec) {
        $key = $spec->section_name . '|' . ($spec->field_name ?: '메모');
        if (!isset($rows[$key])) {
            $rows[$key] = [
                'section' => $spec->section_name,
                'field' => $spec->field_name ?: '메모',
                'a' => null,
                'b' => $spec->field_value,
                'same' => false,
            ];
        } else {
            $rows[$key]['b'] = $spec->field_value;
            $rows[$key]['same'] = trim((string) $rows[$key]['a']) === trim((string) $spec->field_value);
        }
    }
    return array_values($rows);
}
