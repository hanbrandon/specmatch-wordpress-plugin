<?php

if (!defined('ABSPATH')) {
    exit;
}

const PC_PHONE_NAME_VERSION = '3';

function pc_default_name_mappings(): array
{
    return [
        'Samsung' => '삼성', 'Huawei' => '화웨이', 'Xiaomi' => '샤오미', 'Apple' => '애플',
        'Galaxy' => '갤럭시', 'iPhone' => '아이폰', 'iPad' => '아이패드',
        'Pro Max' => '프로 맥스', 'Pro' => '프로', 'Ultra' => '울트라', 'Plus' => '플러스',
        'Mini' => '미니', 'Air' => '에어', 'Watch' => '워치', 'Fold' => '폴드', 'Flip' => '플립',
        'Note' => '노트', 'Tab' => '탭',
    ];
}

function pc_name_mappings(): array
{
    $saved = get_option('pc_name_mappings');
    return is_array($saved) && $saved ? $saved : pc_default_name_mappings();
}

function pc_detail_mappings(): array
{
    $saved = get_option('pc_detail_mappings');
    return is_array($saved) ? $saved : [];
}

function pc_apply_detail_mappings(string $text): string
{
    $mappings = pc_detail_mappings();
    uksort($mappings, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ($mappings as $english => $korean) {
        if ($english !== '' && $korean !== '') {
            $text = str_ireplace($english, $korean, $text);
        }
    }
    return $text;
}

function pc_apply_name_mappings(string $name): string
{
    $mappings = pc_name_mappings();
    uksort($mappings, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ($mappings as $english => $korean) {
        if ($english !== '' && $korean !== '') {
            $name = (string) preg_replace('/(?<![A-Za-z])' . preg_quote($english, '/') . '(?![A-Za-z])/i', $korean, $name);
        }
    }
    $name = (string) preg_replace('/([가-힣])(\d)/u', '$1 $2', $name);
    return trim((string) preg_replace('/\s+/u', ' ', $name));
}

function pc_phone_name_ko(string $model, string $brand = ''): string
{
    $name = trim(preg_replace('/\s+/', ' ', $model));
    return pc_apply_name_mappings($name);
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
    $original = (string) get_post_meta($post_id, '_pc_name_en', true);
    if ($original === '') {
        $device = pc_get_device($post_id);
        $original = (string) ($device?->model ?: get_post_field('post_title', $post_id));
    }
    return $original !== '' ? pc_phone_name_ko($original) : (string) get_post_field('post_title', $post_id);
}

function pc_filter_phone_title(string $title, int $post_id = 0): string
{
    if (is_admin() || !$post_id || get_post_type($post_id) !== 'phone') {
        return $title;
    }
    return pc_product_name($post_id);
}

function pc_product_original_name(int $post_id): string
{
    return (string) get_post_meta($post_id, '_pc_name_en', true);
}

function pc_localize_phone_post(int $post_id): bool
{
    $device = pc_get_device($post_id);
    if (!$device) {
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
         WHERE v.meta_id IS NULL OR v.meta_value <> '" . esc_sql(PC_PHONE_NAME_VERSION) . "'
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

function pc_name_mapping_admin_menu(): void
{
    add_options_page('제품명 맵핑', '제품명 맵핑', 'manage_options', 'pc-name-mappings', 'pc_name_mapping_admin_page');
}

function pc_name_mapping_admin_page(): void
{
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['pc_save_name_mappings'])) {
        check_admin_referer('pc_save_name_mappings');
        $mappings = [];
        foreach (preg_split('/\R/u', (string) wp_unslash($_POST['pc_name_mappings'] ?? '')) as $line) {
            $parts = array_map('trim', explode('=', $line, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $mappings[sanitize_text_field($parts[0])] = sanitize_text_field($parts[1]);
            }
        }
        update_option('pc_name_mappings', $mappings ?: pc_default_name_mappings(), false);

        $detail_mappings = [];
        foreach (preg_split('/\R/u', (string) wp_unslash($_POST['pc_detail_mappings'] ?? '')) as $line) {
            $parts = array_map('trim', explode('=', $line, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $detail_mappings[sanitize_text_field($parts[0])] = sanitize_text_field($parts[1]);
            }
        }
        update_option('pc_detail_mappings', $detail_mappings, false);
        delete_post_meta_by_key('_pc_name_rule_version');
        if (!wp_next_scheduled('pc_localize_phone_names_batch')) {
            wp_schedule_single_event(time() + 5, 'pc_localize_phone_names_batch');
        }
        echo '<div class="notice notice-success"><p>맵핑을 저장했습니다. 기존 제품명도 순차적으로 다시 적용됩니다.</p></div>';
    }

    $lines = [];
    foreach (pc_name_mappings() as $english => $korean) $lines[] = $english . '=' . $korean;
    $detail_lines = [];
    foreach (pc_detail_mappings() as $english => $korean) $detail_lines[] = $english . '=' . $korean;
    ?>
    <div class="wrap">
        <h1>제품명 맵핑</h1>
        <p>한 줄에 <code>영문=한국어</code> 형식으로 입력하세요. 원본 영문명과 기존 URL은 유지되어 영문 검색도 가능합니다.</p>
        <form method="post">
            <?php wp_nonce_field('pc_save_name_mappings'); ?>
            <textarea name="pc_name_mappings" rows="18" class="large-text code"><?php echo esc_textarea(implode("\n", $lines)); ?></textarea>
            <h2>상세설명 맵핑</h2>
            <p>스펙 값과 상세설명에서 바꿀 문구를 입력하세요. 문장 일부도 치환할 수 있습니다.</p>
            <textarea name="pc_detail_mappings" rows="12" class="large-text code" placeholder="Sapphire crystal front=사파이어 크리스털 전면\nAlways-on display=상시표시 디스플레이"><?php echo esc_textarea(implode("\n", $detail_lines)); ?></textarea>
            <p><button type="submit" name="pc_save_name_mappings" class="button button-primary">맵핑 저장</button></p>
        </form>
    </div>
    <?php
}

function pc_search_post_ids(string $keyword, string $post_type = 'phone', int $limit = 50): array
{
    global $wpdb;
    $keyword = trim(sanitize_text_field($keyword));
    if ($keyword === '') {
        return [];
    }

    $types = in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu'], true)
        ? [$post_type]
        : ['phone', 'laptop', 'cpu', 'gpu'];
    $limit = max(1, min(5000, $limit));
    $cache_key = 'pc_search_' . md5($keyword . '|' . implode(',', $types) . '|' . $limit);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return array_map('intval', $cached);
    }

    $normalize = static function (string $expression): string {
        foreach ([' ', '-', '_', '/', '.', ',', '(', ')'] as $character) {
            $expression = "REPLACE({$expression}, '" . esc_sql($character) . "', '')";
        }
        return "LOWER({$expression})";
    };
    $compact = mb_strtolower((string) preg_replace('/[\s\-_\/.,()]+/u', '', $keyword));
    $tokens = array_values(array_unique(array_filter(
        preg_split('/[\s\-_\/.,()]+/u', mb_strtolower($keyword)),
        static fn(string $token): bool => $token !== ''
    )));

    $devices = pc_table('devices');
    $type_placeholders = implode(',', array_fill(0, count($types), '%s'));
    $haystack = "CONCAT_WS(' ', p.post_title, COALESCE(a.meta_value,''), COALESCE(d.brand,''), COALESCE(d.model,''), COALESCE(d.chipset,''), COALESCE(d.os,''), COALESCE(d.display,''), COALESCE(d.camera,''), COALESCE(d.battery,''), COALESCE(d.ram,''), COALESCE(d.storage,''), COALESCE(ts.meta_value,''))";
    $normalized_title = $normalize('p.post_title');
    $normalized_alias = $normalize("COALESCE(a.meta_value,'')");
    $normalized_model = $normalize("COALESCE(d.model,'')");
    $normalized_haystack = $normalize($haystack);
    $normalized_specs = $normalize("COALESCE(s.field_value,'')");

    $match_parts = ["{$normalized_haystack} LIKE %s", "EXISTS (SELECT 1 FROM " . pc_table('specs') . " s WHERE s.device_id=d.id AND {$normalized_specs} LIKE %s)"];
    $match_args = ['%' . $wpdb->esc_like($compact) . '%', '%' . $wpdb->esc_like($compact) . '%'];
    $token_parts = [];
    $token_args = [];
    foreach ($tokens as $token) {
        $like = '%' . $wpdb->esc_like($token) . '%';
        $token_parts[] = "({$haystack} LIKE %s OR EXISTS (SELECT 1 FROM " . pc_table('specs') . " sx WHERE sx.device_id=d.id AND CONCAT_WS(' ', sx.field_name, sx.field_value) LIKE %s))";
        $token_args[] = $like;
        $token_args[] = $like;
    }
    if ($token_parts) {
        $match_parts[] = '(' . implode(' AND ', $token_parts) . ')';
        $match_args = array_merge($match_args, $token_args);
    }

    $sql = "SELECT DISTINCT p.ID,
                CASE
                    WHEN {$normalized_title} = %s THEN 1000
                    WHEN CONCAT('|', {$normalized_alias}, '|') LIKE %s THEN 950
                    WHEN {$normalized_title} LIKE %s THEN 900
                    WHEN {$normalized_title} LIKE %s THEN 850
                    WHEN {$normalized_title} LIKE %s THEN 700
                    WHEN {$normalized_alias} LIKE %s THEN 650
                    WHEN {$normalized_model} LIKE %s THEN 625
                    ELSE 400
                END AS relevance,
                COALESCE(rd.meta_value, '0000-00-00') AS release_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} a ON a.post_id=p.ID AND a.meta_key='_pc_search_aliases'
            LEFT JOIN {$wpdb->postmeta} ts ON ts.post_id=p.ID AND ts.meta_key='_tech_specs'
            LEFT JOIN {$wpdb->postmeta} rd ON rd.post_id=p.ID AND rd.meta_key='_catalog_release_date'
            LEFT JOIN {$devices} d ON d.post_id=p.ID
            WHERE p.post_status='publish' AND p.post_type IN ({$type_placeholders})
              AND (" . implode(' OR ', $match_parts) . ")
            ORDER BY relevance DESC, release_date DESC, p.post_title ASC
            LIMIT %d";
    $compact_like = '%' . $wpdb->esc_like($compact) . '%';
    $args = array_merge(
        [$compact, '%|' . $wpdb->esc_like($compact) . '|%', '%' . $wpdb->esc_like($compact), $wpdb->esc_like($compact) . '%', $compact_like, $compact_like, $compact_like],
        $types,
        $match_args,
        [$limit]
    );
    $ids = array_map('intval', $wpdb->get_col($wpdb->prepare($sql, ...$args)));
    set_transient($cache_key, $ids, 5 * MINUTE_IN_SECONDS);
    return $ids;
}
