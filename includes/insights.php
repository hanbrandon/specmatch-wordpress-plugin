<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_device_spec_map(int $device_id): array
{
    $map = [];
    foreach (pc_get_specs($device_id) as $row) {
        $key = strtolower(trim($row->section_name . '|' . ($row->field_name ?: '_note')));
        $map[$key][] = (string) $row->field_value;
    }
    return $map;
}

function pc_fact(array $map, array $keys): ?string
{
    foreach ($keys as $key) {
        $normalized = strtolower($key);
        if (!empty($map[$normalized])) {
            return implode(' · ', array_filter($map[$normalized]));
        }
    }
    return null;
}

function pc_number(?string $value, string $unit = ''): ?float
{
    if (!$value) {
        return null;
    }
    $unit_pattern = $unit ? '\s*' . preg_quote($unit, '/') : '';
    if (preg_match('/(\d+(?:\.\d+)?)' . $unit_pattern . '/i', str_replace(',', '', $value), $match)) {
        return (float) $match[1];
    }
    return null;
}

function pc_thickness(?string $dimensions): ?float
{
    if (!$dimensions || !preg_match_all('/(\d+(?:\.\d+)?)\s*mm/i', $dimensions, $matches)) {
        return null;
    }
    return (float) end($matches[1]);
}

function pc_is_yes(?string $value): bool
{
    return (bool) ($value && !preg_match('/^(no|없음|미지원)\b/i', trim($value)));
}

function pc_get_ai_content(int $device_id, string $type): ?string
{
    global $wpdb;
    $table = pc_table('ai_content');
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT content FROM {$table}
             WHERE device_id = %d AND content_type = %s AND status = 'published'
             LIMIT 1",
            $device_id,
            $type
        )
    ) ?: null;
}

function pc_device_insights(object $device): array
{
    $map = pc_device_spec_map((int) $device->id);
    $technology = pc_fact($map, ['network|technology']);
    $announced = pc_public_text(pc_fact($map, ['launch|announced']) ?: $device->announced);
    $status = pc_fact($map, ['launch|status']) ?: $device->status;
    $weight_text = pc_fact($map, ['body|weight']);
    $dimensions = pc_fact($map, ['body|dimensions']);
    $body_notes = pc_fact($map, ['body|_note']);
    $display_type = pc_fact($map, ['display|type']);
    $display_size = pc_fact($map, ['display|size']) ?: $device->display;
    $os = pc_fact($map, ['platform|os']) ?: $device->os;
    $chipset = pc_fact($map, ['platform|chipset']) ?: $device->chipset;
    $card_slot = pc_fact($map, ['memory|card slot']);
    $internal = pc_fact($map, ['memory|internal']) ?: $device->storage;
    $camera = pc_fact($map, [
        'main camera|single', 'main camera|dual', 'main camera|triple',
        'main camera|quad', 'camera|primary', 'camera|single',
    ]) ?: $device->camera;
    $jack = pc_fact($map, ['sound|3.5mm jack']);
    $loudspeaker = pc_fact($map, ['sound|loudspeaker']);
    $nfc = pc_fact($map, ['comms|nfc']);
    $radio = pc_fact($map, ['comms|radio']);
    $battery = pc_fact($map, ['battery|type']) ?: $device->battery;
    $charging = pc_fact($map, ['battery|charging']);

    $facts = array_filter([
        $announced ? "발표 {$announced}" : null,
        $display_size ? "화면 {$display_size}" : null,
        $chipset ? "칩셋 {$chipset}" : null,
        $internal ? "저장공간 {$internal}" : null,
        $battery ? "배터리 {$battery}" : null,
    ]);
    $generated_summary = $facts
        ? pc_product_name((int) $device->post_id) . '의 주요 사양은 ' . implode(', ', array_slice($facts, 0, 4)) . '입니다.'
        : pc_product_name((int) $device->post_id) . '의 출시 정보와 전체 하드웨어 사양을 정리한 페이지입니다.';
    if ($status) {
        $generated_summary .= ' 출시 상태는 ' . $status . '로 표시되어 있습니다.';
    }
    $summary = pc_get_ai_content((int) $device->id, 'summary') ?: $generated_summary;

    $pros = [];
    $cons = [];
    $recommended = [];
    $weight = pc_number($weight_text, 'g');
    $screen = pc_number($display_size, 'inches');
    $capacity = pc_number($battery, 'mAh');

    if ($technology && stripos($technology, '5G') !== false) {
        $pros[] = '5G 이동통신을 지원합니다.';
    }
    if ($display_type && preg_match('/AMOLED|OLED/i', $display_type)) {
        $pros[] = 'OLED 계열 디스플레이를 사용합니다.';
    }
    if ($display_type && preg_match('/(\d{2,3})\s*Hz/i', $display_type, $refresh) && (int) $refresh[1] >= 120) {
        $pros[] = $refresh[1] . 'Hz 고주사율 화면을 지원합니다.';
    }
    if (($body_notes && preg_match('/IP6[78]/i', $body_notes)) || ($dimensions && preg_match('/IP6[78]/i', $dimensions))) {
        $pros[] = 'IP 등급의 방수·방진을 지원합니다.';
    }
    if ($capacity !== null && $capacity >= 5000) {
        $pros[] = number_format_i18n($capacity) . 'mAh 대용량 배터리를 탑재했습니다.';
    }
    if ($weight !== null && $weight <= 175) {
        $pros[] = number_format_i18n($weight) . 'g으로 비교적 가벼운 편입니다.';
    } elseif ($weight !== null && $weight >= 210) {
        $cons[] = number_format_i18n($weight) . 'g으로 무거운 편입니다.';
    }
    if ($card_slot && pc_is_yes($card_slot)) {
        $pros[] = '외장 메모리 카드를 사용할 수 있습니다.';
    } elseif ($card_slot && !pc_is_yes($card_slot)) {
        $cons[] = '외장 메모리 카드를 지원하지 않습니다.';
    }
    if ($jack && !pc_is_yes($jack)) {
        $cons[] = '3.5mm 유선 이어폰 단자가 없습니다.';
    }
    if ($nfc && pc_is_yes($nfc)) {
        $pros[] = 'NFC 기능을 지원합니다.';
    }
    if ($charging) {
        $pros[] = '충전 사양: ' . $charging;
    }

    if ($screen !== null && $screen >= 6.7) {
        $recommended[] = '큰 화면으로 영상과 문서를 자주 보는 사용자';
    }
    if ($weight !== null && $weight <= 180) {
        $recommended[] = '휴대성과 가벼운 무게를 중요하게 보는 사용자';
    }
    if ($capacity !== null && $capacity >= 5000) {
        $recommended[] = '배터리 용량을 중요하게 보는 사용자';
    }
    if ($technology && stripos($technology, '5G') !== false) {
        $recommended[] = '5G 통신이 필요한 사용자';
    }
    if ($status && stripos($status, 'Discontinued') !== false) {
        $recommended = ['구형 휴대폰의 사양을 확인하거나 중고·수집용 모델을 찾는 사용자'];
        $cons[] = '단종 모델이므로 새 제품 구매와 서비스 지원이 제한될 수 있습니다.';
    }

    $faqs = [];
    if ($announced) {
        $faqs[] = [
            'question' => pc_product_name((int) $device->post_id) . '은 언제 발표됐나요?',
            'answer' => $announced . '에 발표된 것으로 기록되어 있습니다.',
        ];
    }
    if ($card_slot) {
        $faqs[] = [
            'question' => '메모리 카드를 사용할 수 있나요?',
            'answer' => pc_is_yes($card_slot)
                ? '네. 메모리 카드 사양은 ' . $card_slot . '입니다.'
                : '아니요. 별도의 외장 메모리 카드 슬롯을 지원하지 않습니다.',
        ];
    }
    if ($battery) {
        $faqs[] = [
            'question' => '배터리 사양은 어떻게 되나요?',
            'answer' => $battery . ($charging ? ' 충전 사양은 ' . $charging . '입니다.' : '입니다.'),
        ];
    }
    if ($jack) {
        $faqs[] = [
            'question' => '3.5mm 이어폰 단자가 있나요?',
            'answer' => pc_is_yes($jack) ? '네. 3.5mm 이어폰 단자를 지원합니다.' : '아니요. 3.5mm 이어폰 단자가 없습니다.',
        ];
    }

    return [
        'summary' => $summary,
        'pros' => array_slice(array_values(array_unique($pros)), 0, 6),
        'cons' => array_slice(array_values(array_unique($cons)), 0, 6),
        'recommended' => array_slice(array_values(array_unique($recommended)), 0, 4),
        'faqs' => array_slice($faqs, 0, 4),
        'facts' => compact(
            'technology', 'announced', 'status', 'weight_text', 'dimensions',
            'display_type', 'display_size', 'os', 'chipset', 'card_slot',
            'internal', 'camera', 'jack', 'loudspeaker', 'nfc', 'radio',
            'battery', 'charging'
        ),
    ];
}

function pc_score_between(float $value, float $minimum, float $maximum, bool $lower_is_better = false): int
{
    if ($maximum <= $minimum) {
        return 0;
    }
    $score = (($value - $minimum) / ($maximum - $minimum)) * 100;
    $score = max(0, min(100, $score));
    return (int) round($lower_is_better ? 100 - $score : $score);
}

function pc_max_match(?string $value, string $pattern): ?float
{
    if (!$value || !preg_match_all($pattern, str_replace(',', '', $value), $matches) || !$matches[1]) {
        return null;
    }
    return max(array_map('floatval', $matches[1]));
}

function pc_device_scorecard(object $device): array
{
    $map = pc_device_spec_map((int) $device->id);
    $scores = [
        'performance' => [],
        'display' => [],
        'camera' => [],
        'battery' => [],
        'usability' => [],
    ];

    $internal = pc_fact($map, ['memory|internal']) ?: $device->storage;
    $cpu = pc_fact($map, ['platform|cpu']);
    $chipset = pc_fact($map, ['platform|chipset']) ?: $device->chipset;
    $gpu = pc_fact($map, ['platform|gpu']);
    $ram = pc_max_match($internal, '/(\d+(?:\.\d+)?)\s*GB\s*RAM/i');
    $clock = pc_max_match($cpu, '/(\d+(?:\.\d+)?)\s*GHz/i');
    $process = pc_max_match($chipset, '/(\d+(?:\.\d+)?)\s*nm/i');
    if ($ram !== null) {
        $scores['performance'][] = pc_score_between($ram, 1, 16);
    }
    if ($clock !== null) {
        $scores['performance'][] = pc_score_between($clock, 0.8, 3.5);
    }
    if ($process !== null) {
        $scores['performance'][] = pc_score_between($process, 3, 28, true);
    }
    if ($internal && preg_match('/UFS\s*([234])(?:\.(\d))?/i', $internal, $ufs)) {
        $version = (float) ($ufs[1] . '.' . ($ufs[2] ?? '0'));
        $scores['performance'][] = pc_score_between($version, 2, 4);
    } elseif ($internal && stripos($internal, 'eMMC') !== false) {
        $scores['performance'][] = 25;
    }
    if ($gpu) {
        $scores['performance'][] = 55;
    }

    $display_type = pc_fact($map, ['display|type']);
    $resolution = pc_fact($map, ['display|resolution']);
    $refresh = pc_max_match($display_type, '/(\d{2,3})\s*Hz/i');
    $ppi = pc_max_match($resolution, '/(\d{3,4})\s*ppi/i');
    if ($display_type) {
        $scores['display'][] = preg_match('/AMOLED|OLED/i', $display_type) ? 92 : 58;
        $scores['display'][] = pc_score_between($refresh ?: 60, 60, 144);
        if (preg_match('/HDR10|Dolby Vision/i', $display_type)) {
            $scores['display'][] = 95;
        }
    }
    if ($ppi !== null) {
        $scores['display'][] = pc_score_between($ppi, 220, 550);
    }

    $camera = pc_fact($map, [
        'main camera|single', 'main camera|dual', 'main camera|triple',
        'main camera|quad', 'camera|primary', 'camera|single',
    ]) ?: $device->camera;
    $camera_features = pc_fact($map, ['main camera|features', 'camera|features']);
    $camera_video = pc_fact($map, ['main camera|video', 'camera|video']);
    $megapixels = pc_max_match($camera, '/(\d+(?:\.\d+)?)\s*MP/i');
    if ($megapixels !== null) {
        $scores['camera'][] = pc_score_between($megapixels, 5, 108);
    }
    if (($camera && preg_match('/\bOIS\b/i', $camera)) || ($camera_features && preg_match('/\bOIS\b/i', $camera_features))) {
        $scores['camera'][] = 95;
    } elseif ($camera) {
        $scores['camera'][] = 45;
    }
    if ($camera_video) {
        $scores['camera'][] = stripos($camera_video, '8K') !== false ? 100 : (stripos($camera_video, '4K') !== false ? 82 : 48);
    }

    $battery = pc_fact($map, ['battery|type']) ?: $device->battery;
    $charging = pc_fact($map, ['battery|charging']);
    $capacity = pc_max_match($battery, '/(\d{3,5})\s*mAh/i');
    $charging_watts = pc_max_match($charging, '/(\d+(?:\.\d+)?)\s*W\b/i');
    if ($capacity !== null) {
        $scores['battery'][] = pc_score_between($capacity, 2000, 6000);
    }
    if ($charging_watts !== null) {
        $scores['battery'][] = pc_score_between($charging_watts, 5, 100);
    }
    if ($charging && preg_match('/wireless|Qi/i', $charging)) {
        $scores['battery'][] = 90;
    }

    $technology = pc_fact($map, ['network|technology']);
    $body = pc_fact($map, ['body|build', 'body|_note']);
    $dimensions = pc_fact($map, ['body|dimensions']);
    $weight = pc_number(pc_fact($map, ['body|weight']), 'g');
    $thickness = pc_thickness($dimensions);
    $nfc = pc_fact($map, ['comms|nfc']);
    if ($weight !== null) {
        $scores['usability'][] = pc_score_between($weight, 150, 260, true);
    }
    if ($thickness !== null) {
        $scores['usability'][] = pc_score_between($thickness, 6, 12, true);
    }
    if ($technology) {
        $scores['usability'][] = stripos($technology, '5G') !== false ? 100 : 45;
    }
    if ($nfc) {
        $scores['usability'][] = pc_is_yes($nfc) ? 90 : 30;
    }
    if (($body && preg_match('/IP6[78]/i', $body)) || ($dimensions && preg_match('/IP6[78]/i', $dimensions))) {
        $scores['usability'][] = 95;
    }

    $labels = [
        'performance' => '성능',
        'display' => '디스플레이',
        'camera' => '카메라',
        'battery' => '배터리',
        'usability' => '사용 편의',
    ];
    $weights = [
        'performance' => 0.28,
        'display' => 0.23,
        'camera' => 0.20,
        'battery' => 0.17,
        'usability' => 0.12,
    ];
    $categories = [];
    $weighted_total = 0;
    $available_weight = 0;
    $evidence_count = 0;
    foreach ($scores as $key => $values) {
        if (!$values) {
            $categories[$key] = ['label' => $labels[$key], 'score' => null];
            continue;
        }
        $score = (int) round(array_sum($values) / count($values));
        $categories[$key] = ['label' => $labels[$key], 'score' => $score];
        $weighted_total += $score * $weights[$key];
        $available_weight += $weights[$key];
        $evidence_count += count($values);
    }

    $benchmark = pc_fact($map, ['our tests|performance']);
    return [
        'overall' => $available_weight > 0 ? (int) round($weighted_total / $available_weight) : null,
        'categories' => $categories,
        'benchmark' => $benchmark,
        'evidence_count' => $evidence_count,
    ];
}

function pc_compare_insights(object $a, object $b): array
{
    $a_data = pc_device_insights($a);
    $b_data = pc_device_insights($b);
    $af = $a_data['facts'];
    $bf = $b_data['facts'];
    $differences = [];
    $a_reasons = [];
    $b_reasons = [];

    $metrics = [
        ['화면 크기', pc_number($af['display_size'], 'inches'), pc_number($bf['display_size'], 'inches'), '인치', 'larger'],
        ['무게', pc_number($af['weight_text'], 'g'), pc_number($bf['weight_text'], 'g'), 'g', 'smaller'],
        ['배터리 용량', pc_number($af['battery'], 'mAh'), pc_number($bf['battery'], 'mAh'), 'mAh', 'larger'],
        ['두께', pc_thickness($af['dimensions']), pc_thickness($bf['dimensions']), 'mm', 'smaller'],
    ];

    foreach ($metrics as [$label, $av, $bv, $unit, $preference]) {
        if ($av === null || $bv === null || abs($av - $bv) < 0.01) {
            continue;
        }
        $a_wins = $preference === 'larger' ? $av > $bv : $av < $bv;
        $winner_device = $a_wins ? $a : $b;
        $winner = pc_product_name((int) $winner_device->post_id);
        $difference = abs($av - $bv);
        $note = $label === '무게' || $label === '두께'
            ? $winner . '이(가) ' . number_format_i18n($difference, 1) . $unit . ' 더 ' . ($label === '무게' ? '가볍습니다.' : '얇습니다.')
            : $winner . '이(가) ' . number_format_i18n($difference, 1) . $unit . ' 더 큽니다.';
        $differences[] = [
            'label' => $label,
            'a' => number_format_i18n($av, 1) . $unit,
            'b' => number_format_i18n($bv, 1) . $unit,
            'note' => $note,
        ];
        if ($a_wins) {
            $a_reasons[] = $note;
        } else {
            $b_reasons[] = $note;
        }
    }

    if (
        $af['technology'] && $bf['technology']
        && stripos($af['technology'], '5G') !== stripos($bf['technology'], '5G')
    ) {
        $a_has = stripos($af['technology'], '5G') !== false;
        if ($a_has) {
            $a_reasons[] = '5G 이동통신을 지원합니다.';
        } else {
            $b_reasons[] = '5G 이동통신을 지원합니다.';
        }
    }
    if ($af['card_slot'] && $bf['card_slot'] && pc_is_yes($af['card_slot']) !== pc_is_yes($bf['card_slot'])) {
        if (pc_is_yes($af['card_slot'])) {
            $a_reasons[] = '외장 메모리 카드를 지원합니다.';
        } else {
            $b_reasons[] = '외장 메모리 카드를 지원합니다.';
        }
    }
    if ($af['jack'] && $bf['jack'] && pc_is_yes($af['jack']) !== pc_is_yes($bf['jack'])) {
        if (pc_is_yes($af['jack'])) {
            $a_reasons[] = '3.5mm 이어폰 단자를 지원합니다.';
        } else {
            $b_reasons[] = '3.5mm 이어폰 단자를 지원합니다.';
        }
    }

    $a_reasons = array_slice(array_values(array_unique($a_reasons)), 0, 4);
    $b_reasons = array_slice(array_values(array_unique($b_reasons)), 0, 4);
    if ($a_reasons || $b_reasons) {
        $name_a = pc_product_name((int) $a->post_id);
        $name_b = pc_product_name((int) $b->post_id);
        $verdict = $name_a . '과 ' . $name_b . '은(는) 사양별 강점이 다릅니다. '
            . ($a_reasons ? $name_a . '은(는) ' . $a_reasons[0] . ' ' : '')
            . ($b_reasons ? $name_b . '은(는) ' . $b_reasons[0] : '');
    } else {
        $verdict = '두 기기의 핵심 수치 차이가 제한적입니다. 아래 전체 스펙에서 지원 기능과 세부 구성을 확인하세요.';
    }

    $score_a = pc_device_scorecard($a);
    $score_b = pc_device_scorecard($b);

    return compact('verdict', 'differences', 'a_reasons', 'b_reasons', 'score_a', 'score_b');
}

function pc_related_devices(object $device, int $limit = 4): array
{
    global $wpdb;
    $table = pc_table('devices');
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT d.*
             FROM {$table} d
             INNER JOIN {$wpdb->posts} p ON p.ID = d.post_id
             LEFT JOIN {$wpdb->postmeta} rd ON rd.post_id=p.ID AND rd.meta_key='_catalog_release_date'
             WHERE d.brand = %s AND d.id <> %d AND p.post_status = 'publish'
             ORDER BY rd.meta_value DESC, d.source_id DESC
             LIMIT %d",
            $device->brand,
            $device->id,
            $limit
        )
    );
}
