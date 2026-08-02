<?php

if (!defined('ABSPATH')) {
    exit;
}

const PC_PHONE_NAME_VERSION = '2';

function pc_phone_name_ko(string $model, string $brand = ''): string
{
    $name = trim(preg_replace('/\s+/', ' ', $model));
    $brand_key = strtolower(trim($brand));

    if ($brand_key === 'apple' || preg_match('/^Apple\s+/i', $name)) {
        $name = preg_replace('/^Apple\s+/i', '', $name);
        $replacements = [
            '/\biPhone\s*/i' => '아이폰 ',
            '/\biPad\s*/i' => '아이패드 ',
            '/\bPro\s*Max\b/i' => '프로 맥스',
            '/\bPro\b/i' => '프로',
            '/\bPlus\b/i' => '플러스',
            '/\bAir\b/i' => '에어',
            '/\bMini\b/i' => '미니',
        ];
        return trim(preg_replace('/\s+/', ' ', preg_replace(array_keys($replacements), array_values($replacements), $name)));
    }

    if ($brand_key === 'samsung' || preg_match('/^Samsung\s+/i', $name)) {
        $name = preg_replace('/^Samsung\s+/i', '', $name);
        $replacements = [
            '/\bGalaxy\s*/i' => '갤럭시 ',
            '/\bZ\s*Fold\s*(\d*)/i' => 'Z 폴드 $1',
            '/\bZ\s*Flip\s*(\d*)/i' => 'Z 플립 $1',
            '/\bUltra\b/i' => '울트라',
            '/\bPlus\b/i' => '플러스',
            '/\bNote\s*/i' => '노트 ',
            '/\bTab\s*/i' => '탭 ',
            '/\bFold\b/i' => '폴드',
            '/\bFlip\b/i' => '플립',
        ];
        return trim(preg_replace('/\s+/', ' ', preg_replace(array_keys($replacements), array_values($replacements), $name)));
    }

    return $name;
}

function pc_phone_search_aliases(string $original, string $localized, string $brand = ''): string
{
    $aliases = [
        $localized,
        preg_replace('/\s+/u', '', $localized),
        $original,
        preg_replace('/\s+/u', '', $original),
    ];
    if ($brand) {
        $brand_ko = strtolower($brand) === 'apple' ? '애플' : (strtolower($brand) === 'samsung' ? '삼성' : $brand);
        $aliases[] = $brand_ko . ' ' . $localized;
        $aliases[] = preg_replace('/\s+/u', '', $brand_ko . $localized);
    }
    return implode(' | ', array_values(array_unique(array_filter(array_map('trim', $aliases)))));
}

function pc_product_name(int $post_id): string
{
    $localized = (string) get_post_meta($post_id, '_pc_name_ko', true);
    return $localized ?: get_the_title($post_id);
}

function pc_product_original_name(int $post_id): string
{
    return (string) get_post_meta($post_id, '_pc_name_en', true);
}

function pc_localize_phone_post(int $post_id): bool
{
    $device = pc_get_device($post_id);
    if (!$device || !in_array(strtolower((string) $device->brand), ['apple', 'samsung'], true)) {
        return false;
    }

    $original = trim((string) $device->model);
    $localized = pc_phone_name_ko($original, (string) $device->brand);
    if (!$original || !$localized) {
        return false;
    }

    update_post_meta($post_id, '_pc_name_en', $original);
    update_post_meta($post_id, '_pc_name_ko', $localized);
    update_post_meta($post_id, '_pc_search_aliases', pc_phone_search_aliases($original, $localized, (string) $device->brand));
    update_post_meta($post_id, '_pc_name_rule_version', PC_PHONE_NAME_VERSION);

    $post = get_post($post_id);
    if ($post && $post->post_title !== $localized) {
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $localized,
            'post_name' => $post->post_name,
        ]);
    }
    return true;
}

function pc_schedule_phone_name_localization(): void
{
    if (!wp_next_scheduled('pc_localize_phone_names_hourly')) {
        wp_schedule_event(time() + 10, 'hourly', 'pc_localize_phone_names_hourly');
    }
}

function pc_localize_phone_names_batch(): void
{
    global $wpdb;
    $devices = pc_table('devices');
    $rows = $wpdb->get_results(
        "SELECT d.post_id FROM {$devices} d
         LEFT JOIN {$wpdb->postmeta} v ON v.post_id=d.post_id AND v.meta_key='_pc_name_rule_version'
         WHERE LOWER(d.brand) IN ('apple','samsung')
           AND (v.meta_id IS NULL OR v.meta_value <> '" . esc_sql(PC_PHONE_NAME_VERSION) . "')
         ORDER BY d.post_id ASC LIMIT 100"
    );
    foreach ($rows as $row) {
        pc_localize_phone_post((int) $row->post_id);
    }
    if (count($rows) === 100) {
        wp_schedule_single_event(time() + 20, 'pc_localize_phone_names_batch');
    }
}

function pc_maybe_schedule_phone_name_localization(): void
{
    pc_schedule_phone_name_localization();
}

function pc_search_post_ids(string $keyword, string $post_type = 'phone', int $limit = 50): array
{
    global $wpdb;
    $keyword = trim($keyword);
    if ($keyword === '') {
        return [];
    }
    $like = '%' . $wpdb->esc_like($keyword) . '%';
    $types = in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu'], true) ? [$post_type] : ['phone', 'laptop', 'cpu', 'gpu'];
    $type_placeholders = implode(',', array_fill(0, count($types), '%s'));
    $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} a ON a.post_id=p.ID AND a.meta_key='_pc_search_aliases'
            WHERE p.post_status='publish' AND p.post_type IN ({$type_placeholders})
              AND (p.post_title LIKE %s OR a.meta_value LIKE %s)
            ORDER BY p.post_date DESC LIMIT %d";
    return array_map('intval', $wpdb->get_col($wpdb->prepare($sql, ...array_merge($types, [$like, $like, $limit]))));
}
