<?php

// SPDX-License-Identifier: Apache-2.0
// Controller quản lý Báo cáo Skill Gap (/api/v1/reports) và Lộ trình học tập (/api/v1/reports/:id/roadmap, /api/v1/roadmaps/:id/progress)

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateGapReportJob;
use App\Models\GapReport;
use App\Models\JobTarget;
use App\Models\JobTracking;
use App\Models\Profile;
use App\Models\Roadmap;
use App\Services\RagRetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * POST /api/v1/reports
     * Nhận `{ profileId, jobTargetId }`, tạo báo cáo và đẩy job tính điểm. Trả về 202 `{ reportId, jobId }`.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profileId' => ['required', 'string'],
            'jobTargetId' => ['required', 'string'],
        ]);

        $profile = Profile::find($validated['profileId']);
        $jobTarget = JobTarget::find($validated['jobTargetId']);

        if (! $profile || ! $jobTarget) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy hồ sơ CV hoặc vị trí tuyển dụng mục tiêu.', 404);
        }

        $report = GapReport::create([
            'profile_id' => $profile->id,
            'job_target_id' => $jobTarget->id,
            'match_score' => 0,
            'category_scores' => [],
            'matched_skills' => [],
            'gaps' => [],
            'status' => 'processing',
        ]);

        $tracking = JobTracking::create([
            'type' => 'gap_report',
            'state' => 'queued',
            'progress' => 10,
            'entity_id' => $report->id,
        ]);

        GenerateGapReportJob::dispatch($report->id, $tracking->id);

        return response()->json([
            'reportId' => $report->id,
            'jobId' => $tracking->id,
        ], 202);
    }

    /**
     * GET /api/v1/reports/:id
     */
    public function show(string $id): JsonResponse
    {
        $report = GapReport::with(['profile.skills', 'jobTarget.skills', 'roadmap'])->find($id);
        if (! $report) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy báo cáo khoảng cách kỹ năng.', 404);
        }

        return response()->json($report);
    }

    /**
     * POST /api/v1/reports/:id/roadmap
     * Hỗ trợ cả luồng SSE (`token`, `result`, `done`) lẫn phản hồi JSON trực tiếp.
     */
    public function generateRoadmap(Request $request, string $id, RagRetrievalService $rag): StreamedResponse|JsonResponse
    {
        $report = GapReport::find($id);
        if (! $report) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy báo cáo khoảng cách kỹ năng.', 404);
        }

        $hoursPerWeek = (int) $request->input('hoursPerWeek', 10);
        $weeksCount = (int) $request->input('weeks', 4);

        $roadmap = $rag->generateRoadmap($report, $hoursPerWeek, $weeksCount);

        // Nếu client yêu cầu text/event-stream (SSE), phát sự kiện token -> result -> done theo thời gian thực
        if (str_contains((string) $request->header('Accept'), 'text/event-stream') || $request->boolean('stream')) {
            return response()->stream(function () use ($roadmap) {
                echo "event: token\n";
                echo 'data: '.json_encode(['text' => 'Đang truy xuất Hybrid Search (Vector + Full-Text RRF k=60) và phân bổ giờ học...'], JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();

                echo "event: result\n";
                echo 'data: '.json_encode($roadmap->toArray(), JSON_UNESCAPED_UNICODE)."\n\n";
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
                
            ]);
        }

        return response()->json($roadmap);
    }

    /**
     * PATCH /api/v1/roadmaps/:id/progress
     * Cập nhật trạng thái hoàn thành (checkbox) của từng mục học tập trong lộ trình.
     */
    public function updateProgress(Request $request, string $id): JsonResponse
    {
        $roadmap = Roadmap::find($id);
        if (! $roadmap) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy lộ trình học tập.', 404);
        }

        $validated = $request->validate([
            'week' => ['required', 'integer', 'min:1'],
            'resourceSlug' => ['required', 'string'],
            'completed' => ['required', 'boolean'],
        ]);

        $weeks = is_array($roadmap->weeks) ? $roadmap->weeks : [];
        foreach ($weeks as &$weekBlock) {
            if ((int) ($weekBlock['week'] ?? 0) === (int) $validated['week']) {
                foreach ($weekBlock['items'] as &$item) {
                    if (($item['resourceSlug'] ?? '') === $validated['resourceSlug']) {
                        $item['completed'] = (bool) $validated['completed'];
                    }
                }
            }
        }

        $roadmap->update(['weeks' => $weeks]);

        return response()->json($roadmap->fresh());
    }
}
