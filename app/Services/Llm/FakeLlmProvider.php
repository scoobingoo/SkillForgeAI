<?php

// SPDX-License-Identifier: Apache-2.0
// FakeLlmProvider trả dữ liệu tất định dùng cho Unit/Feature Test, CI và chế độ trải nghiệm độc lập (Offline Demo)

namespace App\Services\Llm;

use App\Exceptions\ApiException;
use Throwable;

class FakeLlmProvider implements LlmProviderInterface
{
    /**
     * Hàng đợi các phản hồi tuỳ chỉnh dùng để test kịch bản retry khi LLM trả JSON lỗi.
     *
     * @var array<int, string>
     */
    private array $queuedRawResponses = [];

    /**
     * Cho phép bài test nạp trước các chuỗi trả về giả lập (ví dụ JSON lỗi ở lần 1, JSON đúng ở lần 2).
     *
     * @param  array<int, string>  $responses
     */
    public function queueRawResponses(array $responses): void
    {
        $this->queuedRawResponses = $responses;
    }

    public function chat(array $messages, array $opts = []): string
    {
        if (! empty($this->queuedRawResponses)) {
            return array_shift($this->queuedRawResponses);
        }

        $joined = implode("\n", array_column($messages, 'content'));

        // Tự động nhận diện loại tác vụ từ prompt để sinh JSON chuẩn xác dựa trên nội dung văn bản gốc
        if (str_contains($joined, '<cv>') && str_contains($joined, '</cv>')) {
            return json_encode($this->extractSkillsFromTaggedText($joined, 'cv'), JSON_UNESCAPED_UNICODE);
        }

        if (str_contains($joined, '<jd>') && str_contains($joined, '</jd>')) {
            return json_encode($this->extractSkillsFromTaggedText($joined, 'jd'), JSON_UNESCAPED_UNICODE);
        }

        if (str_contains($joined, 'TASK_SKILL_DISAMBIGUATION')) {
            return json_encode(['selected_slug' => 'none'], JSON_UNESCAPED_UNICODE);
        }

        if (str_contains($joined, 'TASK_GAP_EXPLANATION')) {
            return json_encode([
                'explanation' => 'Kỹ năng này được yêu cầu rõ trong JD nhưng chưa thấy đề cập hoặc chưa đạt mức độ tương đương trong CV của bạn.',
            ], JSON_UNESCAPED_UNICODE);
        }

        if (str_contains($joined, 'TASK_GENERATE_QUESTION')) {
            return json_encode([
                'question' => 'Bạn hãy giải thích nguyên lý hoạt động và cách bạn áp dụng kỹ năng này trong dự án thực tế để xử lý bài toán hiệu năng?',
                'reference_answer' => 'Ứng viên cần nêu được khái niệm cốt lõi, kiến trúc xử lý, cách tối ưu hoá truy vấn/bộ nhớ và ví dụ thực tế kèm số liệu đo lường.',
            ], JSON_UNESCAPED_UNICODE);
        }

        if (str_contains($joined, 'TASK_GRADE_ANSWER')) {
            return json_encode($this->gradeAnswerDeterministically($joined), JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    }

    public function generateObject(array $jsonSchema, callable $validator, array $messages, array $opts = []): array
    {
        $maxRetries = 2;
        $workingMessages = $messages;
        $lastError = '';

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $rawOutput = $this->chat($workingMessages, $opts);

            try {
                $decoded = json_decode($rawOutput, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new \RuntimeException('Dữ liệu giải mã không phải mảng JSON.');
                }

                return $validator($decoded);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt < $maxRetries) {
                    $workingMessages[] = ['role' => 'assistant', 'content' => $rawOutput];
                    $workingMessages[] = ['role' => 'user', 'content' => 'Lỗi parse schema: '.$lastError];
                }
            }
        }

        throw new ApiException(
            errorCode: 'LLM_INVALID_OUTPUT',
            message: 'FakeLlmProvider vượt quá số lần thử lại (2 retries): '.$lastError,
            statusCode: 502
        );
    }

    /**
     * Sinh vector 1024 chiều tất định dựa trên băm từ khoá để tìm kiếm tương đồng ngữ nghĩa hoạt động ổn định trong test.
     */
    public function embed(array $texts): array
    {
        $vectors = [];
        foreach ($texts as $text) {
            $vec = array_fill(0, 1024, 0.0);
            $normalized = mb_strtolower(trim($text), 'UTF-8');
            $tokens = preg_split('/[\s,.;:\/\-_]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [$normalized];

            foreach ($tokens as $token) {
                $hash = crc32($token);
                $idx1 = abs($hash) % 1024;
                $idx2 = abs((int) ($hash >> 8)) % 1024;
                $vec[$idx1] += 1.0;
                $vec[$idx2] += 0.5;
            }

            // Chuẩn hoá vector đơn vị (L2 normalization) để tính Cosine Similarity chính xác
            $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vec)));
            if ($norm > 0) {
                $vec = array_map(fn ($v) => round($v / $norm, 6), $vec);
            } else {
                $vec[0] = 1.0;
            }

            $vectors[] = $vec;
        }

        return $vectors;
    }

    public function checkHealth(): array
    {
        return [
            'ok' => true,
            'models_ready' => true,
            'available_models' => ['qwen3:4b (fake)', 'bge-m3 (fake)'],
        ];
    }

    /**
     * Trích xuất kỹ năng thực tế kèm đoạn trích nguyên văn (evidence) từ thẻ <cv> hoặc <jd>.
     *
     * @return array{skills?: array<int, array<string, mixed>>, requirements?: array<int, array<string, mixed>>}
     */
    private function extractSkillsFromTaggedText(string $prompt, string $tag): array
    {
        $pattern = sprintf('/<%s>(.*?)<\/%s>/s', preg_quote($tag, '/'), preg_quote($tag, '/'));
        $rawText = '';
        if (preg_match_all($pattern, $prompt, $matches) && ! empty($matches[1])) {
            $rawText = trim((string) end($matches[1]));
        }

        $knownPatterns = [
            'PHP' => ['php', 'php 8'],
            'Laravel' => ['laravel'],
            'JavaScript' => ['javascript', 'es6'],
            'TypeScript' => ['typescript'],
            'React' => ['react', 'reactjs', 'react.js'],
            'Next.js' => ['next.js', 'nextjs'],
            'Vue.js' => ['vue', 'vue.js', 'vuejs'],
            'Tailwind CSS' => ['tailwind', 'tailwindcss', 'tailwind css'],
            'HTML5' => ['html', 'html5'],
            'CSS3' => ['css', 'css3'],
            'Node.js' => ['node.js', 'nodejs'],
            'NestJS' => ['nestjs', 'nest.js'],
            'Python' => ['python'],
            'PostgreSQL' => ['postgresql', 'postgres', 'pgvector'],
            'MySQL' => ['mysql'],
            'Redis' => ['redis'],
            'Docker' => ['docker', 'docker compose', 'dockerfile'],
            'Kubernetes' => ['kubernetes', 'k8s'],
            'Git' => ['git', 'github'],
            'CI/CD' => ['ci/cd', 'github actions', 'gitlab ci'],
            'REST API' => ['rest api', 'restful api', 'restful'],
            'GraphQL' => ['graphql'],
            'Unit Testing' => ['unit test', 'phpunit', 'vitest', 'jest', 'testing'],
            'Linux' => ['linux', 'ubuntu', 'bash'],
            'AWS' => ['aws', 'amazon web services'],
            'SQL' => ['sql', 'truy vấn sql'],
            'Data Analysis' => ['data analysis', 'phân tích dữ liệu', 'pandas'],
            'Power BI' => ['power bi', 'tableau'],
        ];

        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
        $items = [];

        foreach ($knownPatterns as $skillName => $keywords) {
            foreach ($lines as $line) {
                $cleanLine = trim($line);
                if ($cleanLine === '') {
                    continue;
                }
                $lowerLine = mb_strtolower($cleanLine, 'UTF-8');

                foreach ($keywords as $kw) {
                    if (preg_match('/\b'.preg_quote($kw, '/').'\b/iu', $lowerLine)) {
                        $level = 'intermediate';
                        if (preg_match('/(senior|advanced|chuyên sâu|thành thạo|3\+?\s*năm|4\+?\s*năm|5\+?\s*năm)/iu', $lowerLine)) {
                            $level = 'advanced';
                        } elseif (preg_match('/(beginner|basic|cơ bản|mới học|fresher|intern|làm quen)/iu', $lowerLine)) {
                            $level = 'beginner';
                        }

                        $years = null;
                        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:\+)?\s*(?:năm|years?)/iu', $lowerLine, $ym)) {
                            $years = (float) $ym[1];
                        }

                        $importance = 'required';
                        if (preg_match('/(nice to have|ưu tiên|điểm cộng|optional|lợi thế)/iu', $lowerLine)) {
                            $importance = 'nice_to_have';
                        }

                        $entry = [
                            'name' => $skillName,
                            'level' => $level,
                            'years' => $years,
                            'evidence' => $cleanLine,
                        ];

                        if ($tag === 'jd') {
                            $entry['importance'] = $importance;
                        }

                        $items[] = $entry;
                        break 2;
                    }
                }
            }
        }

        return $tag === 'cv'
            ? ['skills' => $items]
            : ['requirements' => $items];
    }

    /**
     * Chấm điểm câu trả lời phỏng vấn một cách tất định dựa trên độ dài và mức độ chi tiết của câu trả lời.
     *
     * @return array<string, mixed>
     */
    private function gradeAnswerDeterministically(string $prompt): array
    {
        $answer = '';
        if (preg_match_all('/<answer>(.*?)<\/answer>/s', $prompt, $m) && ! empty($m[1])) {
            $answer = trim((string) end($m[1]));
        }

        $wordCount = count(preg_split('/\s+/u', $answer, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($wordCount >= 40) {
            $score = 4;
            $correctness = 4;
            $depth = 4;
            $clarity = 5;
        } elseif ($wordCount >= 15) {
            $score = 3;
            $correctness = 3;
            $depth = 3;
            $clarity = 4;
        } else {
            $score = 2;
            $correctness = 2;
            $depth = 2;
            $clarity = 3;
        }

        return [
            'score' => $score,
            'criteria' => [
                'correctness' => $correctness,
                'depth' => $depth,
                'clarity' => $clarity,
            ],
            'strengths' => [
                'Trình bày đúng trọng tâm câu hỏi kỹ thuật.',
                'Có cấu trúc lập luận rõ ràng.',
            ],
            'missing_points' => [
                'Cần bổ sung thêm ví dụ thực tế về đo lường hiệu năng và xử lý lỗi biên (edge cases).',
            ],
            'sample_answer' => 'Trong thực tế, để giải quyết bài toán này chúng ta cần kết hợp thiết kế kiến trúc phân tầng rõ ràng, sử dụng chỉ mục (index) phù hợp trong cơ sở dữ liệu, kiểm soát truy vấn N+1 và viết kiểm thử tự động (Unit/Integration Test) để đảm bảo độ tin cậy khi mở rộng.',
        ];
    }
}
