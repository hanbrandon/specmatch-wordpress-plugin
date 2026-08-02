<?php

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class PC_Import_Command
{
    /**
     * NDJSON 휴대폰 데이터를 가져옵니다.
     *
     * ## OPTIONS
     *
     * <file>
     * : 컨테이너 안의 NDJSON 경로
     *
     * [--limit=<number>]
     * : 최대 처리 건수
     *
     * [--release-only]
     * : 기존 글의 정규화된 출시일 메타만 갱신
     *
     * ## EXAMPLES
     *
     *     wp phone-catalog import /imports/phones.ndjson --limit=50
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $path = $args[0];
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0;
        $release_only = isset($assoc_args['release-only']);
        if (!is_readable($path)) {
            WP_CLI::error("파일을 읽을 수 없습니다: {$path}");
        }

        pc_install_schema();
        $handle = fopen($path, 'rb');
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            if ($limit && $count >= $limit) {
                break;
            }
            $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if ($release_only) {
                $this->import_release_only($item);
            } elseif (in_array($item['post_type'] ?? '', ['laptop', 'cpu', 'gpu'], true)) {
                $this->import_tech($item);
            } else {
                $this->import_phone($item);
            }
            $count++;
            if ($count % 100 === 0) {
                WP_CLI::log(number_format($count) . '개 처리');
            }
        }
        fclose($handle);
        WP_CLI::success(number_format($count) . '개 기기를 가져왔습니다.');
    }

    private function import_release_only(array $item): void
    {
        global $wpdb;
        $post_type = sanitize_key($item['post_type'] ?? 'phone');
        if ($post_type === 'phone') {
            $devices = pc_table('devices');
            $post_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$devices} WHERE source_id = %d",
                (int) ($item['source_id'] ?? 0)
            ));
        } else {
            $source_key = sanitize_text_field($item['source_key'] ?? '');
            $post_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID
                 WHERE p.post_type=%s AND pm.meta_key='_tech_source_key' AND pm.meta_value=%s
                 LIMIT 1",
                $post_type,
                $source_key
            ));
        }
        if ($post_id) {
            $this->update_release_meta($post_id, $item);
        }
    }

    private function import_tech(array $item): void
    {
        $post_type = sanitize_key($item['post_type'] ?? '');
        if (!in_array($post_type, ['laptop', 'cpu', 'gpu'], true)) {
            throw new InvalidArgumentException('지원하지 않는 제품 유형입니다.');
        }

        $source_key = sanitize_text_field($item['source_key'] ?? '');
        $existing = get_posts([
            'post_type' => $post_type,
            'post_status' => 'any',
            'meta_key' => '_tech_source_key',
            'meta_value' => $source_key,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);
        $post_id = wp_insert_post([
            'ID' => $existing ? (int) $existing[0] : 0,
            'post_type' => $post_type,
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($item['name'] ?? ''),
            'post_name' => sanitize_title($item['slug'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($item['description'] ?? ''),
            'post_content' => $this->tech_content($item),
        ], true);
        if (is_wp_error($post_id)) {
            throw new RuntimeException($post_id->get_error_message());
        }

        if (!empty($item['brand'])) {
            wp_set_object_terms($post_id, sanitize_text_field($item['brand']), 'hardware_brand');
        }
        $scores = is_array($item['scores'] ?? null) ? $item['scores'] : [];
        $score_names = $post_type === 'laptop'
            ? ['NanoReview Score', 'Final Score']
            : ['NanoReview Final Score', 'Final Score'];
        $main_score = '';
        foreach ($score_names as $score_name) {
            if (isset($scores[$score_name])) {
                $main_score = (float) $scores[$score_name];
                break;
            }
        }

        update_post_meta($post_id, '_tech_source_key', $source_key);
        update_post_meta($post_id, '_tech_source_url', esc_url_raw($item['source_url'] ?? ''));
        update_post_meta($post_id, '_tech_image_url', esc_url_raw($item['image_url'] ?? ''));
        update_post_meta($post_id, '_tech_launched', sanitize_text_field($item['launched'] ?? ''));
        $this->update_release_meta($post_id, $item);
        update_post_meta($post_id, '_tech_score', $main_score);
        update_post_meta($post_id, '_tech_scores', wp_json_encode($scores, JSON_UNESCAPED_UNICODE));
        update_post_meta($post_id, '_tech_specs', wp_json_encode($item['specs'] ?? [], JSON_UNESCAPED_UNICODE));
        update_post_meta($post_id, '_tech_configurations', wp_json_encode($item['configurations'] ?? [], JSON_UNESCAPED_UNICODE));
        update_post_meta($post_id, '_tech_content_hash', sanitize_text_field($item['content_hash'] ?? ''));
        update_post_meta($post_id, '_tech_source_updated_at', sanitize_text_field($item['updated_at'] ?? ''));
        pc_assign_product_series((int) $post_id);
        clean_post_cache($post_id);
    }

    private function update_release_meta(int $post_id, array $item): void
    {
        $release_date = sanitize_text_field($item['release_date'] ?? '');
        $release_year = (int) ($item['release_year'] ?? 0);
        $release_state = sanitize_key($item['release_state'] ?? 'unknown');
        update_post_meta($post_id, '_catalog_release_date', $release_date);
        update_post_meta($post_id, '_catalog_release_year', $release_year ?: '');
        update_post_meta($post_id, '_catalog_release_precision', sanitize_key($item['release_precision'] ?? ''));
        update_post_meta($post_id, '_catalog_release_state', $release_state);
        update_post_meta($post_id, '_catalog_age_status', $this->catalog_age_status($release_date, $release_state));
    }

    private function catalog_age_status(string $release_date, string $release_state): string
    {
        if ($release_state === 'upcoming') {
            return 'upcoming';
        }
        if (!$release_date) {
            return 'unknown';
        }
        try {
            $released = new DateTimeImmutable($release_date);
            $today = new DateTimeImmutable('today');
        } catch (Exception) {
            return 'unknown';
        }
        if ($released > $today) {
            return 'upcoming';
        }
        $months = ((int) $today->format('Y') - (int) $released->format('Y')) * 12
            + ((int) $today->format('n') - (int) $released->format('n'));
        if ($months <= 6) {
            return 'new';
        }
        if ($months <= 12) {
            return 'recent';
        }
        if ($months <= 36) {
            return 'previous';
        }
        return 'legacy';
    }

    private function tech_content(array $item): string
    {
        $html = '';
        $image = esc_url($item['image_url'] ?? '');
        if ($image) {
            $html .= '<figure class="tech-product-image"><img src="' . $image . '" alt="' .
                esc_attr($item['name'] ?? '') . '" loading="eager" decoding="async" referrerpolicy="no-referrer"></figure>';
        }
        $scores = is_array($item['scores'] ?? null) ? $item['scores'] : [];
        if ($scores) {
            $html .= '<section class="tech-data-section"><header><span>PERFORMANCE</span><h2>평가 및 벤치마크</h2></header><div class="tech-score-grid">';
            foreach ($scores as $label => $score) {
                $html .= '<div><span>' . esc_html((string) $label) . '</span><strong>' . esc_html((string) $score) . '</strong></div>';
            }
            $html .= '</div></section>';
        }
        $configurations = is_array($item['configurations'] ?? null) ? $item['configurations'] : [];
        if ($configurations) {
            $html .= '<section class="tech-data-section"><header><span>CONFIGURATIONS</span><h2>선택 가능한 구성</h2></header>';
            foreach ($configurations as $category => $options) {
                $html .= '<div class="tech-config-row"><strong>' . esc_html((string) $category) . '</strong><div>';
                foreach ((array) $options as $option) {
                    $class = !empty($option['is_default']) ? ' class="is-default"' : '';
                    $html .= '<span' . $class . '>' . esc_html((string) ($option['label'] ?? '')) . '</span>';
                }
                $html .= '</div></div>';
            }
            $html .= '</section>';
        }
        $grouped = [];
        foreach ((array) ($item['specs'] ?? []) as $spec) {
            $grouped[(string) ($spec['section'] ?? 'Specifications')][] = $spec;
        }
        if ($grouped) {
            $html .= '<section class="tech-data-section"><header><span>FULL SPECIFICATIONS</span><h2>전체 사양</h2></header>';
            foreach ($grouped as $section => $specs) {
                $html .= '<div class="tech-spec-group"><h3>' . esc_html($section) . '</h3><dl>';
                foreach ($specs as $spec) {
                    $html .= '<div><dt>' . esc_html((string) ($spec['field'] ?? '')) . '</dt><dd>' .
                        esc_html((string) ($spec['value'] ?? '')) . '</dd></div>';
                }
                $html .= '</dl></div>';
            }
            $html .= '</section>';
        }
        return $html;
    }

    private function import_phone(array $item): void
    {
        global $wpdb;
        $devices = pc_table('devices');
        $specs = pc_table('specs');
        $existing_post = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$devices} WHERE source_id = %d",
                $item['source_id']
            )
        );
        if (!$existing_post) {
            $existing_post = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key = '_pc_source_id' AND meta_value = %s
                     ORDER BY post_id ASC LIMIT 1",
                    (string) $item['source_id']
                )
            );
        }

        $post_id = wp_insert_post([
            'ID' => $existing_post ? (int) $existing_post : 0,
            'post_type' => 'phone',
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($item['model']),
            'post_name' => sanitize_title($item['slug']),
            'post_excerpt' => sanitize_textarea_field($item['description'] ?? ''),
        ], true);
        if (is_wp_error($post_id)) {
            throw new RuntimeException($post_id->get_error_message());
        }

        wp_set_object_terms($post_id, $item['brand'], 'phone_brand');
        update_post_meta($post_id, '_pc_source_id', (int) $item['source_id']);
        if (!empty($item['announced_date'])) {
            update_post_meta($post_id, '_pc_announced_date', sanitize_text_field($item['announced_date']));
        }
        if (isset($item['popularity']) && preg_match('/[\d.]+/', (string) $item['popularity'], $popularity)) {
            update_post_meta($post_id, '_pc_popularity', (float) $popularity[0]);
        }
        if (isset($item['fans']) && preg_match('/\d+/', str_replace(',', '', (string) $item['fans']), $fans)) {
            update_post_meta($post_id, '_pc_fans', (int) $fans[0]);
        }
        if (!empty($item['year'])) {
            wp_set_object_terms($post_id, (string) $item['year'], 'phone_year');
        }
        $this->update_release_meta($post_id, $item);
        pc_assign_product_series((int) $post_id);

        $now = current_time('mysql', true);
        $replaced = $wpdb->replace($devices, [
            'post_id' => $post_id,
            'source_id' => $item['source_id'],
            'brand' => $item['brand'],
            'model' => $item['model'],
            'source_url' => $item['source_url'],
            'image_url' => $item['image_url'] ?? null,
            'announced' => $item['announced'] ?? null,
            'announced_date' => $item['announced_date'] ?? null,
            'release_year' => $item['year'] ?? null,
            'status' => $item['status'] ?? null,
            'os' => $item['os'] ?? null,
            'chipset' => $item['chipset'] ?? null,
            'display' => $item['display'] ?? null,
            'camera' => isset($item['camera']) ? mb_substr((string) $item['camera'], 0, 240) : null,
            'battery' => $item['battery'] ?? null,
            'ram' => $item['ram'] ?? null,
            'storage' => $item['storage'] ?? null,
            'content_hash' => $item['content_hash'],
            'source_updated_at' => $item['updated_at'] ?? null,
            'updated_at' => $now,
        ]);
        if ($replaced === false) {
            throw new RuntimeException(
                '기기 테이블 저장 실패 (source_id ' . (int) $item['source_id'] . '): ' . $wpdb->last_error
            );
        }

        $device_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$devices} WHERE source_id = %d", $item['source_id'])
        );
        $wpdb->delete($specs, ['device_id' => $device_id]);
        foreach ($item['specs'] ?? [] as $spec) {
            $wpdb->insert($specs, [
                'device_id' => $device_id,
                'section_order' => $spec['section_order'],
                'row_order' => $spec['row_order'],
                'section_name' => $spec['section'],
                'field_name' => $spec['field'],
                'field_value' => $spec['value'],
                'data_spec' => $spec['data_spec'],
            ]);
        }
        clean_post_cache($post_id);
    }
}

class PC_SEO_Audit_Command
{
    public function __invoke(array $args, array $assoc_args): void
    {
        global $wpdb;
        $checks = [];
        $home = home_url('/');
        $host = (string) wp_parse_url($home, PHP_URL_HOST);
        $checks[] = ['실제 도메인', !in_array($host, ['localhost', '127.0.0.1'], true), $home];
        $checks[] = ['HTTPS', str_starts_with($home, 'https://'), $home];
        $checks[] = ['검색엔진 공개', (string) get_option('blog_public') === '1', 'blog_public=' . get_option('blog_public')];
        $checks[] = ['고유주소', (bool) get_option('permalink_structure'), (string) get_option('permalink_structure')];
        foreach (['about', 'methodology', 'corrections', 'affiliate-disclosure', 'privacy-policy'] as $slug) {
            $checks[] = ['필수 페이지 ' . $slug, (bool) get_page_by_path($slug), home_url('/' . $slug . '/')];
        }
        $sitemap = wp_remote_get(home_url('/wp-sitemap.xml'), ['timeout' => 10]);
        $checks[] = ['XML 사이트맵', !is_wp_error($sitemap) && wp_remote_retrieve_response_code($sitemap) === 200, home_url('/wp-sitemap.xml')];

        $thin = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} specs ON specs.post_id=p.ID AND specs.meta_key='_tech_specs'
             WHERE p.post_status='publish' AND p.post_type IN ('laptop','cpu','gpu')
               AND (specs.meta_value IS NULL OR CHAR_LENGTH(specs.meta_value) < 300)"
        );
        $missing_series = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_catalog_series_slug'
             WHERE p.post_status='publish' AND p.post_type IN ('phone','laptop','cpu','gpu') AND s.meta_id IS NULL"
        );
        $checks[] = ['얇은 하드웨어 페이지 없음', $thin === 0, $thin . '개'];
        $checks[] = ['시리즈 미분류 검토', $missing_series === 0, $missing_series . '개'];

        foreach ($checks as [$label, $passed, $detail]) {
            WP_CLI::log(($passed ? '[PASS] ' : '[CHECK] ') . $label . ' — ' . $detail);
        }
        WP_CLI::log('');
        WP_CLI::log('실제 도메인 연결 후 Search Console에서 소유권 인증과 ' . home_url('/wp-sitemap.xml') . ' 제출을 완료하세요.');
    }
}
