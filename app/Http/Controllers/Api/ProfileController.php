<?php

// SPDX-License-Identifier: Apache-2.0
// Controller quản lý hồ sơ ứng viên (/api/v1/profiles): tải lên PDF hoặc chọn CV mẫu, chỉnh sửa kỹ năng tay và xoá dữ liệu

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessProfileJob;
use App\Models\JobTracking;
use App\Models\Profile;
use App\Models\ProfileSkill;
use App\Models\Skill;
use App\Services\PdfExtractorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    /**
     * POST /api/v1/profiles
     * Nhận multipart `file` (PDF) hoặc `{ sampleId }`, ẩn PII và đẩy vào hàng đợi xử lý. Trả về 202 { profileId, jobId }.
     */
    public function store(Request $request, PdfExtractorService $pdfExtractor): JsonResponse
    {
        $sampleMap = [
            'fresher-php' => [
                'title' => 'CV Mẫu: Nguyễn Minh Khôi (Fresher PHP / Laravel)',
                'path' => base_path('data/samples/cv-fresher-php.txt'),
            ],
            'frontend-student' => [
                'title' => 'CV Mẫu: Trần Thị Mai Anh (Frontend Web Student)',
                'path' => base_path('data/samples/cv-frontend-student.txt'),
            ],
            'data-student' => [
                'title' => 'CV Mẫu: Lê Hoàng Nam (Data Analyst Intern)',
                'path' => base_path('data/samples/cv-data-student.txt'),
            ],
        ];

        $sampleId = $request->input('sampleId');
        $rawText = '';
        $title = 'Hồ sơ CV tải lên';
        $sourceType = 'upload';

        if (! empty($sampleId)) {
            if (! isset($sampleMap[$sampleId]) || ! is_file($sampleMap[$sampleId]['path'])) {
                throw new ApiException('VALIDATION_ERROR', 'Mã CV mẫu (sampleId) không hợp lệ.', 422);
            }
            $sourceType = 'sample';
            $title = $sampleMap[$sampleId]['title'];
            $rawText = $pdfExtractor->redactPii((string) file_get_contents($sampleMap[$sampleId]['path']));
        } elseif ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $title = 'CV: '.pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME);
            $rawText = $pdfExtractor->extractAndSanitizeFromUpload($uploaded);
        } elseif ($request->filled('cvText')) {
            $title = (string) $request->input('title', 'Hồ sơ CV');
            $rawText = $pdfExtractor->redactPii((string) $request->input('cvText'));
        } else {
            throw new ApiException(
                'VALIDATION_ERROR',
                'Vui lòng tải lên tệp PDF (`file`) hoặc chọn một hồ sơ mẫu (`sampleId`).',
                422
            );
        }

        $profile = Profile::create([
            'title' => $title,
            'source_type' => $sourceType,
            'sample_id' => $sampleId,
            'raw_text' => $rawText,
            'status' => 'processing',
        ]);

        $tracking = JobTracking::create([
            'type' => 'profile_extract',
            'state' => 'queued',
            'progress' => 5,
            'entity_id' => $profile->id,
        ]);

        ProcessProfileJob::dispatch($profile->id, $tracking->id);

        return response()->json([
            'profileId' => $profile->id,
            'jobId' => $tracking->id,
        ], 202);
    }

    /**
     * GET /api/v1/profiles/:id
     */
    public function show(string $id): JsonResponse
    {
        $profile = Profile::with('skills')->find($id);
        if (! $profile) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy hồ sơ ứng viên.', 404);
        }

        return response()->json($profile);
    }

    /**
     * PATCH /api/v1/profiles/:id/skills
     * Cho phép người dùng chỉnh sửa, thêm hoặc xoá kỹ năng thủ công sau khi AI trích xuất.
     */
    public function updateSkills(Request $request, string $id): JsonResponse
    {
        $profile = Profile::find($id);
        if (! $profile) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy hồ sơ ứng viên.', 404);
        }

        $validated = $request->validate([
            'skills' => ['required', 'array'],
            'skills.*.raw_name' => ['required', 'string', 'max:160'],
            'skills.*.canonical_slug' => ['nullable', 'string', 'max:120'],
            'skills.*.level' => ['required', 'string', 'in:beginner,intermediate,advanced'],
            'skills.*.years' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'skills.*.evidence' => ['nullable', 'string'],
        ]);

        $allSkills = Skill::all();
        ProfileSkill::where('profile_id', $profile->id)->delete();

        foreach ($validated['skills'] as $item) {
            $slug = ! empty($item['canonical_slug'])
                ? strtolower($item['canonical_slug'])
                : strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $item['raw_name']));

            $matchedTaxonomy = $allSkills->firstWhere('slug', $slug);

            ProfileSkill::create([
                'profile_id' => $profile->id,
                'skill_id' => $matchedTaxonomy?->id,
                'raw_name' => $item['raw_name'],
                'canonical_slug' => $matchedTaxonomy?->slug ?? $slug,
                'category' => $matchedTaxonomy?->category ?? 'general',
                'level' => $item['level'],
                'years' => isset($item['years']) ? (float) $item['years'] : null,
                'evidence' => ! empty($item['evidence']) ? $item['evidence'] : 'Cập nhật thủ công bởi người dùng.',
                'mapping' => $matchedTaxonomy ? 'alias' : 'unmapped',
            ]);
        }

        return response()->json($profile->fresh('skills'));
    }

    /**
     * DELETE /api/v1/profiles/:id
     * Xoá hồ sơ ứng viên và cascade xoá toàn bộ báo cáo, lộ trình, lịch sử phỏng vấn liên quan.
     */
    public function destroy(string $id): Response
    {
        $profile = Profile::find($id);
        if (! $profile) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy hồ sơ ứng viên.', 404);
        }

        $profile->delete();

        return response()->noContent();
    }
}
