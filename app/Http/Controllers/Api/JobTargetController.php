<?php

// SPDX-License-Identifier: Apache-2.0
// Controller quản lý danh sách Role mẫu (GET /api/v1/roles) và tạo JobTarget (POST /api/v1/job-targets)

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessJobTargetJob;
use App\Models\JobTarget;
use App\Models\JobTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;

class JobTargetController extends Controller
{
    /**
     * GET /api/v1/roles
     * Đọc danh sách 5 vị trí mẫu từ data/roles/*.yaml.
     */
    public function roles(): JsonResponse
    {
        $roles = [];
        foreach (glob(base_path('data/roles/*.yaml')) ?: [] as $file) {
            $parsed = Yaml::parseFile($file);
            if (is_array($parsed) && isset($parsed['slug'])) {
                $roles[] = [
                    'slug' => $parsed['slug'],
                    'title' => $parsed['title'] ?? $parsed['slug'],
                    'description' => $parsed['description'] ?? '',
                    'skills' => $parsed['skills'] ?? [],
                ];
            }
        }

        return response()->json($roles);
    }

    /**
     * POST /api/v1/job-targets
     * Nhận `{ roleSlug }` hoặc `{ title, jdText }` và đẩy vào hàng đợi xử lý. Trả về 202 `{ jobTargetId, jobId }`.
     */
    public function store(Request $request): JsonResponse
    {
        $roleSlug = $request->input('roleSlug');
        $title = (string) $request->input('title', '');
        $jdText = (string) $request->input('jdText', '');

        if (! empty($roleSlug)) {
            $roleFile = base_path("data/roles/{$roleSlug}.yaml");
            if (! is_file($roleFile)) {
                throw new ApiException('VALIDATION_ERROR', "Không tìm thấy vị trí mẫu (roleSlug): {$roleSlug}", 422);
            }
            $roleData = Yaml::parseFile($roleFile);
            $title = (string) ($roleData['title'] ?? $roleSlug);
            $lines = array_map(
                fn ($s) => "- {$s['slug']} ({$s['level']}, {$s['importance']}): ".($s['evidence'] ?? ''),
                $roleData['skills'] ?? []
            );
            $jdText = ($roleData['description'] ?? '')."\n".implode("\n", $lines);
        } elseif (trim($jdText) === '') {
            throw new ApiException(
                'VALIDATION_ERROR',
                'Vui lòng chọn một vị trí mẫu (`roleSlug`) hoặc nhập nội dung mô tả công việc (`jdText`).',
                422
            );
        }

        if ($title === '') {
            $title = 'Vị trí tuyển dụng mục tiêu';
        }

        $jobTarget = JobTarget::create([
            'title' => $title,
            'role_slug' => $roleSlug,
            'jd_text' => $jdText,
            'status' => 'processing',
        ]);

        $tracking = JobTracking::create([
            'type' => 'jd_extract',
            'state' => 'queued',
            'progress' => 5,
            'entity_id' => $jobTarget->id,
        ]);

        ProcessJobTargetJob::dispatch($jobTarget->id, $tracking->id);

        return response()->json([
            'jobTargetId' => $jobTarget->id,
            'jobId' => $tracking->id,
        ], 202);
    }
}
