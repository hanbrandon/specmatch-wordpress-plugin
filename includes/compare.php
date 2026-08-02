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

function pc_phone_comparison_family(object $device): string
{
    $model = strtolower((string) $device->model);
    foreach ([
        '/iphone\s*(\d+)/' => 'iphone-$1',
        '/galaxy\s*s(\d+)/' => 'galaxy-s$1',
        '/galaxy\s*z\s*fold\s*(\d+)/' => 'galaxy-fold-$1',
        '/galaxy\s*z\s*flip\s*(\d+)/' => 'galaxy-flip-$1',
        '/pixel\s*(\d+)/' => 'pixel-$1',
    ] as $pattern => $family) {
        if (preg_match($pattern, $model, $match)) {
            return str_replace('$1', $match[1], $family);
        }
    }
    return sanitize_title((string) $device->brand);
}

function pc_phone_comparison_score(object $a, object $b): float
{
    $year_a = pc_device_release_year($a) ?: 0;
    $year_b = pc_device_release_year($b) ?: 0;
    $score = max($year_a, $year_b) * 0.01;
    $same_brand = strcasecmp((string) $a->brand, (string) $b->brand) === 0;
    if ($same_brand) {
        $score += 35;
    }
    if (pc_phone_comparison_family($a) === pc_phone_comparison_family($b)) {
        $score += 65;
    }
    $year_gap = $year_a && $year_b ? abs($year_a - $year_b) : 99;
    if ($year_gap === 0) {
        $score += 30;
    } elseif ($year_gap === 1) {
        $score += 18;
    }
    $brands = array_map('strtolower', [(string) $a->brand, (string) $b->brand]);
    sort($brands);
    if ($brands === ['apple', 'samsung'] && $year_gap <= 1) {
        $score += 20;
    }
    $score += min(10, (float) get_post_meta((int) $a->post_id, '_pc_popularity', true));
    $score += min(10, (float) get_post_meta((int) $b->post_id, '_pc_popularity', true));
    return $score;
}

function pc_priority_comparisons_for_device(object $device, int $limit = 4): array
{
    $cache_key = 'pc_priority_compare_' . (int) $device->post_id . '_' . $limit;
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_values(array_filter(array_map(
            static fn(int $post_id): ?object => pc_get_device($post_id),
            array_map('intval', $cached_ids)
        )));
    }
    $posts = (new WP_Query(array_merge([
        'post_type' => 'phone',
        'post_status' => 'publish',
        'post__not_in' => [(int) $device->post_id],
        'posts_per_page' => 36,
        'no_found_rows' => true,
    ], pc_newest_query_args())))->posts;
    $ranked = [];
    foreach ($posts as $post) {
        $other = pc_get_device((int) $post->ID);
        if (!$other) {
            continue;
        }
        $ranked[] = ['device' => $other, 'score' => pc_phone_comparison_score($device, $other)];
    }
    usort($ranked, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $selected = [];
    foreach ($ranked as $candidate) {
        if (!pc_compare_is_indexable($device, $candidate['device'])) {
            continue;
        }
        $selected[] = $candidate['device'];
        if (count($selected) >= $limit) {
            break;
        }
    }
    set_transient($cache_key, array_map(static fn(object $item): int => (int) $item->post_id, $selected), 6 * HOUR_IN_SECONDS);
    return $selected;
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
