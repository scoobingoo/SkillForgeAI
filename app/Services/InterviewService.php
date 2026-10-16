<?php

// SPDX-License-Identifier: Apache-2.0
// Dịch vụ phỏng vấn kỹ thuật thích ứng (Adaptive Mock Interview) và chấm điểm theo Rubric với nhiệt độ 0

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\GapReport;
use App\Models\InterviewSession;
use App\Models\InterviewTurn;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\PromptLoader;

class InterviewService
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
    ) {}

    /**
     * Khởi tạo phiên phỏng vấn mới (mặc định 5 câu, ưu tiên gap 'required') và sinh ngay câu hỏi đầu tiên.
     *
     * @param  array<int, string>  $types
     * @return array{session: InterviewSession, firstTurn: InterviewTurn}
     */
    public function startSession(GapReport $report, int $questionCount = 5, array $types = ['concept', 'scenario', 'behavioral', 'coding_discussion']): array
    {
        $questionCount = max(1, min(10, $questionCount));
        if (empty($types)) {
            $types = ['concept', 'scenario', 'behavioral', 'coding_discussion'];
        }

        $session = InterviewSession::create([
            'gap_report_id' => $report->id,
            'question_count' => $questionCount,
            'types' => array_values($types),
            'status' => 'in_progress',
        ]);

        $firstTurn = $this->generateNextTurn($session, null);

        return [
            'session' => $session->fresh(['turns']),
            'firstTurn' => $firstTurn,
        ];
    }

    /**
     * Chấm điểm câu trả lời của ứng viên theo Rubric ở nhiệt độ 0 và tự động sinh câu hỏi tiếp theo (hoặc kết thúc phiên).
     *
     * Quy tắc điều chỉnh độ khó thích ứng (Adaptive Difficulty):
     * - Câu trả lời trước đạt >= 4 điểm: câu sau khó hơn (easy -> intermediate -> hard).
     * - Câu trả lời trước đạt <= 2 điểm: câu sau giữ CÙNG kỹ năng nhưng dễ hơn (hard -> intermediate -> easy).
     *
     * @return array{turn: InterviewTurn, nextTurn: ?InterviewTurn, session: InterviewSession}
     */
    public function submitAnswer(InterviewSession $session, string $turnId, string $userAnswer): array
    {
        /** @var InterviewTurn|null $turn */
        $turn = InterviewTurn::where('interview_session_id', $session->id)
            ->where('id', $turnId)
            ->first();

        if (! $turn) {
            throw new ApiException(
                errorCode: 'NOT_FOUND',
                message: 'Không tìm thấy lượt câu hỏi phỏng vấn.',
                statusCode: 404
            );
        }

        $prompt = PromptLoader::load('interview-grade', [
            'skill_name' => $turn->skill_slug,
            'question' => $turn->question,
            'reference_answer' => (string) ($turn->reference_answer ?? 'Trình bày đúng bản chất kỹ thuật và ví dụ thực tế.'),
            'user_answer' => $userAnswer,
        ]);

        // Chấm điểm khách quan với nhiệt độ 0 và bật chế độ suy luận (thinking mode)
        $grade = $this->llm->generateObject(
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'score' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    'criteria' => [
                        'type' => 'object',
                        'properties' => [
                            'correctness' => ['type' => 'number', 'minimum' => 1, 'maximum' => 5],
                            'depth' => ['type' => 'number', 'minimum' => 1, 'maximum' => 5],
                            'clarity' => ['type' => 'number', 'minimum' => 1, 'maximum' => 5],
                        ],
                        'required' => ['correctness', 'depth', 'clarity'],
                    ],
                    'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'missing_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'sample_answer' => ['type' => 'string'],
                ],
                'required' => ['score', 'criteria', 'strengths', 'missing_points', 'sample_answer'],
            ],
            validator: function (array $data): array {
                $score = (int) ($data['score'] ?? 0);
                if ($score < 1 || $score > 5) {
                    throw new \InvalidArgumentException('Điểm score phải là số nguyên từ 1 đến 5.');
                }
                foreach (['correctness', 'depth', 'clarity'] as $c) {
                    $val = (float) ($data['criteria'][$c] ?? 0);
                    if ($val < 1 || $val > 5) {
                        throw new \InvalidArgumentException("Tiêu chí {$c} phải nằm trong khoảng 1..5.");
                    }
                }

                return [
                    'score' => $score,
                    'criteria' => [
                        'correctness' => (int) round((float) $data['criteria']['correctness']),
                        'depth' => (int) round((float) $data['criteria']['depth']),
                        'clarity' => (int) round((float) $data['criteria']['clarity']),
                    ],
                    'strengths' => array_slice(array_map('strval', (array) ($data['strengths'] ?? [])), 0, 3),
                    'missing_points' => array_slice(array_map('strval', (array) ($data['missing_points'] ?? [])), 0, 3),
                    'sample_answer' => (string) ($data['sample_answer'] ?? ''),
                ];
            },
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ],
            opts: [
                'temperature' => 0.0,
                'thinking' => true,
            ]
        );

        $turn->update([
            'user_answer' => $userAnswer,
            'score' => $grade['score'],
            'grade' => $grade,
        ]);

        // Kiểm tra xem đã đủ số lượng câu hỏi của phiên chưa
        $answeredCount = InterviewTurn::where('interview_session_id', $session->id)
            ->whereNotNull('score')
            ->count();

        $nextTurn = null;
        if ($answeredCount < $session->question_count) {
            $nextTurn = $this->generateNextTurn($session, $turn);
        } else {
            $avgScore = (float) InterviewTurn::where('interview_session_id', $session->id)
                ->whereNotNull('score')
                ->avg('score');

            $session->update([
                'status' => 'completed',
                'average_score' => round($avgScore, 2),
                'summary' => sprintf(
                    'Hoàn thành %d/%d câu hỏi phỏng vấn. Điểm trung bình đạt %.2f/5.00.',
                    $answeredCount,
                    $session->question_count,
                    $avgScore
                ),
            ]);
        }

        return [
            'turn' => $turn->fresh(),
            'nextTurn' => $nextTurn,
            'session' => $session->fresh(['turns']),
        ];
    }

    /**
     * Sinh câu hỏi tiếp theo dựa trên kết quả của lượt trước đó (Adaptive Difficulty & Skill Selection).
     */
    public function generateNextTurn(InterviewSession $session, ?InterviewTurn $previousTurn): InterviewTurn
    {
        $report = $session->gapReport;
        $gaps = is_array($report?->gaps) ? $report->gaps : [];

        // Ưu tiên các gap có priority === 'required'
        usort($gaps, fn ($a, $b) => (($a['priority'] ?? '') === 'required' ? 0 : 1) <=> (($b['priority'] ?? '') === 'required' ? 0 : 1));

        $turnNumber = $previousTurn ? ($previousTurn->turn_number + 1) : 1;
        $types = is_array($session->types) && ! empty($session->types)
            ? $session->types
            : ['concept', 'scenario', 'behavioral', 'coding_discussion'];
        $questionType = $types[($turnNumber - 1) % count($types)];

        // Xác định kỹ năng và độ khó dựa trên điểm của câu trả lời liền trước
        $difficulty = 'intermediate';
        $selectedGap = $gaps[($turnNumber - 1) % max(1, count($gaps))] ?? [
            'skill' => 'software-engineering',
            'name' => 'Kỹ thuật phần mềm',
            'jdEvidence' => 'Yêu cầu nắm vững kiến thức phát triển phần mềm.',
        ];

        if ($previousTurn !== null && $previousTurn->score !== null) {
            if ($previousTurn->score <= 2) {
                // Điểm <= 2: Giữ CÙNG kỹ năng nhưng giảm độ khó
                $skillSlug = $previousTurn->skill_slug;
                $matchedGap = collect($gaps)->firstWhere('skill', $skillSlug);
                if ($matchedGap) {
                    $selectedGap = $matchedGap;
                } else {
                    $selectedGap = [
                        'skill' => $skillSlug,
                        'name' => strtoupper($skillSlug),
                        'jdEvidence' => '',
                    ];
                }
                $difficulty = $this->easierDifficulty($previousTurn->difficulty);
            } elseif ($previousTurn->score >= 4) {
                // Điểm >= 4: Tăng độ khó ở câu hỏi tiếp theo
                $difficulty = $this->harderDifficulty($previousTurn->difficulty);
            } else {
                $difficulty = $previousTurn->difficulty;
            }
        }

        $skillSlug = (string) ($selectedGap['skill'] ?? 'software-engineering');
        $skillName = (string) ($selectedGap['name'] ?? $skillSlug);
        $jdEvidence = (string) ($selectedGap['jdEvidence'] ?? '');

        $prompt = PromptLoader::load('interview-question', [
            'skill_name' => $skillName,
            'skill_slug' => $skillSlug,
            'question_type' => $questionType,
            'difficulty' => $difficulty,
            'jd_evidence' => $jdEvidence,
        ]);

        // Model sinh câu hỏi kèm đáp án tham chiếu (reference_answer) trước khi ứng viên trả lời
        $generated = $this->llm->generateObject(
            jsonSchema: [
                'type' => 'object',
                'properties' => [
                    'question' => ['type' => 'string'],
                    'reference_answer' => ['type' => 'string'],
                ],
                'required' => ['question', 'reference_answer'],
            ],
            validator: function (array $data): array {
                if (empty($data['question']) || ! is_string($data['question'])) {
                    throw new \InvalidArgumentException('Thiếu nội dung câu hỏi question.');
                }

                return [
                    'question' => trim($data['question']),
                    'reference_answer' => trim((string) ($data['reference_answer'] ?? '')),
                ];
            },
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ]
        );

        return InterviewTurn::create([
            'interview_session_id' => $session->id,
            'turn_number' => $turnNumber,
            'skill_slug' => $skillSlug,
            'question_type' => $questionType,
            'difficulty' => $difficulty,
            'question' => $generated['question'],
            'reference_answer' => $generated['reference_answer'],
        ]);
    }

    private function harderDifficulty(string $current): string
    {
        return match ($current) {
            'easy' => 'intermediate',
            'intermediate' => 'hard',
            default => 'hard',
        };
    }

    private function easierDifficulty(string $current): string
    {
        return match ($current) {
            'hard' => 'intermediate',
            'intermediate' => 'easy',
            default => 'easy',
        };
    }
}
