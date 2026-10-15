<?php

// SPDX-License-Identifier: Apache-2.0
// Dịch vụ truy xuất Hybrid Search (Vector + Full-Text) kết hợp Reciprocal Rank Fusion (RRF k=60) và lập lộ trình học

namespace App\Services;

use App\Models\GapReport;
use App\Models\Resource;
use App\Models\ResourceChunk;
use App\Models\Roadmap;
use App\Services\Llm\LlmProviderInterface;

class RagRetrievalService
{
    private const RRF_K = 60;

    public function __construct(
        private readonly LlmProviderInterface $llm,
    ) {}

    /**
     * Truy xuất các phân đoạn tài nguyên học (chunks) cho một kỹ năng gap bằng Hybrid Search + RRF (k = 60).
     *
     * Quy trình truy xuất lai (Hybrid Retrieval):
     * 1. Lọc trước các chunk có chứa skill thuộc gap.
     * 2. Lấy top 20 theo độ tương đồng vector và top 20 theo khớp từ khoá (full-text).
     * 3. Hợp nhất bằng Reciprocal Rank Fusion (k = 60), giữ top 8 mỗi gap.
     *
     * @return array<int, array{chunk: ResourceChunk, resource: ?resource, rrf_score: float}>
     */
    public function retrieveForGapSkill(string $skillSlug, string $queryText): array
    {
        $allChunks = ResourceChunk::with('resource')->get()->filter(function (ResourceChunk $chunk) use ($skillSlug) {
            $chunkSkills = is_array($chunk->skills) ? array_map('strtolower', $chunk->skills) : [];

            return in_array(strtolower($skillSlug), $chunkSkills, true);
        })->values();

        // Nếu chưa có chunk khớp chính xác slug, mở rộng sang toàn bộ kho tài nguyên
        if ($allChunks->isEmpty()) {
            $allChunks = ResourceChunk::with('resource')->get();
        }

        if ($allChunks->isEmpty()) {
            return [];
        }

        $queryVectors = $this->llm->embed([$queryText]);
        $queryVec = $queryVectors[0] ?? [];
        $normQuery = SkillNormalizerService::normalizeForEvidenceMatch($queryText);
        $queryTerms = array_filter(explode(' ', $normQuery), fn ($w) => strlen($w) >= 2);

        // Xếp hạng Vector Top-20
        $vectorScored = [];
        // Xếp hạng Full-Text Top-20
        $ftsScored = [];

        foreach ($allChunks as $chunk) {
            $chunkVec = is_array($chunk->embedding_json) ? $chunk->embedding_json : [];
            $vecSim = (! empty($queryVec) && ! empty($chunkVec))
                ? SkillNormalizerService::cosineSimilarity($queryVec, $chunkVec)
                : 0.0;

            $vectorScored[] = ['chunk' => $chunk, 'score' => $vecSim];

            // Tính điểm khớp từ khoá Full-text (kết hợp heading + content đã bỏ dấu)
            $normContent = SkillNormalizerService::normalizeForEvidenceMatch(
                ($chunk->heading ?? '').' '.$chunk->content
            );
            $termHits = 0;
            foreach ($queryTerms as $term) {
                if (str_contains($normContent, $term)) {
                    $termHits += substr_count($normContent, $term);
                }
            }
            $ftsScored[] = ['chunk' => $chunk, 'score' => (float) $termHits];
        }

        usort($vectorScored, fn ($a, $b) => $b['score'] <=> $a['score']);
        usort($ftsScored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $top20Vector = array_slice($vectorScored, 0, 20);
        $top20Fts = array_slice($ftsScored, 0, 20);

        // Hợp nhất bằng Reciprocal Rank Fusion: RRF_score(d) = Σ 1 / (k + rank_i(d)) với k = 60
        $rrfMap = [];
        foreach ($top20Vector as $rankZeroBased => $item) {
            $id = $item['chunk']->id;
            $rank = $rankZeroBased + 1;
            $rrfMap[$id] = [
                'chunk' => $item['chunk'],
                'resource' => $item['chunk']->resource,
                'rrf_score' => ($rrfMap[$id]['rrf_score'] ?? 0.0) + (1.0 / (self::RRF_K + $rank)),
            ];
        }

        foreach ($top20Fts as $rankZeroBased => $item) {
            $id = $item['chunk']->id;
            $rank = $rankZeroBased + 1;
            $rrfMap[$id] = [
                'chunk' => $item['chunk'],
                'resource' => $item['chunk']->resource,
                'rrf_score' => ($rrfMap[$id]['rrf_score'] ?? 0.0) + (1.0 / (self::RRF_K + $rank)),
            ];
        }

        $merged = array_values($rrfMap);
        usort($merged, fn ($a, $b) => $b['rrf_score'] <=> $a['rrf_score']);

        // Giữ tối đa top 8 phân đoạn điểm cao nhất cho mỗi kỹ năng gap
        return array_slice($merged, 0, 8);
    }

    /**
     * Sinh lộ trình học tập theo tuần cho một GapReport:
     * - Chỉ giữ các mục có `resourceSlug` tồn tại thực tế trong bảng `resources`.
     * - Nếu tổng số giờ của một tuần vượt `hoursPerWeek`, CODE tự động dời mục sang tuần kế tiếp (không gọi lại LLM).
     */
    public function generateRoadmap(GapReport $report, int $hoursPerWeek = 10, int $requestedWeeks = 4): Roadmap
    {
        $hoursPerWeek = max(2, min(60, $hoursPerWeek));
        $requestedWeeks = max(1, min(24, $requestedWeeks));

        $gaps = is_array($report->gaps) ? $report->gaps : [];
        $validResourcesBySlug = Resource::all()->keyBy('slug');

        $candidateItems = [];
        $usedResourceSlugs = [];

        foreach ($gaps as $gap) {
            $skillSlug = (string) ($gap['skill'] ?? '');
            $skillName = (string) ($gap['name'] ?? $skillSlug);
            if ($skillSlug === '') {
                continue;
            }

            $retrieved = $this->retrieveForGapSkill($skillSlug, "{$skillName} {$skillSlug}");
            foreach ($retrieved as $hit) {
                $res = $hit['resource'];
                if (! $res instanceof Resource) {
                    continue;
                }
                if (isset($usedResourceSlugs[$res->slug])) {
                    continue;
                }

                $usedResourceSlugs[$res->slug] = true;
                $estHours = max(1.0, min((float) $res->est_hours, (float) $hoursPerWeek));

                $candidateItems[] = [
                    'resourceSlug' => $res->slug,
                    'title' => $res->title,
                    'url' => $res->url,
                    'license' => $res->license,
                    'skillSlug' => $skillSlug,
                    'skillName' => $skillName,
                    'task' => "Nghiên cứu tài liệu \"{$res->title}\" và thực hành bài tập áp dụng cho kỹ năng {$skillName}.",
                    'estHours' => $estHours,
                    'completed' => false,
                ];
            }
        }

        // Nếu báo cáo không có gap hoặc chưa tìm được resource đặc thù, lấy các tài nguyên nền tảng trong DB
        if (empty($candidateItems) && $validResourcesBySlug->isNotEmpty()) {
            foreach ($validResourcesBySlug->take(6) as $res) {
                $firstSkill = is_array($res->skills) && ! empty($res->skills) ? $res->skills[0] : 'general';
                $candidateItems[] = [
                    'resourceSlug' => $res->slug,
                    'title' => $res->title,
                    'url' => $res->url,
                    'license' => $res->license,
                    'skillSlug' => $firstSkill,
                    'skillName' => strtoupper($firstSkill),
                    'task' => "Ôn tập nâng cao qua tài liệu \"{$res->title}\".",
                    'estHours' => max(1.0, min((float) $res->est_hours, (float) $hoursPerWeek)),
                    'completed' => false,
                ];
            }
        }

        // Kiểm tra nghiêm ngặt resourceSlug phải tồn tại trong bảng resources và chia vào các tuần không vượt hoursPerWeek
        $weeks = $this->packItemsIntoWeeks($candidateItems, $validResourcesBySlug->keys()->all(), $hoursPerWeek, $requestedWeeks);

        return Roadmap::updateOrCreate(
            ['gap_report_id' => $report->id],
            [
                'hours_per_week' => $hoursPerWeek,
                'total_weeks' => count($weeks),
                'weeks' => $weeks,
            ]
        );
    }

    /**
     * Hàm thuần kiểm tra `resourceSlug` hợp lệ và tự động dời mục sang tuần sau nếu tổng giờ vượt `hoursPerWeek`.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $existingResourceSlugs
     * @return array<int, array{week: int, totalHours: float, focusSkills: array<int, string>, items: array<int, array<string, mixed>>}>
     */
    public function packItemsIntoWeeks(
        array $items,
        array $existingResourceSlugs,
        int $hoursPerWeek,
        int $minWeeks = 1
    ): array {
        $validSet = array_flip($existingResourceSlugs);
        $weeks = [];
        $currentWeekIndex = 1;
        $currentWeekHours = 0.0;
        $currentWeekItems = [];
        $currentWeekSkills = [];

        foreach ($items as $item) {
            $slug = (string) ($item['resourceSlug'] ?? '');
            // Loại bỏ mọi mục có resourceSlug không tồn tại trong DB
            if ($slug === '' || ! isset($validSet[$slug])) {
                continue;
            }

            $itemHours = (float) ($item['estHours'] ?? 2.0);
            // Nếu một mục đơn lẻ vượt quá hoursPerWeek, giới hạn bằng hoursPerWeek để có thể xếp vào tuần mới
            if ($itemHours > $hoursPerWeek) {
                $itemHours = (float) $hoursPerWeek;
                $item['estHours'] = $itemHours;
            }

            // Nếu thêm mục này làm tổng giờ của tuần hiện tại vượt quá hoursPerWeek -> dời sang tuần sau
            if (! empty($currentWeekItems) && ($currentWeekHours + $itemHours) > $hoursPerWeek) {
                $weeks[] = [
                    'week' => $currentWeekIndex,
                    'totalHours' => round($currentWeekHours, 1),
                    'focusSkills' => array_values(array_unique($currentWeekSkills)),
                    'items' => $currentWeekItems,
                ];
                $currentWeekIndex++;
                $currentWeekHours = 0.0;
                $currentWeekItems = [];
                $currentWeekSkills = [];
            }

            $currentWeekItems[] = $item;
            $currentWeekHours += $itemHours;
            if (! empty($item['skillSlug'])) {
                $currentWeekSkills[] = (string) $item['skillSlug'];
            }
        }

        if (! empty($currentWeekItems)) {
            $weeks[] = [
                'week' => $currentWeekIndex,
                'totalHours' => round($currentWeekHours, 1),
                'focusSkills' => array_values(array_unique($currentWeekSkills)),
                'items' => $currentWeekItems,
            ];
        }

        return $weeks;
    }
}
