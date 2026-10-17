<?php

// SPDX-License-Identifier: Apache-2.0
// Controller kiểm tra trạng thái sẵn sàng của hệ thống và các model AI (GET /api/v1/health)

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Llm\LlmProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Trả về 200 khi DB và Ollama sẵn sàng, 503 khi chưa sẵn sàng, kèm cờ models_ready cho banner giao diện.
     */
    public function show(LlmProviderInterface $llm): JsonResponse
    {
        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (Throwable) {
            $dbOk = false;
        }

        $llmStatus = $llm->checkHealth();
        $allReady = $dbOk && $llmStatus['ok'] && $llmStatus['models_ready'];

        return response()->json([
            'status' => $allReady ? 'ok' : 'degraded',
            'database' => $dbOk ? 'up' : 'down',
            'ollama' => $llmStatus['ok'] ? 'up' : 'down',
            'modelsReady' => $llmStatus['models_ready'],
            'configuredModels' => [
                'chat' => config('ai.model'),
                'embedding' => config('ai.embed_model'),
            ],
            'availableModels' => $llmStatus['available_models'],
        ], $allReady ? 200 : 503);
    }
}
