<?php

// SPDX-License-Identifier: Apache-2.0
// Unit Test cho LlmProvider: kiểm tra cơ chế retry tối đa 2 lần khi JSON parse lỗi và ném ngoại lệ LLM_INVALID_OUTPUT

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Services\Llm\FakeLlmProvider;
use PHPUnit\Framework\TestCase;

class LlmProviderTest extends TestCase
{
    /**
     * Kiểm tra generateObject tự động thử lại khi lần đầu trả JSON lỗi và thành công ở lần thử thứ 2.
     */
    public function test_generate_object_retries_on_invalid_json_and_succeeds(): void
    {
        $provider = new FakeLlmProvider;
        $provider->queueRawResponses([
            'đây không phải là chuỗi JSON hợp lệ {',
            '{"selected_slug": "laravel"}',
        ]);

        $result = $provider->generateObject(
            jsonSchema: ['type' => 'object'],
            validator: function (array $data): array {
                if (empty($data['selected_slug'])) {
                    throw new \InvalidArgumentException('Thiếu selected_slug');
                }

                return $data;
            },
            messages: [['role' => 'user', 'content' => 'Test retry']]
        );

        $this->assertSame('laravel', $result['selected_slug']);
    }

    /**
     * Kiểm tra generateObject ném ngoại lệ LLM_INVALID_OUTPUT sau khi thất bại cả 3 lần thử (1 lần gốc + 2 lần retry).
     */
    public function test_generate_object_throws_llm_invalid_output_after_max_retries(): void
    {
        $provider = new FakeLlmProvider;
        $provider->queueRawResponses([
            'invalid json 1',
            'invalid json 2',
            'invalid json 3',
        ]);

        $this->expectException(ApiException::class);

        try {
            $provider->generateObject(
                jsonSchema: ['type' => 'object'],
                validator: fn (array $d) => $d,
                messages: [['role' => 'user', 'content' => 'Test fail all retries']]
            );
        } catch (ApiException $e) {
            $this->assertSame('LLM_INVALID_OUTPUT', $e->errorCode);
            throw $e;
        }
    }
}
