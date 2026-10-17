<?php

// SPDX-License-Identifier: Apache-2.0
// Controller tra cứu trạng thái tiến trình xử lý bất đồng bộ (GET /api/v1/jobs/:id)

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\JobTracking;
use Illuminate\Http\JsonResponse;

class JobStatusController extends Controller
{
    /**
     * Trả về { state, progress, error?, entityId } của một job bất đồng bộ.
     */
    public function show(string $id): JsonResponse
    {
        $job = JobTracking::find($id);
        if (! $job) {
            throw new ApiException('NOT_FOUND', 'Không tìm thấy tiến trình xử lý.', 404);
        }

        $payload = [
            'id' => $job->id,
            'type' => $job->type,
            'state' => $job->state,
            'progress' => $job->progress,
            'entityId' => $job->entity_id,
        ];

        if ($job->error !== null) {
            $payload['error'] = $job->error;
        }

        return response()->json($payload);
    }
}
