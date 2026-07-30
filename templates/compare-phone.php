<?php
get_header();
[$phone_a, $phone_b] = pc_compare_devices();
?>
<main class="site-main shell" id="main-content">
    <?php if (function_exists('ps_breadcrumbs')) ps_breadcrumbs(true); ?>
    <?php if (!$phone_a || !$phone_b) : ?>
        <section class="empty-state">
            <p class="eyebrow">비교할 수 없음</p>
            <h1>비교할 기기를 찾지 못했습니다.</h1>
            <a class="button" href="<?php echo esc_url(home_url('/phones/')); ?>">기기 목록으로</a>
        </section>
    <?php else : ?>
        <?php
        $rows = pc_compare_rows($phone_a, $phone_b);
        $insights = pc_compare_insights($phone_a, $phone_b);
        ?>
        <header class="compare-hero">
            <p class="eyebrow">나란히 보기 / 자동 비교</p>
            <h1><?php echo esc_html($phone_a->model); ?><span>vs</span><?php echo esc_html($phone_b->model); ?></h1>
            <p>두 기기의 차이를 같은 기준으로 빠르게 확인하세요.</p>
        </header>
        <section class="compare-identities">
            <?php foreach ([$phone_a, $phone_b] as $phone) : ?>
                <article>
                    <?php if (pc_public_image_url($phone)) : ?>
                        <img loading="eager" decoding="async" width="240" height="190" src="<?php echo esc_url(pc_public_image_url($phone)); ?>" alt="<?php echo esc_attr($phone->model); ?>">
                    <?php endif; ?>
                    <div>
                        <span><?php echo esc_html($phone->brand); ?></span>
                        <h2><?php echo esc_html($phone->model); ?></h2>
                        <a href="<?php echo esc_url(get_permalink($phone->post_id)); ?>">상세 스펙 보기</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="compare-insights">
            <?php
            $score_a = $insights['score_a'];
            $score_b = $insights['score_b'];
            $overall_difference = ($score_a['overall'] !== null && $score_b['overall'] !== null)
                ? $score_a['overall'] - $score_b['overall']
                : 0;
            ?>
            <div class="scoreboard">
                <div class="scoreboard-heading">
                    <p class="eyebrow">사양 평가 / 자체 평가</p>
                    <h2>기기 평가 점수</h2>
                    <p>수집된 하드웨어 사양을 같은 규칙으로 계산한 참고용 점수입니다. 실제 벤치마크 측정값과는 다릅니다.</p>
                </div>
                <div class="overall-scores">
                    <?php foreach ([[$phone_a, $score_a, $overall_difference], [$phone_b, $score_b, -$overall_difference]] as [$phone, $score, $lead]) : ?>
                        <article>
                            <?php if ($lead >= 2) : ?><span class="advantage-badge">종합 우세</span><?php endif; ?>
                            <small><?php echo esc_html($phone->model); ?></small>
                            <strong><?php echo $score['overall'] !== null ? esc_html($score['overall']) : '—'; ?></strong>
                            <em>/ 100</em>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="category-scores">
                    <?php foreach ($score_a['categories'] as $key => $category_a) :
                        $category_b = $score_b['categories'][$key];
                        $a_value = $category_a['score'];
                        $b_value = $category_b['score'];
                        $gap = ($a_value !== null && $b_value !== null) ? $a_value - $b_value : 0;
                    ?>
                        <div class="score-row">
                            <strong><?php echo esc_html($category_a['label']); ?></strong>
                            <span class="<?php echo $gap >= 3 ? 'is-winner' : ''; ?>">
                                <?php echo $a_value !== null ? esc_html($a_value) : '—'; ?>
                                <?php if ($gap >= 3) : ?><i>우세</i><?php endif; ?>
                            </span>
                            <span class="<?php echo $gap <= -3 ? 'is-winner' : ''; ?>">
                                <?php echo $b_value !== null ? esc_html($b_value) : '—'; ?>
                                <?php if ($gap <= -3) : ?><i>우세</i><?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($score_a['benchmark'] || $score_b['benchmark']) : ?>
                    <div class="measured-benchmark">
                        <strong>수집된 실측 성능 데이터</strong>
                        <span><?php echo esc_html($score_a['benchmark'] ?: '데이터 없음'); ?></span>
                        <span><?php echo esc_html($score_b['benchmark'] ?: '데이터 없음'); ?></span>
                    </div>
                <?php endif; ?>
                <p class="score-note">점수는 데이터가 있는 항목만 반영하며, 실제 사용 경험·가격·소프트웨어 최적화는 포함하지 않습니다.</p>
            </div>
            <div class="compare-verdict">
                <p class="eyebrow">비교 결론 / 자동 분석</p>
                <h2>핵심 비교 결론</h2>
                <p><?php echo esc_html($insights['verdict']); ?></p>
            </div>
            <?php if ($insights['differences']) : ?>
                <div class="difference-grid">
                    <?php foreach ($insights['differences'] as $difference) : ?>
                        <article>
                            <span><?php echo esc_html($difference['label']); ?></span>
                            <div><strong><?php echo esc_html($difference['a']); ?></strong><i>vs</i><strong><?php echo esc_html($difference['b']); ?></strong></div>
                            <p><?php echo esc_html($difference['note']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="choice-reasons">
                <?php foreach ([[$phone_a, $insights['a_reasons']], [$phone_b, $insights['b_reasons']]] as [$phone, $reasons]) : ?>
                    <div>
                        <span><?php echo esc_html($phone->brand); ?></span>
                        <h3><?php echo esc_html($phone->model); ?>을 선택할 이유</h3>
                        <ul>
                            <?php foreach ($reasons as $reason) : ?><li><?php echo esc_html($reason); ?></li><?php endforeach; ?>
                            <?php if (!$reasons) : ?><li>아래 전체 스펙에서 필요한 기능을 직접 확인하세요.</li><?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="compare-filters" data-compare-filters>
            <span>비교표 보기</span>
            <button class="is-active" type="button" data-mode="different">주요 차이</button>
            <button type="button" data-mode="all">전체 스펙</button>
        </div>
        <section class="spec-sheet compare-sheet" data-compare-table>
            <?php $last_section = ''; ?>
            <?php foreach ($rows as $row) : ?>
                <?php if ($last_section !== $row['section']) : $last_section = $row['section']; ?>
                    <h2><?php echo esc_html(pc_translate_spec_section($row['section'])); ?></h2>
                <?php endif; ?>
                <div class="compare-row <?php echo $row['same'] ? 'is-same' : ''; ?>" <?php echo $row['same'] ? 'hidden' : ''; ?>>
                    <?php if (function_exists('ps_spec_label')) ps_spec_label($row['section'], $row['field'], 'compare-help-' . md5($row['section'] . '|' . $row['field'])); ?>
                    <span><?php echo esc_html($row['a'] ?: '—'); ?></span>
                    <span><?php echo esc_html($row['b'] ?: '—'); ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
