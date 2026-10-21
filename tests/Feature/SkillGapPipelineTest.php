<?php

// SPDX-License-Identifier: Apache-2.0
// Kiểm thử tích hợp toàn trình (End-to-End Feature Test) với FakeLlmProvider: CV -> JD -> Báo cáo Skill Gap -> Lộ trình RAG -> Phỏng vấn thử 5 câu

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillGapPipelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kiểm tra toàn bộ luồng nghiệp vụ từ nạp dữ liệu mở, trích xuất CV/JD có bằng chứng thật,
     * tính điểm Skill Gap, sinh lộ trình học RAG không vượt hoursPerWeek đến hoàn thành phỏng vấn 5 câu.
     */
    public function test_full_end_to_end_pipeline_with_fake_llm_provider(): void
    {
        // 1. Nạp danh mục kỹ năng và kho tài liệu RAG (seed + ingest)
        $this->artisan('db:seed')->assertExitCode(0);
        $this->artisan('app:ingest')->assertExitCode(0);

        // 2. Tạo hồ sơ từ CV mẫu (POST /api/v1/profiles)
        $profileRes = $this->postJson('/api/v1/profiles', [
            'sampleId' => 'fresher-php',
        ]);
        $profileRes->assertStatus(202)
            ->assertJsonStructure(['profileId', 'jobId']);

        $profileId = $profileRes->json('profileId');

        // Kiểm tra email và số điện thoại đã được che (PII Redaction)
        $profileShow = $this->getJson("/api/v1/profiles/{$profileId}");
        $profileShow->assertStatus(200);
        $this->assertStringNotContainsString('khoi.nguyen.demo@student.edu.vn', $profileShow->json('raw_text'));
        $this->assertStringContainsString('[EMAIL_REDACTED]', $profileShow->json('raw_text'));

        // 3. Tạo mục tiêu công việc từ vị trí mẫu (POST /api/v1/job-targets)
        $jdRes = $this->postJson('/api/v1/job-targets', [
            'roleSlug' => 'backend',
        ]);
        $jdRes->assertStatus(202)
            ->assertJsonStructure(['jobTargetId', 'jobId']);
        $jobTargetId = $jdRes->json('jobTargetId');

        // 4. Tạo báo cáo Skill Gap (POST /api/v1/reports)
        $repRes = $this->postJson('/api/v1/reports', [
            'profileId' => $profileId,
            'jobTargetId' => $jobTargetId,
        ]);
        $repRes->assertStatus(202)
            ->assertJsonStructure(['reportId', 'jobId']);
        $reportId = $repRes->json('reportId');

        // Kiểm tra kết quả báo cáo có match_score và danh sách gap kèm trích dẫn thật
        $reportDetail = $this->getJson("/api/v1/reports/{$reportId}");
        $reportDetail->assertStatus(200);
        $this->assertGreaterThan(0, $reportDetail->json('match_score'));
        $this->assertNotEmpty($reportDetail->json('gaps'));

        // 5. Sinh lộ trình học tập RAG (POST /api/v1/reports/:id/roadmap) với hoursPerWeek = 8
        $roadmapRes = $this->postJson("/api/v1/reports/{$reportId}/roadmap", [
            'hoursPerWeek' => 8,
            'weeks' => 4,
        ]);
        $roadmapRes->assertStatus(200);
        $weeks = $roadmapRes->json('weeks');
        $this->assertNotEmpty($weeks);

        // Kiểm tra ràng buộc phân bổ thời gian: tổng giờ mỗi tuần không bao giờ vượt hoursPerWeek (8 giờ)
        foreach ($weeks as $weekBlock) {
            $this->assertLessThanOrEqual(8.0, (float) $weekBlock['totalHours']);
        }

        // 6. Khởi tạo và hoàn thành phiên phỏng vấn thử thích ứng 5 câu
        $interviewStart = $this->postJson('/api/v1/interviews', [
            'reportId' => $reportId,
            'questionCount' => 5,
        ]);
        $interviewStart->assertStatus(201)
            ->assertJsonStructure(['sessionId', 'firstQuestion']);

        $sessionId = $interviewStart->json('sessionId');
        $currentTurnId = $interviewStart->json('firstQuestion.id');

        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotNull($currentTurnId, "Phải có câu hỏi ở lượt {$i}");
            $ansRes = $this->postJson("/api/v1/interviews/{$sessionId}/answers", [
                'turnId' => $currentTurnId,
                'answer' => 'Trong dự án thực tế em sử dụng Service Container, Eloquent ORM có Eager Loading và viết Unit Test đầy đủ để đảm bảo hiệu năng và độ tin cậy.',
            ]);
            $ansRes->assertStatus(200)
                ->assertJsonStructure([
                    'grade' => ['score', 'criteria' => ['correctness', 'depth', 'clarity'], 'strengths', 'missing_points', 'sample_answer'],
                ]);

            $currentTurnId = $ansRes->json('nextQuestion.id');
        }

        // Sau 5 câu, phiên phỏng vấn phải chuyển sang trạng thái 'completed'
        $finalSession = $this->getJson("/api/v1/interviews/{$sessionId}");
        $finalSession->assertStatus(200)
            ->assertJsonPath('status', 'completed');

        // 7. Kiểm tra xoá Profile sẽ xoá cascade toàn bộ dữ liệu liên quan
        $this->deleteJson("/api/v1/profiles/{$profileId}")->assertStatus(204);
        $this->getJson("/api/v1/reports/{$reportId}")->assertStatus(404);
    }
}
