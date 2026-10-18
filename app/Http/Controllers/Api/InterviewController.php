<?php

// SPDX-License-Identifier: Apache-2.0
// Controller quản lý Phỏng vấn thử thích ứng (/api/v1/interviews): tạo phiên, gửi câu trả lời chấm Rubric (SSE/JSON) và xem chi tiết

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\GapReport;
use App\Models\InterviewSession;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InterviewController extends Controller
{
    /**
     * POST /api/v1/interviews
     * Nhận `{ reportId, questionCount?, types?[] }` và trả về 201 `{ sessionId, firstQuestion }`.
     */
    public function store(Request $request, InterviewService $interviewService): JsonResponse
    {
        $validated = $request->validate([
            'reportId' => ['required', 'string'],
            'questionCount' => ['nullable', 'integer', 'min:1', 'max:10'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:concept,scenario,behavioral,coding_discussion'],
        ]);

        $report = GapReport::find($validated['reportId']);
        if (! $report) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy báo cáo khoảng cách kỹ năng.', 404);
        }

        $started = $interviewService->startSession(
            report: $report,
            questionCount: (int) ($validated['questionCount'] ?? 5),
            types: $validated['types'] ?? ['concept', 'scenario', 'behavioral', 'coding_discussion']
        );

        return response()->json([
            'sessionId' => $started['session']->id,
            'firstQuestion' => $started['firstTurn'],
            'session' => $started['session'],
        ], 201);
    }

    /**
     * POST /api/v1/interviews/:id/answers
     * Nhận `{ turnId, answer }`, chấm theo Rubric và sinh câu tiếp theo (hỗ trợ cả SSE lẫn JSON).
     */
    public function submitAnswer(Request $request, string $id, InterviewService $interviewService): StreamedResponse|JsonResponse
    {
        $session = InterviewSession::find($id);
        if (! $session) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy phiên phỏng vấn.', 404);
        }

        $validated = $request->validate([
            'turnId' => ['required', 'string'],
            'answer' => ['required', 'string', 'min:2'],
        ]);

        $result = $interviewService->submitAnswer(
            session: $session,
            turnId: $validated['turnId'],
            userAnswer: $validated['answer']
        );

        $payload = [
            'gradedTurn' => $result['turn'],
            'grade' => $result['turn']->grade,
            'nextQuestion' => $result['nextTurn'],
            'session' => $result['session'],
        ];

        if (str_contains((string) $request->header('Accept'), 'text/event-stream') || $request->boolean('stream')) {
            return response()->stream(function () use ($payload) {
                echo "event: token\n";
                echo 'data: '.json_encode(['text' => 'Đang đối chiếu câu trả lời với đáp án tham chiếu theo Rubric...'], JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();

                echo "event: result\n";
                echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();

                echo "event: done\n";
                echo "data: {}\n\n";
                @ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->json($payload);
    }

    /**
     * GET /api/v1/interviews/:id
     */
    public function show(string $id): JsonResponse
    {
        $session = InterviewSession::with(['turns', 'gapReport.profile', 'gapReport.jobTarget'])->find($id);
        if (! $session) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy phiên phỏng vấn.', 404);
        }

        return response()->json($session);
    }
}
