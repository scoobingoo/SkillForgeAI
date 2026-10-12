<?php

// SPDX-License-Identifier: Apache-2.0
// Dịch vụ tính điểm tương thích kỹ năng thuần tuý (pure function, không phụ thuộc I/O)

namespace App\Services;

class ScoringService
{
    /**
     * Ánh xạ mức độ thành số nguyên: beginner = 1, intermediate = 2, advanced = 3.
     * Nếu không ghi mức độ thì mặc định trả về $default (JD không ghi mức thì coi là 2).
     */
    public static function levelToNumber(?string $level, int $default = 2): int
    {
        return match (strtolower((string) $level)) {
            'beginner' => 1,
            'intermediate' => 2,
            'advanced' => 3,
            default => $default,
        };
    }

    /**
     * Trọng số mức độ quan trọng trong JD: required = 2, nice_to_have = 1.
     */
    public static function importanceWeight(?string $importance): int
    {
        return strtolower((string) $importance) === 'nice_to_have' ? 1 : 2;
    }

    /**
     * Tính toán điểm đáp ứng tổng thể (matchScore), điểm theo nhóm kỹ năng (categoryScores),
     * danh sách kỹ năng đã khớp (matchedSkills) và danh sách khoảng cách kỹ năng (gaps).
     *
     * Công thức tính điểm chuẩn hoá:
     * - Trọng số w: required = 2, nice_to_have = 1.
     * - Mức độ: beginner = 1, intermediate = 2, advanced = 3 (JD không ghi mức thì coi là 2).
     * - Điểm một kỹ năng = min(1, levelCv / levelJd). Không có trong CV thì bằng 0.
     * - CV chỉ có kỹ năng con (theo parent trong taxonomy) thì được 0.5.
     * - matchScore = Σ(w * điểm) / Σw.
     *
     * @param  array<int, array{slug: string, name: string, category?: string, level?: ?string, evidence: string}>  $cvSkills
     * @param  array<int, array{slug: string, name: string, category?: string, level?: ?string, importance?: string, evidence: string}>  $jdRequirements
     * @param  array<string, string|null>  $taxonomyParentMap  Ánh xạ child_slug => parent_slug từ Taxonomy
     * @return array{
     *   matchScore: float,
     *   categoryScores: array<string, float>,
     *   matchedSkills: array<int, array<string, mixed>>,
     *   gaps: array<int, array<string, mixed>>
     * }
     */
    public function calculate(array $cvSkills, array $jdRequirements, array $taxonomyParentMap = []): array
    {
        if (empty($jdRequirements)) {
            return [
                'matchScore' => 0.0,
                'categoryScores' => [],
                'matchedSkills' => [],
                'gaps' => [],
            ];
        }

        // Lập chỉ mục kỹ năng trong CV theo slug (nếu trùng slug thì giữ mức cao nhất)
        $cvBySlug = [];
        foreach ($cvSkills as $cvItem) {
            $slug = strtolower(trim((string) ($cvItem['slug'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $lvlNum = self::levelToNumber($cvItem['level'] ?? null, 2);
            if (! isset($cvBySlug[$slug]) || $lvlNum > $cvBySlug[$slug]['levelNum']) {
                $cvBySlug[$slug] = [
                    'slug' => $slug,
                    'name' => (string) ($cvItem['name'] ?? $slug),
                    'category' => (string) ($cvItem['category'] ?? 'general'),
                    'level' => $cvItem['level'] ?? 'intermediate',
                    'levelNum' => $lvlNum,
                    'evidence' => (string) ($cvItem['evidence'] ?? ''),
                ];
            }
        }

        $totalWeightedScore = 0.0;
        $totalWeight = 0;

        $categoryBuckets = []; // category => ['weighted' => float, 'weight' => int]
        $matchedSkills = [];
        $gaps = [];

        foreach ($jdRequirements as $req) {
            $jdSlug = strtolower(trim((string) ($req['slug'] ?? '')));
            if ($jdSlug === '') {
                continue;
            }

            $skillName = (string) ($req['name'] ?? $jdSlug);
            $category = (string) ($req['category'] ?? 'general');
            $importance = strtolower((string) ($req['importance'] ?? 'required')) === 'nice_to_have'
                ? 'nice_to_have'
                : 'required';
            $weight = self::importanceWeight($importance);
            $levelJd = self::levelToNumber($req['level'] ?? null, 2);
            $jdEvidence = (string) ($req['evidence'] ?? '');

            $skillScore = 0.0;
            $cvEvidence = null;
            $cvLevelLabel = null;
            $matchedViaChild = false;

            if (isset($cvBySlug[$jdSlug])) {
                // Khớp trực tiếp kỹ năng trong CV
                $levelCv = $cvBySlug[$jdSlug]['levelNum'];
                $skillScore = min(1.0, $levelCv / $levelJd);
                $cvEvidence = $cvBySlug[$jdSlug]['evidence'];
                $cvLevelLabel = $cvBySlug[$jdSlug]['level'];
            } else {
                // Kiểm tra xem CV có chứa kỹ năng con (child skill có parent === $jdSlug) không
                foreach ($cvBySlug as $cvSlug => $cvData) {
                    $parentSlug = isset($taxonomyParentMap[$cvSlug])
                        ? strtolower((string) $taxonomyParentMap[$cvSlug])
                        : null;
                    if ($parentSlug !== null && $parentSlug === $jdSlug) {
                        $skillScore = 0.5;
                        $cvEvidence = $cvData['evidence'];
                        $cvLevelLabel = $cvData['level'];
                        $matchedViaChild = true;
                        break;
                    }
                }
            }

            $totalWeightedScore += $weight * $skillScore;
            $totalWeight += $weight;

            if (! isset($categoryBuckets[$category])) {
                $categoryBuckets[$category] = ['weighted' => 0.0, 'weight' => 0];
            }
            $categoryBuckets[$category]['weighted'] += $weight * $skillScore;
            $categoryBuckets[$category]['weight'] += $weight;

            if ($skillScore >= 1.0) {
                $matchedSkills[] = [
                    'skill' => $jdSlug,
                    'name' => $skillName,
                    'category' => $category,
                    'importance' => $importance,
                    'score' => round($skillScore, 4),
                    'jdEvidence' => $jdEvidence,
                    'cvEvidence' => $cvEvidence,
                ];
            } else {
                // Tạo mục gap: missing (điểm = 0) hoặc below_level (0 < điểm < 1)
                $gapType = $skillScore <= 0.0 ? 'missing' : 'below_level';
                $gaps[] = [
                    'skill' => $jdSlug,
                    'name' => $skillName,
                    'category' => $category,
                    'type' => $gapType,
                    'priority' => $importance,
                    'requiredLevel' => $req['level'] ?? 'intermediate',
                    'currentLevel' => $cvLevelLabel,
                    'matchedViaChild' => $matchedViaChild,
                    'score' => round($skillScore, 4),
                    'jdEvidence' => $jdEvidence,
                    'cvEvidence' => $cvEvidence,
                ];
            }
        }

        // Sắp xếp danh sách gap: ưu tiên 'required' trước 'nice_to_have', và 'missing' trước 'below_level'
        usort($gaps, function (array $a, array $b): int {
            $prioA = $a['priority'] === 'required' ? 0 : 1;
            $prioB = $b['priority'] === 'required' ? 0 : 1;
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            return $a['score'] <=> $b['score'];
        });

        $matchScore = $totalWeight > 0 ? round($totalWeightedScore / $totalWeight, 4) : 0.0;

        $categoryScores = [];
        foreach ($categoryBuckets as $cat => $bucket) {
            $categoryScores[$cat] = $bucket['weight'] > 0
                ? round($bucket['weighted'] / $bucket['weight'], 4)
                : 0.0;
        }

        return [
            'matchScore' => $matchScore,
            'categoryScores' => $categoryScores,
            'matchedSkills' => $matchedSkills,
            'gaps' => $gaps,
        ];
    }
}
