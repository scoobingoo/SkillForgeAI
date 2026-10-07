<?php

// SPDX-License-Identifier: Apache-2.0
// Triển khai OpenAiCompatibleProvider kết nối Ollama (qwen3:4b, bge-m3) hoặc bất kỳ OpenAI-compatible API nào

namespace App\Services\Llm;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiCompatibleProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $chatModel,
        private readonly string $embedModel,
        private readonly int $concurrency = 2,
        private readonly int $timeoutSeconds = 120,
    ) {}

    /**
     * Gọi endpoint /chat/completions tương thích chuẩn OpenAI của Ollama.
     */
    public function chat(array $messages, array $opts = []): string
    {
        $thinking = (bool) ($opts['thinking'] ?? false);
        $temperature = (float) ($opts['temperature'] ?? 0.2);

        // Mặc định tắt chế độ thinking của Qwen3 cho tác vụ trích xuất nhanh, chỉ bật khi chấm điểm phỏng vấn
        $preparedMessages = $this->applyThinkingDirective($messages, $thinking);

        $payload = [
            'model' => $this->chatModel,
            'messages' => $preparedMessages,
            'temperature' => $temperature,
            'stream' => false,
        ];

        if (isset($opts['response_format'])) {
            $payload['response_format'] = $opts['response_format'];
        }
        if (isset($opts['format'])) {
            $payload['format'] = $opts['format'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeoutSeconds)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);
        } catch (Throwable $e) {
            throw new ApiException(
                errorCode: 'LLM_UNAVAILABLE',
                message: 'Không thể kết nối tới dịch vụ AI (Ollama): '.$e->getMessage(),
                statusCode: 503
            );
        }

        if (! $response->successful()) {
            throw new ApiException(
                errorCode: 'LLM_UNAVAILABLE',
                message: 'Dịch vụ AI trả về mã lỗi HTTP '.$response->status(),
                statusCode: 503
            );
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        // Loại bỏ thẻ <think>...</think> nếu model có sinh ra trong chế độ suy luận
        return trim((string) preg_replace('/<think>.*?<\/think>/s', '', $content));
    }

    /**
     * Sinh đối tượng JSON theo lược đồ, tự động thử lại tối đa 2 lần nếu lỗi JSON hoặc sai schema.
     */
    public function generateObject(array $jsonSchema, callable $validator, array $messages, array $opts = []): array
    {
        $maxRetries = 2;
        $workingMessages = $messages;
        $lastError = '';

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $rawOutput = $this->chat($workingMessages, array_merge($opts, [
                'format' => $jsonSchema,
                'response_format' => ['type' => 'json_object'],
                'temperature' => $opts['temperature'] ?? 0.0,
            ]));

            try {
                $cleanJson = $this->extractJsonBlock($rawOutput);
                $decoded = json_decode($cleanJson, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new \RuntimeException('Kết quả giải mã JSON không phải là một đối tượng/mảng.');
                }

                // Kiểm tra tính hợp lệ qua validator (tương đương Zod parse)
                return $validator($decoded);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                if ($attempt < $maxRetries) {
                    // Gửi lại kèm thông báo lỗi để model tự sửa ở lần thử tiếp theo
                    $workingMessages[] = [
                        'role' => 'assistant',
                        'content' => $rawOutput,
                    ];
                    $workingMessages[] = [
                        'role' => 'user',
                        'content' => "JSON bạn vừa trả về không hợp lệ hoặc vi phạm schema với lỗi: {$lastError}. Vui lòng chỉ trả về đúng JSON hợp lệ theo schema đã yêu cầu, không thêm văn bản giải thích.",
                    ];
                }
            }
        }

        throw new ApiException(
            errorCode: 'LLM_INVALID_OUTPUT',
            message: 'Model AI không trả về đúng định dạng JSON sau 3 lần thử: '.$lastError,
            statusCode: 502
        );
    }

    /**
     * Sinh vector embedding qua endpoint /embeddings (sử dụng model bge-m3 1024 chiều).
     */
    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $results = [];
        // Chia theo lô dựa trên cấu hình LLM_CONCURRENCY để tránh quá tải Ollama
        $chunks = array_chunk(array_values($texts), max(1, $this->concurrency * 4));

        foreach ($chunks as $batch) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout($this->timeoutSeconds)
                    ->acceptJson()
                    ->post(rtrim($this->baseUrl, '/').'/embeddings', [
                        'model' => $this->embedModel,
                        'input' => $batch,
                    ]);
            } catch (Throwable $e) {
                throw new ApiException(
                    errorCode: 'LLM_UNAVAILABLE',
                    message: 'Không thể tạo vector embedding từ Ollama: '.$e->getMessage(),
                    statusCode: 503
                );
            }

            if (! $response->successful()) {
                throw new ApiException(
                    errorCode: 'LLM_UNAVAILABLE',
                    message: 'Lỗi tạo vector embedding HTTP '.$response->status(),
                    statusCode: 503
                );
            }

            $data = $response->json('data', []);
            foreach ($data as $item) {
                $vec = $item['embedding'] ?? [];
                // Đảm bảo chuẩn kích thước 1024 chiều
                $results[] = array_map('floatval', array_pad(array_slice($vec, 0, 1024), 1024, 0.0));
            }
        }

        return $results;
    }

    /**
     * Kiểm tra kết nối tới Ollama và xác minh cả 2 model (chat & embedding) đã tải xong chưa.
     */
    public function checkHealth(): array
    {
        try {
            $ollamaRoot = preg_replace('#/v1/?$#', '', rtrim($this->baseUrl, '/'));
            $response = Http::timeout(5)->get($ollamaRoot.'/api/tags');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'models_ready' => false,
                    'available_models' => [],
                    'message' => 'Ollama trả về HTTP '.$response->status(),
                ];
            }

            $models = collect($response->json('models', []))
                ->pluck('name')
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all();

            $hasChat = $this->modelExistsInList($this->chatModel, $models);
            $hasEmbed = $this->modelExistsInList($this->embedModel, $models);

            return [
                'ok' => true,
                'models_ready' => $hasChat && $hasEmbed,
                'available_models' => $models,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'models_ready' => false,
                'available_models' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Thêm chỉ thị /no_think hoặc /think cho dòng model Qwen3.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function applyThinkingDirective(array $messages, bool $thinking): array
    {
        if (empty($messages)) {
            return $messages;
        }

        $directive = $thinking ? '/think' : '/no_think';
        $lastIndex = count($messages) - 1;
        if (! str_contains($messages[$lastIndex]['content'], $directive)) {
            $messages[$lastIndex]['content'] = $directive."\n".$messages[$lastIndex]['content'];
        }

        return $messages;
    }

    /**
     * Trích xuất phần JSON từ chuỗi phản hồi nếu model bọc trong khối markdown ```json ... ```.
     */
    private function extractJsonBlock(string $text): string
    {
        $trimmed = trim($text);
        if (preg_match('/```(?:json)?\s*(\{.*\}|\[.*\])\s*```/s', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        return $trimmed;
    }

    /**
     * Kiểm tra tên model có trong danh sách đã tải của Ollama hay không.
     *
     * @param  array<int, string>  $available
     */
    private function modelExistsInList(string $target, array $available): bool
    {
        $targetBase = strtolower(explode(':', $target)[0]);
        foreach ($available as $name) {
            $lower = strtolower($name);
            if ($lower === strtolower($target) || str_starts_with($lower, $targetBase.':')) {
                return true;
            }
        }

        return false;
    }
}
