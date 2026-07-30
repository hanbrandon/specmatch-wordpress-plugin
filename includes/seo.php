<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_catalog_filter_active(): bool
{
    return (bool) (
        get_query_var('catalog_q')
        || get_query_var('catalog_year')
        || get_query_var('min_score')
        || get_query_var('catalog_sort')
    );
}

function pc_catalog_canonical_url(): string
{
    $paged = max(1, (int) get_query_var('paged'));
    if (is_tax('phone_brand')) {
        $base = get_term_link(get_queried_object());
    } else {
        $type = (string) get_query_var('post_type');
        $brand = sanitize_title((string) get_query_var('tech_brand'));
        $base = $brand && function_exists('pc_hardware_brand_url')
            ? pc_hardware_brand_url($type, $brand)
            : get_post_type_archive_link($type);
    }
    if (is_wp_error($base) || !$base) {
        return home_url('/');
    }
    return $paged > 1 ? trailingslashit((string) $base) . 'page/' . $paged . '/' : (string) $base;
}

function pc_tech_seo_data(int $post_id): array
{
    $type = (string) get_post_type($post_id);
    $labels = ['laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
    $brands = wp_get_post_terms($post_id, 'hardware_brand', ['fields' => 'names']);
    $brand = !is_wp_error($brands) ? ($brands[0] ?? '') : '';
    $name = get_the_title($post_id);
    $score = get_post_meta($post_id, '_tech_score', true);
    $release = (string) get_post_meta($post_id, '_catalog_release_date', true);
    $description = trim($brand . ' ' . $name . ' ' . ($labels[$type] ?? '하드웨어') . '의 전체 사양');
    if ($score !== '') {
        $description .= ', 평가 점수 ' . $score . '점';
    }
    if ($release) {
        $description .= ', 출시일 ' . $release;
    }
    $description .= '과 벤치마크 데이터를 한눈에 확인하고 관련 제품과 비교하세요.';
    return compact('type', 'brand', 'name', 'score', 'release', 'description');
}

function pc_contextual_seo_data(): array
{
    $site_name = get_bloginfo('name') ?: '스펙매치';
    $title = wp_get_document_title();
    $description = '';
    $canonical = '';
    $image = '';
    $type = 'website';

    if (
        (is_front_page() || is_home())
        && !(function_exists('pc_is_series') && pc_is_series())
        && !pc_is_compare()
    ) {
        $title = '스펙매치 | 스마트폰·노트북·CPU·GPU 스펙 비교';
        $description = '스마트폰, 노트북, CPU와 GPU의 출시 정보, 전체 사양과 벤치마크를 한국어로 확인하고 제품별 차이를 비교하세요.';
        $canonical = home_url('/');
    } elseif (function_exists('pc_is_series') && pc_is_series()) {
        $posts = pc_series_posts();
        if ($posts) {
            $label = (string) get_post_meta($posts[0]->ID, '_catalog_series_label', true);
            $series_type = sanitize_key((string) get_query_var('pc_series_type'));
            $series_slug = sanitize_title((string) get_query_var('pc_series_slug'));
            $title = $label . ' 스펙·세대별 비교 | ' . $site_name;
            $description = $label . ' 제품의 출시일, 주요 사양과 평가 점수를 최신순으로 확인하고 세대별 차이를 비교하세요.';
            $canonical = pc_series_url($series_type, $series_slug);
        }
    } elseif (is_singular('phone')) {
        $device = pc_get_device((int) get_queried_object_id());
        if ($device) {
            $title = $device->model . ' 스펙·평가·비교 | ' . $site_name;
            $description = wp_html_excerpt(pc_device_insights($device)['summary'], 155, '…');
            $canonical = get_permalink((int) $device->post_id);
            $image = (string) pc_public_image_url($device);
            $type = 'product';
        }
    } elseif (is_singular(['laptop', 'cpu', 'gpu'])) {
        $post_id = (int) get_queried_object_id();
        $data = pc_tech_seo_data($post_id);
        $title = $data['name'] . ' 스펙·벤치마크 | ' . $site_name;
        $description = wp_html_excerpt($data['description'], 155, '…');
        $canonical = get_permalink($post_id);
        $image = (string) get_post_meta($post_id, '_tech_image_url', true);
        $type = 'product';
    } elseif (is_post_type_archive(['phone', 'laptop', 'cpu', 'gpu']) || is_tax('phone_brand')) {
        $archive_type = is_tax('phone_brand') ? 'phone' : (string) get_query_var('post_type');
        $labels = ['phone' => '스마트폰', 'laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
        $brand = is_tax('phone_brand')
            ? single_term_title('', false)
            : ucwords(str_replace('-', ' ', sanitize_text_field((string) get_query_var('tech_brand'))));
        $label = trim(($brand ? $brand . ' ' : '') . ($labels[$archive_type] ?? '제품'));
        $title = $label . ' 최신 제품·스펙 | ' . $site_name;
        $description = $label . '의 최신 제품, 출시일, 주요 사양과 평가 데이터를 최신순으로 확인하고 비교하세요.';
        $canonical = pc_catalog_canonical_url();
    } elseif (pc_is_compare()) {
        if (pc_compare_type() !== 'phone') {
            [$a, $b] = pc_compare_tech_posts();
            if ($a && $b) {
                $title = $a->post_title . ' vs ' . $b->post_title . ' 비교 | ' . $site_name;
                $description = $a->post_title . '과 ' . $b->post_title . '의 성능, 벤치마크와 전체 사양을 항목별로 비교합니다.';
                $canonical = pc_compare_tech_url($a, $b);
            }
        } else {
            [$a, $b] = pc_compare_devices();
            if ($a && $b) {
                $title = $a->model . ' vs ' . $b->model . ' 비교 | ' . $site_name;
                $description = wp_html_excerpt(pc_compare_insights($a, $b)['verdict'], 155, '…');
                $canonical = pc_compare_url($a, $b);
            }
        }
    } elseif (is_page()) {
        $page_descriptions = [
            'compare' => '스마트폰, 노트북, CPU와 GPU에서 두 제품을 선택해 사양, 평가 점수와 벤치마크 차이를 한눈에 비교하세요.',
            'about' => '스펙매치가 스마트폰, 노트북, CPU와 GPU 데이터를 수집하고 한국어로 제공하는 목적과 운영 원칙을 안내합니다.',
            'methodology' => '스펙매치의 제품 사양, 출시 정보, 벤치마크 데이터 수집과 자체 평가 점수 산정 및 갱신 원칙을 확인하세요.',
            'corrections' => '스펙매치 제품 정보에서 오류를 발견했을 때 제보하는 방법과 데이터 확인 및 정정 절차를 안내합니다.',
            'affiliate-disclosure' => '스펙매치의 제휴 링크와 수수료 운영 원칙, 제품 평가 및 비교 결과의 독립성에 관한 안내입니다.',
            'privacy-policy' => '스펙매치의 익명 이용 통계, 브라우저 저장소와 개인정보 처리 원칙을 확인하세요.',
        ];
        $slug = (string) get_post_field('post_name', get_queried_object_id());
        $description = $page_descriptions[$slug] ?? wp_html_excerpt(
            trim(wp_strip_all_tags((string) get_post_field('post_content', get_queried_object_id()))),
            155,
            '…'
        );
        $canonical = get_permalink();
    }

    return compact('title', 'description', 'canonical', 'image', 'type');
}

function pc_output_primary_meta(array $meta): void
{
    if (!$meta['description'] || !$meta['canonical']) {
        return;
    }
    echo '<meta name="description" content="' . esc_attr($meta['description']) . '">' . "\n";
    if (!is_singular()) {
        echo '<link rel="canonical" href="' . esc_url($meta['canonical']) . '">' . "\n";
    }
    echo '<meta property="og:locale" content="ko_KR">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($meta['type']) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($meta['title']) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($meta['description']) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($meta['canonical']) . '">' . "\n";
    echo '<meta name="twitter:card" content="' . ($meta['image'] ? 'summary_large_image' : 'summary') . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($meta['title']) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($meta['description']) . '">' . "\n";
    if ($meta['image']) {
        echo '<meta property="og:image" content="' . esc_url($meta['image']) . '">' . "\n";
        echo '<meta property="og:image:alt" content="' . esc_attr($meta['title']) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($meta['image']) . '">' . "\n";
    }
}

function pc_output_item_list_schema(): void
{
    global $wp_query;
    if (!$wp_query instanceof WP_Query || !$wp_query->posts) {
        return;
    }
    $items = [];
    $offset = (max(1, (int) get_query_var('paged')) - 1) * (int) $wp_query->get('posts_per_page');
    foreach ($wp_query->posts as $index => $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $items[] = [
            '@type' => 'ListItem',
            'position' => $offset + $index + 1,
            'url' => get_permalink($post),
            'name' => $post->post_title,
        ];
    }
    if ($items) {
        echo '<script type="application/ld+json">' . wp_json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}

function pc_output_seo(): void
{
    pc_output_breadcrumb_schema();
    pc_output_primary_meta(pc_contextual_seo_data());

    if (function_exists('pc_is_series') && pc_is_series()) {
        $posts = pc_series_posts();
        if (!$posts) {
            echo '<meta name="robots" content="noindex,follow">' . "\n";
            return;
        }
        $label = (string) get_post_meta($posts[0]->ID, '_catalog_series_label', true);
        $type = sanitize_key((string) get_query_var('pc_series_type'));
        $slug = sanitize_title((string) get_query_var('pc_series_slug'));
        $items = [];
        foreach ($posts as $index => $post) {
            $items[] = ['@type' => 'ListItem', 'position' => $index + 1, 'url' => get_permalink($post), 'name' => $post->post_title];
        }
        echo '<script type="application/ld+json">' . wp_json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $label,
            'itemListElement' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        return;
    }

    if (is_singular('phone')) {
        $device = pc_get_device((int) get_queried_object_id());
        if (!$device) {
            return;
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $device->model,
            'brand' => ['@type' => 'Brand', 'name' => $device->brand],
            'model' => $device->model,
            'category' => '스마트폰',
            'url' => get_permalink(),
        ];
        $insights = pc_device_insights($device);
        $description = $insights['summary'];
        $schema['description'] = $description;
        echo '<meta property="article:modified_time" content="' . esc_attr(get_post_modified_time(DATE_W3C, true, (int) $device->post_id)) . '">' . "\n";
        echo '<script type="application/ld+json">' .
            wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
            '</script>' . "\n";
        return;
    }

    if (is_singular(['laptop', 'cpu', 'gpu'])) {
        $post_id = (int) get_queried_object_id();
        $data = pc_tech_seo_data($post_id);
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $data['name'],
            'url' => get_permalink($post_id),
            'description' => $data['description'],
            'category' => $data['type'],
        ];
        if ($data['brand']) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $data['brand']];
        }
        echo '<meta property="article:modified_time" content="' . esc_attr(get_post_modified_time(DATE_W3C, true, $post_id)) . '">' . "\n";
        echo '<script type="application/ld+json">' . wp_json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . '</script>' . "\n";
        return;
    }

    if (is_post_type_archive(['phone', 'laptop', 'cpu', 'gpu']) || is_tax('phone_brand')) {
        $type = is_tax('phone_brand') ? 'phone' : (string) get_query_var('post_type');
        $labels = ['phone' => '스마트폰', 'laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
        $brand = is_tax('phone_brand')
            ? single_term_title('', false)
            : ucwords(str_replace('-', ' ', sanitize_text_field((string) get_query_var('tech_brand'))));
        $title = trim(($brand ? $brand . ' ' : '') . ($labels[$type] ?? '제품'));
        pc_output_item_list_schema();
        return;
    }

    if (pc_is_compare()) {
        if (pc_compare_type() !== 'phone') {
            [$a, $b] = pc_compare_tech_posts();
            if (!$a || !$b) {
                return;
            }
            if (!pc_compare_tech_is_indexable($a, $b) || !pc_compare_tech_current_url_is_canonical($a, $b)) {
                echo '<meta name="robots" content="noindex,follow">' . "\n";
            }
            return;
        }
        [$a, $b] = pc_compare_devices();
        if (!$a || !$b) {
            return;
        }
        if (!pc_compare_is_indexable($a, $b) || !pc_compare_current_url_is_canonical($a, $b)) {
            echo '<meta name="robots" content="noindex,follow">' . "\n";
        }
    }
}

function pc_breadcrumb_items(): array
{
    $items = [['name' => '홈', 'url' => home_url('/')]];

    if (function_exists('pc_is_series') && pc_is_series()) {
        $type = sanitize_key((string) get_query_var('pc_series_type'));
        $posts = pc_series_posts();
        $labels = ['phone' => '스마트폰', 'laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
        $items[] = ['name' => $labels[$type] ?? '제품', 'url' => get_post_type_archive_link($type)];
        $items[] = ['name' => $posts ? (string) get_post_meta($posts[0]->ID, '_catalog_series_label', true) : '시리즈', 'url' => ''];
    } elseif (is_post_type_archive('phone')) {
        $items[] = ['name' => '전체 휴대폰', 'url' => ''];
    } elseif (is_post_type_archive(['laptop', 'cpu', 'gpu'])) {
        $type = (string) get_query_var('post_type');
        $labels = ['laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
        $brand = ucwords(str_replace('-', ' ', sanitize_text_field((string) get_query_var('tech_brand'))));
        $items[] = ['name' => $labels[$type] ?? '하드웨어', 'url' => $brand ? get_post_type_archive_link($type) : ''];
        if ($brand) {
            $items[] = ['name' => $brand, 'url' => ''];
        }
    } elseif (is_tax('phone_brand')) {
        $items[] = ['name' => '전체 휴대폰', 'url' => get_post_type_archive_link('phone')];
        $items[] = ['name' => single_term_title('', false), 'url' => ''];
    } elseif (is_singular('phone')) {
        $device = pc_get_device((int) get_queried_object_id());
        $items[] = ['name' => '전체 휴대폰', 'url' => get_post_type_archive_link('phone')];
        if ($device) {
            $term = get_term_by('name', $device->brand, 'phone_brand');
            if ($term && !is_wp_error($term)) {
                $items[] = ['name' => $device->brand, 'url' => get_term_link($term)];
            }
            $items[] = ['name' => $device->model, 'url' => ''];
        }
    } elseif (is_singular(['laptop', 'cpu', 'gpu'])) {
        $type = get_post_type();
        $labels = ['laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
        $items[] = ['name' => $labels[$type] ?? '하드웨어', 'url' => get_post_type_archive_link($type)];
        $items[] = ['name' => get_the_title(), 'url' => ''];
    } elseif (pc_is_compare()) {
        if (pc_compare_type() !== 'phone') {
            [$a, $b] = pc_compare_tech_posts();
            $items[] = ['name' => '제품 비교', 'url' => home_url('/compare/')];
            if ($a && $b) {
                $items[] = ['name' => $a->post_title . ' vs ' . $b->post_title, 'url' => ''];
            }
            return $items;
        }
        [$a, $b] = pc_compare_devices();
        $items[] = ['name' => '기기 비교', 'url' => home_url('/compare/')];
        if ($a && $b) {
            $items[] = ['name' => $a->model . ' vs ' . $b->model, 'url' => ''];
        }
    } elseif (is_page('compare')) {
        $items[] = ['name' => '기기 비교', 'url' => ''];
    } else {
        return [];
    }

    return $items;
}

function pc_output_breadcrumb_schema(): void
{
    $items = pc_breadcrumb_items();
    if (count($items) < 2) {
        return;
    }
    $schema_items = [];
    foreach ($items as $position => $item) {
        $entry = [
            '@type' => 'ListItem',
            'position' => $position + 1,
            'name' => $item['name'],
        ];
        if ($item['url']) {
            $entry['item'] = $item['url'];
        }
        $schema_items[] = $entry;
    }
    echo '<script type="application/ld+json">' .
        wp_json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $schema_items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
        '</script>' . "\n";
}

function pc_prevent_public_image_indexing(array $robots): array
{
    if (!is_admin()) {
        $robots['noimageindex'] = true;
    }
    if (pc_catalog_filter_active()) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
    }
    $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    if (
        $host === 'localhost'
        || $host === '127.0.0.1'
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test')
    ) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
    }
    return $robots;
}

function pc_redirect_legacy_hardware_brand_url(): void
{
    if (
        !is_post_type_archive(['laptop', 'cpu', 'gpu'])
        || !isset($_GET['tech_brand'])
        || pc_catalog_filter_active()
    ) {
        return;
    }
    $type = (string) get_query_var('post_type');
    $brand = sanitize_title((string) get_query_var('tech_brand'));
    if ($brand && function_exists('pc_hardware_brand_url')) {
        wp_safe_redirect(pc_hardware_brand_url($type, $brand), 301);
        exit;
    }
}

function pc_catalog_empty_filter_status(): void
{
    global $wp_query;
    if (
        pc_catalog_filter_active()
        && (is_post_type_archive(['phone', 'laptop', 'cpu', 'gpu']) || is_tax('phone_brand'))
        && $wp_query instanceof WP_Query
        && !$wp_query->have_posts()
    ) {
        status_header(404);
    }
}

function pc_document_title_parts(array $parts): array
{
    if (
        (is_front_page() || is_home())
        && !(function_exists('pc_is_series') && pc_is_series())
        && !pc_is_compare()
    ) {
        return ['title' => '스펙매치 | 스마트폰·노트북·CPU·GPU 스펙 비교'];
    }
    if (function_exists('pc_is_series') && pc_is_series()) {
        $posts = pc_series_posts();
        $parts['title'] = $posts ? (string) get_post_meta($posts[0]->ID, '_catalog_series_label', true) : '제품 시리즈';
        $parts['site'] = get_bloginfo('name');
        unset($parts['tagline']);
    } elseif (is_singular('phone')) {
        $device = pc_get_device((int) get_queried_object_id());
        if ($device) {
            $parts['title'] = $device->model . ' 스펙·평가·비교';
        }
    } elseif (is_singular(['laptop', 'cpu', 'gpu'])) {
        $data = pc_tech_seo_data((int) get_queried_object_id());
        $parts['title'] = $data['name'] . ' 스펙·벤치마크';
    } elseif (is_post_type_archive(['phone', 'laptop', 'cpu', 'gpu']) || is_tax('phone_brand')) {
        $type = is_tax('phone_brand') ? 'phone' : (string) get_query_var('post_type');
        $labels = ['laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
        $labels['phone'] = '스마트폰';
        $brand = is_tax('phone_brand')
            ? single_term_title('', false)
            : ucwords(str_replace('-', ' ', sanitize_text_field((string) get_query_var('tech_brand'))));
        $parts['title'] = trim(($brand ? $brand . ' ' : '') . ($labels[$type] ?? '제품')) . ' 최신 제품·스펙';
    } elseif (pc_is_compare()) {
        if (pc_compare_type() !== 'phone') {
            [$a, $b] = pc_compare_tech_posts();
            if ($a && $b) {
                $parts['title'] = $a->post_title . ' vs ' . $b->post_title . ' 비교';
            }
        } else {
            [$a, $b] = pc_compare_devices();
            if ($a && $b) {
                $parts['title'] = $a->model . ' vs ' . $b->model . ' 비교';
            }
        }
    }
    return $parts;
}
