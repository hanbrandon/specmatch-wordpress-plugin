<?php

if (!defined('ABSPATH')) {
    exit;
}

const PC_OPENROUTER_MODEL = 'stealth/ox-alpha';
const PC_AI_PROMPT_VERSION = 'device-editorial-v1';

function pc_openrouter_api_key(): string
{
    if (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY) {
        return (string) OPENROUTER_API_KEY;
    }
    return (string) get_option('pc_openrouter_api_key', '');
}

function pc_device_editorial(int $device_id): ?array
{
    $content = pc_get_ai_content($device_id, 'editorial_v1');
    if (!$content) {
        return null;
    }
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : null;
}

function pc_device_ai_facts(object $device): array
{
    $specs = [];
    foreach (pc_get_specs((int) $device->id) as $row) {
        $section = pc_translate_spec_section((string) $row->section_name);
        $field = pc_translate_spec_field((string) $row->field_name);
        $value = pc_public_text((string) $row->field_value);
        if ($value === '') {
            continue;
        }
        $specs[] = [
            'section' => $section,
            'field' => $field ?: '추가 정보',
            'value' => mb_substr($value, 0, 600),
        ];
    }

    return [
        'name' => pc_product_name((int) $device->post_id),
        'original_name' => pc_product_original_name((int) $device->post_id),
        'brand' => pc_apply_name_mappings((string) $device->brand),
        'announced' => pc_public_text((string) $device->announced),
        'status' => pc_public_text((string) $device->status),
        'display' => pc_public_text((string) $device->display),
        'chipset' => pc_public_text((string) $device->chipset),
        'memory' => pc_public_text((string) $device->ram),
        'storage' => pc_public_text((string) $device->storage),
        'camera' => pc_public_text((string) $device->camera),
        'battery' => pc_public_text((string) $device->battery),
        'os' => pc_public_text((string) $device->os),
        'specifications' => array_slice($specs, 0, 120),
    ];
}

function pc_generate_device_editorial(int $post_id): array|WP_Error
{
    $device = pc_get_device($post_id);
    if (!$device) {
        return new WP_Error('pc_ai_device_missing', '제품 정보를 찾을 수 없습니다.');
    }
    $api_key = pc_openrouter_api_key();
    if ($api_key === '') {
        return new WP_Error('pc_ai_key_missing', 'OpenRouter API 키를 먼저 설정하세요.');
    }

    $facts = pc_device_ai_facts($device);
    $system = <<<'PROMPT'
당신은 한국의 기술 제품 데이터베이스 편집자입니다. 제공된 FACTS만 사용해 자연스럽고 유용한 한국어 콘텐츠를 작성하세요.
규칙:
- FACTS에 없는 성능, 가격, 품질, 사용시간, 순위 또는 체험을 추측하지 마세요.
- 숫자와 고유명사는 FACTS를 정확히 유지하세요.
- 스펙을 단순 나열하지 말고 구매 판단에 도움이 되도록 해석하세요.
- 과장, 광고 문구, 최상급 표현, 키워드 반복을 피하세요.
- 영문 단위와 용어는 필요한 경우에만 유지하고 문장은 자연스러운 한국어로 작성하세요.
- cautions는 확인 가능한 제한 사항만 작성하고, 근거가 없으면 빈 배열로 반환하세요.
- JSON 이외의 텍스트나 마크다운을 출력하지 마세요.
JSON 구조:
{
  "intro": "3~5문장 제품 개요",
  "verdict": "한 문장 핵심 판단",
  "highlights": [{"label":"짧은 제목","text":"근거가 포함된 설명"}],
  "strengths": ["근거가 있는 장점"],
  "cautions": ["확인할 제한 사항"],
  "recommended_for": ["추천 사용자 유형"],
  "faq": [{"question":"질문","answer":"FACTS 기반 답변"}]
}
PROMPT;

    $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
        'timeout' => 150,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer' => home_url('/'),
            'X-OpenRouter-Title' => get_bloginfo('name') ?: '스펙매치',
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'model' => PC_OPENROUTER_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => "FACTS:\n" . wp_json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
            'temperature' => 0.2,
            'max_tokens' => 2200,
            'response_format' => ['type' => 'json_object'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    if (is_wp_error($response)) {
        return $response;
    }
    $status = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($status < 200 || $status >= 300) {
        return new WP_Error('pc_ai_openrouter_error', sanitize_text_field((string) ($body['error']['message'] ?? 'OpenRouter 요청에 실패했습니다.')));
    }
    $raw = trim((string) ($body['choices'][0]['message']['content'] ?? ''));
    $raw = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw);
    $content = json_decode($raw, true);
    if (!is_array($content) || empty($content['intro']) || empty($content['verdict'])) {
        return new WP_Error('pc_ai_invalid_json', 'AI 응답이 필요한 JSON 형식이 아닙니다.');
    }

    $clean = [
        'intro' => sanitize_textarea_field((string) $content['intro']),
        'verdict' => sanitize_textarea_field((string) $content['verdict']),
        'highlights' => pc_ai_clean_objects($content['highlights'] ?? [], ['label', 'text'], 4),
        'strengths' => pc_ai_clean_strings($content['strengths'] ?? [], 5),
        'cautions' => pc_ai_clean_strings($content['cautions'] ?? [], 5),
        'recommended_for' => pc_ai_clean_strings($content['recommended_for'] ?? [], 4),
        'faq' => pc_ai_clean_objects($content['faq'] ?? [], ['question', 'answer'], 5),
        'generated_at' => current_time('mysql', true),
        'model' => (string) ($body['model'] ?? PC_OPENROUTER_MODEL),
    ];
    global $wpdb;
    $wpdb->replace(pc_table('ai_content'), [
        'device_id' => (int) $device->id,
        'content_type' => 'editorial_v1',
        'content' => wp_json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'model' => PC_OPENROUTER_MODEL,
        'prompt_version' => PC_AI_PROMPT_VERSION,
        'status' => 'published',
        'facts_hash' => hash('sha256', wp_json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        'created_at' => current_time('mysql', true),
        'reviewed_at' => current_time('mysql', true),
    ]);
    if ($wpdb->last_error) {
        return new WP_Error('pc_ai_save_failed', 'AI 콘텐츠 저장에 실패했습니다: ' . $wpdb->last_error);
    }
    clean_post_cache($post_id);
    return $clean;
}

function pc_ai_clean_strings(mixed $items, int $limit): array
{
    if (!is_array($items)) return [];
    return array_values(array_filter(array_map(
        static fn($item): string => sanitize_textarea_field((string) $item),
        array_slice($items, 0, $limit)
    )));
}

function pc_ai_clean_objects(mixed $items, array $keys, int $limit): array
{
    if (!is_array($items)) return [];
    $clean = [];
    foreach (array_slice($items, 0, $limit) as $item) {
        if (!is_array($item)) continue;
        $row = [];
        foreach ($keys as $key) $row[$key] = sanitize_textarea_field((string) ($item[$key] ?? ''));
        if (!in_array('', $row, true)) $clean[] = $row;
    }
    return $clean;
}

function pc_ai_admin_menu(): void
{
    add_management_page('Ox Alpha 콘텐츠', 'Ox Alpha 콘텐츠', 'manage_options', 'pc-ai-content', 'pc_ai_admin_page');
}

function pc_ai_admin_page(): void
{
    if (!current_user_can('manage_options')) return;
    $notice = '';
    $error = '';
    $preview = null;
    $selected_id = absint($_POST['pc_ai_post_id'] ?? $_GET['post_id'] ?? 0);

    if (isset($_POST['pc_ai_save_key'])) {
        check_admin_referer('pc_ai_settings');
        $key = trim((string) wp_unslash($_POST['pc_openrouter_api_key'] ?? ''));
        if ($key !== '') update_option('pc_openrouter_api_key', $key, false);
        $notice = 'OpenRouter 설정을 저장했습니다.';
    }
    if (isset($_POST['pc_ai_generate'])) {
        check_admin_referer('pc_ai_generate');
        $result = pc_generate_device_editorial($selected_id);
        if (is_wp_error($result)) $error = $result->get_error_message();
        else {
            $preview = $result;
            $notice = 'Ox Alpha 콘텐츠를 생성하고 저장했습니다.';
        }
    }

    $phones = get_posts([
        'post_type' => 'phone', 'post_status' => 'publish', 'posts_per_page' => 100,
        'orderby' => 'meta_value', 'meta_key' => '_catalog_release_date', 'order' => 'DESC',
    ]);
    if (!$preview && $selected_id) {
        $device = pc_get_device($selected_id);
        if ($device) $preview = pc_device_editorial((int) $device->id);
    }
    ?>
    <div class="wrap">
        <h1>Ox Alpha 콘텐츠</h1>
        <p>제품 스펙만 전달해 편집형 한국어 설명을 만들고 WordPress DB에 저장합니다. 페이지 요청 중에는 API를 호출하지 않습니다.</p>
        <?php if ($notice) : ?><div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
        <?php if ($error) : ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
        <hr>
        <h2>1. OpenRouter 설정</h2>
        <form method="post">
            <?php wp_nonce_field('pc_ai_settings'); ?>
            <table class="form-table"><tr><th><label for="pc-openrouter-key">API 키</label></th><td>
                <input id="pc-openrouter-key" name="pc_openrouter_api_key" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo pc_openrouter_api_key() ? '설정됨 — 변경할 때만 입력' : 'sk-or-v1-…'; ?>">
                <p class="description">가능하면 wp-config.php의 <code>OPENROUTER_API_KEY</code> 상수를 사용하세요. 모델: <code><?php echo esc_html(PC_OPENROUTER_MODEL); ?></code></p>
            </td></tr></table>
            <p><button class="button" name="pc_ai_save_key">설정 저장</button></p>
        </form>
        <hr>
        <h2>2. 제품 하나 생성</h2>
        <form method="post">
            <?php wp_nonce_field('pc_ai_generate'); ?>
            <select name="pc_ai_post_id" required>
                <option value="">제품 선택</option>
                <?php foreach ($phones as $phone) : ?>
                    <option value="<?php echo (int) $phone->ID; ?>" <?php selected($selected_id, $phone->ID); ?>><?php echo esc_html(pc_product_name((int) $phone->ID)); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button button-primary" name="pc_ai_generate">Ox Alpha로 생성 및 게시</button>
        </form>
        <?php if ($preview) : ?>
            <hr><h2>저장된 콘텐츠 미리보기</h2>
            <h3><?php echo esc_html($preview['verdict'] ?? ''); ?></h3>
            <p style="max-width:800px;font-size:15px;line-height:1.8"><?php echo esc_html($preview['intro'] ?? ''); ?></p>
            <?php if ($selected_id) : ?><p><a class="button" target="_blank" href="<?php echo esc_url(get_permalink($selected_id)); ?>">제품 페이지 보기</a></p><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

