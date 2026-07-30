<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_register_content_types(): void
{
    register_post_type('phone', [
        'labels' => [
            'name' => '휴대폰',
            'singular_name' => '휴대폰',
            'add_new_item' => '휴대폰 추가',
            'edit_item' => '휴대폰 편집',
            'search_items' => '휴대폰 검색',
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => 'phones',
        'rewrite' => ['slug' => 'phones', 'with_front' => false],
        'menu_icon' => 'dashicons-smartphone',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
    ]);

    register_taxonomy('phone_brand', 'phone', [
        'labels' => ['name' => '제조사', 'singular_name' => '제조사'],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'brands', 'with_front' => false],
    ]);

    register_taxonomy('phone_year', 'phone', [
        'labels' => ['name' => '출시연도', 'singular_name' => '출시연도'],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'released', 'with_front' => false],
    ]);

    $hardware_types = [
        'laptop' => ['노트북', 'laptops', 'dashicons-laptop'],
        'cpu' => ['CPU', 'cpus', 'dashicons-performance'],
        'gpu' => ['GPU', 'gpus', 'dashicons-chart-area'],
    ];
    foreach ($hardware_types as $type => [$label, $slug, $icon]) {
        register_post_type($type, [
            'labels' => [
                'name' => $label,
                'singular_name' => $label,
                'add_new_item' => $label . ' 추가',
                'edit_item' => $label . ' 편집',
                'search_items' => $label . ' 검색',
            ],
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => $slug,
            'rewrite' => ['slug' => $slug, 'with_front' => false],
            'menu_icon' => $icon,
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
        ]);
    }

    register_taxonomy('hardware_brand', ['laptop', 'cpu', 'gpu'], [
        'labels' => ['name' => '하드웨어 브랜드', 'singular_name' => '하드웨어 브랜드'],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'makers', 'with_front' => false],
    ]);

    foreach (['laptops' => 'laptop', 'cpus' => 'cpu', 'gpus' => 'gpu'] as $archive => $type) {
        add_rewrite_rule(
            '^' . $archive . '/brand/([^/]+)/page/([0-9]+)/?$',
            'index.php?post_type=' . $type . '&tech_brand=$matches[1]&paged=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^' . $archive . '/brand/([^/]+)/?$',
            'index.php?post_type=' . $type . '&tech_brand=$matches[1]',
            'top'
        );
    }
}

function pc_hardware_brand_url(string $post_type, string $brand, int $page = 1): string
{
    $archives = ['laptop' => 'laptops', 'cpu' => 'cpus', 'gpu' => 'gpus'];
    if (!isset($archives[$post_type])) {
        return home_url('/');
    }
    $path = '/' . $archives[$post_type] . '/brand/' . sanitize_title($brand) . '/';
    if ($page > 1) {
        $path .= 'page/' . $page . '/';
    }
    return home_url($path);
}

function pc_ensure_pages(): void
{
    $pages = [
        'compare' => ['제품 비교', '스마트폰, 노트북, CPU와 GPU 두 제품의 사양을 나란히 비교합니다.'],
        'about' => ['사이트 소개', '<h2>숫자를 선택의 언어로 바꿉니다</h2><p>스펙매치는 스마트폰, 노트북, CPU와 GPU의 사양과 벤치마크를 한국어로 정리하는 독립 기술 데이터베이스입니다.</p><p>제품을 판매하거나 제조하지 않으며, 수집된 데이터가 실제 구매 판단에 도움이 되도록 같은 제품군 안에서 비교 가능한 형태로 제공합니다.</p>'],
        'methodology' => ['데이터 및 평가 방법', '<h2>데이터 구성</h2><p>제품 사양, 출시 정보와 공개 벤치마크 결과를 구조화해 저장합니다. 단위가 다른 시험은 서로 직접 비교하지 않습니다.</p><h2>자체 평가 점수</h2><p>자체 점수는 수집된 항목만 반영하는 참고 지표입니다. 실제 사용자 리뷰 평점이나 절대적인 제품 품질 점수가 아닙니다.</p><h2>갱신 원칙</h2><p>수집 데이터가 변경되면 제품 페이지와 갱신일을 업데이트합니다. 데이터가 부족한 제품은 해석보다 원본 수치를 우선합니다.</p>'],
        'corrections' => ['오류 제보 및 정정', '<h2>데이터 오류를 발견했나요?</h2><p>제품명과 잘못된 항목, 확인 가능한 근거를 함께 알려주세요. 확인 후 데이터를 정정하고 제품 페이지의 갱신일을 업데이트합니다.</p><p>사이트 운영 연락처가 설정되기 전에는 WordPress 관리자 문의 채널을 이용해 주세요.</p>'],
        'affiliate-disclosure' => ['제휴 링크 고지', '<h2>제휴 링크 운영 원칙</h2><p>일부 구매 링크를 통해 수수료를 받을 수 있습니다. 사용자가 지불하는 가격에는 영향을 주지 않습니다.</p><p>제휴 여부는 사양, 평가 점수와 비교 결과에 영향을 주지 않으며 구매 링크에는 sponsored 및 nofollow 속성을 적용합니다.</p>'],
        'privacy-policy' => ['개인정보처리방침', '<h2>익명 이용 통계</h2><p>인기 제품 계산을 위해 제품 조회, 비교 선택과 제휴 링크 클릭 여부를 집계합니다. IP 주소와 원문 검색어는 저장하지 않습니다.</p><p>브라우저에서 생성한 임의 식별자는 서버에서 다시 해시하며, 같은 제품의 같은 행동은 하루 한 번만 집계합니다.</p><h2>브라우저 저장소</h2><p>최근 본 제품과 익명 세션 식별자를 저장하기 위해 로컬 스토리지를 사용합니다. 브라우저 설정에서 언제든 삭제할 수 있습니다.</p>'],
    ];
    foreach ($pages as $slug => [$title, $content]) {
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($existing) {
            $updates = ['ID' => $existing->ID];
            if (trim((string) $existing->post_content) === '') {
                $updates['post_content'] = $content;
            }
            if ($existing->post_status !== 'publish') {
                $updates['post_status'] = 'publish';
                $updates['post_title'] = $title;
                $updates['post_content'] = $content;
            }
            if (count($updates) > 1) {
                wp_update_post($updates);
            }
            continue;
        }
        wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $content,
        ]);
    }
}
