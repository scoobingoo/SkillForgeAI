<?php

// SPDX-License-Identifier: Apache-2.0
// Dịch vụ kiểm chứng trích dẫn nguyên văn (evidence) và chuẩn hoá kỹ năng về Taxonomy theo kiến trúc 3 tầng

namespace App\Services;

use App\Models\Skill;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\PromptLoader;
use Illuminate\Support\Collection;

class SkillNormalizerService
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
    ) {}

    /**
     * Chuẩn hoá chuỗi phục vụ đối chiếu đoạn trích nguyên văn (evidence):
     * chuyển chữ thường, bỏ dấu tiếng Việt và gộp khoảng trắng liên tiếp.
     */
    public static function normalizeForEvidenceMatch(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $unaccented = self::stripVietnameseAccents($lower);
        // Thay thế mọi ký tự xuống dòng, dấu gạch đầu dòng và khoảng trắng thừa thành 1 khoảng trắng đơn
        $collapsed = (string) preg_replace('/[\s\p{Z}\r\n\t]+/u', ' ', $unaccented);

        return trim($collapsed);
    }

    /**
     * Kiểm tra đoạn trích `evidence` có thực sự xuất hiện trong văn bản gốc (CV hoặc JD) hay không để chống ảo giác (hallucination).
     */
    public static function verifyEvidenceInSource(string $evidence, string $sourceText): bool
    {
        $normEvidence = self::normalizeForEvidenceMatch($evidence);
        if ($normEvidence === '' || mb_strlen($normEvidence) < 3) {
            return false;
        }

        $normSource = self::normalizeForEvidenceMatch($sourceText);

        return str_contains($normSource, $normEvidence);
    }

    /**
     * Chuẩn hoá tên kỹ năng để so khớp tầng 1 (Alias): chữ thường, bỏ dấu, bỏ hậu tố .js / js.
     */
    public static function normalizeAliasKey(string $skillName): string
    {
        $clean = mb_strtolower(trim($skillName), 'UTF-8');
        $clean = self::stripVietnameseAccents($clean);
        // Bỏ hậu tố .js hoặc js (ví dụ: react.js -> react, nodejs -> node) trừ khi toàn bộ từ là "js"
        if ($clean !== 'js' && strlen($clean) > 3) {
            $clean = (string) preg_replace('/(?:\.js|js)$/i', '', $clean);
        }
        $clean = (string) preg_replace('/[^a-z0-9+#]+/i', '', $clean);

        return trim($clean);
    }

    /**
     * Chuẩn hoá một tên kỹ năng thô về Taxonomy theo 3 tầng:
     * 1. Khớp alias chính xác -> mapping = 'alias'
     * 2. Láng giềng vector gần nhất có cosine >= 0.82 -> mapping = 'vector'
     * 3. Cosine trong khoảng [0.65, 0.82) -> đưa top-5 ứng viên cho LLM chọn -> mapping = 'llm'
     * 4. Cosine < 0.65 hoặc LLM trả 'none' -> mapping = 'unmapped'
     *
     * @param  Collection<int, Skill>|null  $allSkills  Danh sách taxonomy kỹ năng (truyền vào để tái sử dụng trong vòng lặp)
     * @return array{skill: ?Skill, canonical_slug: ?string, category: string, mapping: string}
     */
    public function normalizeSkill(string $rawName, ?Collection $allSkills = null): array
    {
        $skills = $allSkills ?? Skill::all();
        if ($skills->isEmpty()) {
            return [
                'skill' => null,
                'canonical_slug' => null,
                'category' => 'general',
                'mapping' => 'unmapped',
            ];
        }

        $targetKey = self::normalizeAliasKey($rawName);

        // TẦNG 1: Khớp chính xác theo slug, name hoặc aliases[] sau khi chuẩn hoá
        foreach ($skills as $skill) {
            $candidates = array_merge(
                [$skill->slug, $skill->name],
                is_array($skill->aliases) ? $skill->aliases : []
            );

            foreach ($candidates as $candidate) {
                if ($targetKey !== '' && self::normalizeAliasKey((string) $candidate) === $targetKey) {
                    return [
                        'skill' => $skill,
                        'canonical_slug' => $skill->slug,
                        'category' => $skill->category,
                        'mapping' => 'alias',
                    ];
                }
            }
        }

        // TẦNG 2: So khớp độ tương đồng Cosine trên vector embedding
        $acceptThreshold = (float) config('ai.skill_vector_accept_threshold', 0.82);
        $llmMinThreshold = (float) config('ai.skill_vector_llm_min_threshold', 0.65);

        $vectors = $this->llm->embed([$rawName]);
        $queryVec = $vectors[0] ?? [];

        $scoredCandidates = [];
        foreach ($skills as $skill) {
            $skillVec = is_array($skill->embedding_json) ? $skill->embedding_json : [];
            if (empty($skillVec) || empty($queryVec)) {
                continue;
            }
            $cosine = self::cosineSimilarity($queryVec, $skillVec);
            $scoredCandidates[] = [
                'skill' => $skill,
                'cosine' => $cosine,
            ];
        }

        usort($scoredCandidates, fn ($a, $b) => $b['cosine'] <=> $a['cosine']);
        $topCandidate = $scoredCandidates[0] ?? null;

        if ($topCandidate !== null && $topCandidate['cosine'] >= $acceptThreshold) {
            /** @var Skill $matched */
            $matched = $topCandidate['skill'];

            return [
                'skill' => $matched,
                'canonical_slug' => $matched->slug,
                'category' => $matched->category,
                'mapping' => 'vector',
            ];
        }

        // TẦNG 3: Nếu cosine nằm trong [0.65, 0.82), đưa top-5 ứng viên cho LLM chọn
        if ($topCandidate !== null && $topCandidate['cosine'] >= $llmMinThreshold) {
            $top5 = array_slice($scoredCandidates, 0, 5);
            $candidateList = array_map(fn ($item) => [
                'slug' => $item['skill']->slug,
                'name' => $item['skill']->name,
                'category' => $item['skill']->category,
            ], $top5);

            $prompt = PromptLoader::load('disambiguate-skill', [
                'raw_name' => $rawName,
                'candidates_json' => json_encode($candidateList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);

            $result = $this->llm->generateObject(
                jsonSchema: [
                    'type' => 'object',
                    'properties' => [
                        'selected_slug' => ['type' => 'string'],
                    ],
                    'required' => ['selected_slug'],
                ],
                validator: function (array $data): array {
                    if (! isset($data['selected_slug']) || ! is_string($data['selected_slug'])) {
                        throw new \InvalidArgumentException('Thiếu trường selected_slug kiểu chuỗi.');
                    }

                    return $data;
                },
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ]
            );

            $selectedSlug = trim((string) ($result['selected_slug'] ?? 'none'));
            if ($selectedSlug !== '' && $selectedSlug !== 'none') {
                $found = $skills->firstWhere('slug', $selectedSlug);
                if ($found instanceof Skill) {
                    return [
                        'skill' => $found,
                        'canonical_slug' => $found->slug,
                        'category' => $found->category,
                        'mapping' => 'llm',
                    ];
                }
            }
        }

        // TẦNG 4: Đánh dấu unmapped
        return [
            'skill' => null,
            'canonical_slug' => null,
            'category' => 'general',
            'mapping' => 'unmapped',
        ];
    }

    /**
     * Tính độ tương đồng Cosine giữa 2 vector cùng số chiều.
     *
     * @param  array<int, float>  $vecA
     * @param  array<int, float>  $vecB
     */
    public static function cosineSimilarity(array $vecA, array $vecB): float
    {
        $len = min(count($vecA), count($vecB));
        if ($len === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $len; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Loại bỏ dấu tiếng Việt để so khớp chuỗi bất biến theo dấu.
     */
    public static function stripVietnameseAccents(string $str): string
    {
        $patterns = [
            '/[àáạảãâầấậẩẫăằắặẳẵ]/u' => 'a',
            '/[ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ]/u' => 'A',
            '/[èéẹẻẽêềếệểễ]/u' => 'e',
            '/[ÈÉẸẺẼÊỀẾỆỂỄ]/u' => 'E',
            '/[ìíịỉĩ]/u' => 'i',
            '/[ÌÍỊỈĨ]/u' => 'I',
            '/[òóọỏõôồốộổỗơờớợởỡ]/u' => 'o',
            '/[ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ]/u' => 'O',
            '/[ùúụủũưừứựửữ]/u' => 'u',
            '/[ÙÚỤỦŨƯỪỨỰỬỮ]/u' => 'U',
            '/[ỳýỵỷỹ]/u' => 'y',
            '/[ỲÝỴỶỸ]/u' => 'Y',
            '/[đ]/u' => 'd',
            '/[Đ]/u' => 'D',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $str);
    }
}
