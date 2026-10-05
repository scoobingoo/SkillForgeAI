<?php

// SPDX-License-Identifier: Apache-2.0
// Lớp ngoại lệ chuẩn hoá cấu trúc phản hồi lỗi JSON cho toàn bộ REST API

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    /**
     * Khởi tạo ngoại lệ API với mã lỗi chuẩn, thông báo và chi tiết tuỳ chọn.
     *
     * @param  string  $errorCode  Mã lỗi (VALIDATION_ERROR, NOT_FOUND, CV_PARSE_FAILED, LLM_UNAVAILABLE, LLM_INVALID_OUTPUT)
     * @param  string  $message  Thông báo lỗi hiển thị
     * @param  int  $statusCode  HTTP status code
     * @param  array<string, mixed>|null  $details  Chi tiết bổ sung
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 400,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Chuyển đổi ngoại lệ thành JSON response theo định dạng:
     * { "error": { "code", "message", "details?" } }
     */
    public function toResponse(): JsonResponse
    {
        $payload = [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];

        if ($this->details !== null) {
            $payload['details'] = $this->details;
        }

        return response()->json(['error' => $payload], $this->statusCode);
    }
}
