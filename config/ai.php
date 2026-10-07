<?php

// SPDX-License-Identifier: Apache-2.0
// Cấu hình biến môi trường AI, ngưỡng chuẩn hoá kỹ năng 3 tầng và giới hạn tải tệp

return [
    // Nhà cung cấp LLM: 'openai_compatible' (Ollama/OpenAI) hoặc 'fake' (dành cho kiểm thử & CI)
    'provider' => env('LLM_PROVIDER', 'openai_compatible'),

    // Endpoint OpenAI-compatible của Ollama
    'base_url' => env('LLM_BASE_URL', 'http://ollama:11434/v1'),

    // API key (mặc định là 'ollama' khi chạy local)
    'api_key' => env('LLM_API_KEY', 'ollama'),

    // Tên model sinh văn bản (chat) và tạo vector (embedding)
    'model' => env('LLM_MODEL', 'qwen3:4b'),
    'embed_model' => env('EMBED_MODEL', 'bge-m3'),
    'embed_dimensions' => (int) env('EMBED_DIMENSIONS', 1024),

    // Số lời gọi LLM đồng thời tối đa và thời gian chờ
    'concurrency' => (int) env('LLM_CONCURRENCY', 2),
    'timeout' => (int) env('LLM_TIMEOUT', 120),

    // Ngưỡng chuẩn hoá kỹ năng 3 tầng (Alias -> Vector Cosine -> LLM Top-5)
    'skill_vector_accept_threshold' => (float) env('SKILL_VECTOR_ACCEPT_THRESHOLD', 0.82),
    'skill_vector_llm_min_threshold' => (float) env('SKILL_VECTOR_LLM_MIN_THRESHOLD', 0.65),

    // Dung lượng file PDF tải lên tối đa (MB)
    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 5),
];
