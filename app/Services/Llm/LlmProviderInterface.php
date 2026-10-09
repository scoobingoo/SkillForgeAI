<?php

// SPDX-License-Identifier: Apache-2.0
// Giao diện lớp trừu tượng LlmProvider tự chủ cho hệ thống SkillForge AI (không phụ thuộc LangChain/LlamaIndex)

namespace App\Services\Llm;

interface LlmProviderInterface
{
    /**
     * Gọi LLM sinh phản hồi dạng văn bản từ mảng tin nhắn.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $opts  Tuỳ chọn: temperature, thinking (bật/tắt chế độ suy nghĩ của Qwen3)
     */
    public function chat(array $messages, array $opts = []): string;

    /**
     * Sinh đối tượng có cấu trúc tuân thủ JSON Schema, tự động thử lại tối đa 2 lần kèm thông báo lỗi nếu parse thất bại.
     *
     * @param  array<string, mixed>  $jsonSchema  Lược đồ JSON Schema yêu cầu
     * @param  callable(array<string, mixed>): array<string, mixed>  $validator  Hàm kiểm tra và chuẩn hoá dữ liệu trả về (ném ngoại lệ nếu sai)
     * @param  array<int, array{role: string, content: string}>  $messages  Danh sách tin nhắn prompt
     * @param  array<string, mixed>  $opts  Tuỳ chọn: temperature, thinking
     * @return array<string, mixed>
     */
    public function generateObject(array $jsonSchema, callable $validator, array $messages, array $opts = []): array;

    /**
     * Sinh vector embedding (1024 chiều với bge-m3) cho danh sách văn bản.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts): array;

    /**
     * Kiểm tra trạng thái sẵn sàng của Ollama và các model đã tải xong chưa (phục vụ GET /api/v1/health).
     *
     * @return array{ok: bool, models_ready: bool, available_models: array<int, string>, message?: string}
     */
    public function checkHealth(): array;
}
