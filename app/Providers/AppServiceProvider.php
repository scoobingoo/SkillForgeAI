<?php

// SPDX-License-Identifier: Apache-2.0
// Service Provider chính: kiểm tra cấu hình bắt buộc và đăng ký LlmProviderInterface

namespace App\Providers;

use App\Services\Llm\FakeLlmProvider;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\OpenAiCompatibleProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các dịch vụ vào container của ứng dụng.
     */
    public function register(): void
    {
        // Ràng buộc LlmProviderInterface tuỳ theo cấu hình môi trường (dùng FakeLlmProvider khi test/CI)
        $this->app->singleton(LlmProviderInterface::class, function ($app) {
            $provider = config('ai.provider', 'openai_compatible');
            if ($provider === 'fake' || $app->environment('testing')) {
                return new FakeLlmProvider;
            }

            return new OpenAiCompatibleProvider(
                baseUrl: (string) config('ai.base_url'),
                apiKey: (string) config('ai.api_key'),
                chatModel: (string) config('ai.model'),
                embedModel: (string) config('ai.embed_model'),
                concurrency: (int) config('ai.concurrency', 2),
                timeoutSeconds: (int) config('ai.timeout', 120),
            );
        });
    }

    /**
     * Khởi chạy dịch vụ và kiểm tra tính hợp lệ của các biến môi trường bắt buộc khi khởi động.
     */
    public function boot(): void
    {
        if (! $this->app->environment('testing')) {
            $validator = Validator::make([
                'LLM_BASE_URL' => config('ai.base_url'),
                'LLM_MODEL' => config('ai.model'),
                'EMBED_MODEL' => config('ai.embed_model'),
                'MAX_UPLOAD_MB' => config('ai.max_upload_mb'),
            ], [
                'LLM_BASE_URL' => ['required', 'string', 'url'],
                'LLM_MODEL' => ['required', 'string'],
                'EMBED_MODEL' => ['required', 'string'],
                'MAX_UPLOAD_MB' => ['required', 'numeric', 'min:1', 'max:50'],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException(
                    'Lỗi cấu hình môi trường bắt buộc: '.json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE)
                );
            }
        }
    }
}
