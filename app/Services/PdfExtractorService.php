<?php

// SPDX-License-Identifier: Apache-2.0
// Dịch vụ kiểm tra chữ ký tệp PDF, trích xuất văn bản bằng smalot/pdfparser, che thông tin cá nhân (PII) và xoá tệp tạm

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfExtractorService
{
    /**
     * Kiểm tra magic bytes, dung lượng file PDF, trích xuất văn bản, ẩn PII và xoá ngay file tạm.
     */
    public function extractAndSanitizeFromUpload(UploadedFile $file): string
    {
        $maxBytes = ((int) config('ai.max_upload_mb', 5)) * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new ApiException(
                errorCode: 'VALIDATION_ERROR',
                message: 'Dung lượng file PDF vượt quá giới hạn cho phép ('.config('ai.max_upload_mb', 5).' MB).',
                statusCode: 422
            );
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! is_file($realPath)) {
            throw new ApiException(
                errorCode: 'CV_PARSE_FAILED',
                message: 'Không thể đọc tệp tải lên.',
                statusCode: 400
            );
        }

        try {
            // Kiểm tra chữ ký nhị phân (magic bytes %PDF-) ở đầu tệp để chống giả mạo phần mở rộng
            $handle = fopen($realPath, 'rb');
            $header = $handle ? fread($handle, 5) : '';
            if ($handle) {
                fclose($handle);
            }

            if ($header !== '%PDF-') {
                throw new ApiException(
                    errorCode: 'CV_PARSE_FAILED',
                    message: 'Tệp tải lên không đúng định dạng PDF chuẩn (sai magic bytes %PDF-).',
                    statusCode: 400
                );
            }

            $parser = new Parser;
            $pdf = $parser->parseFile($realPath);
            $rawText = trim($pdf->getText());

            if ($rawText === '') {
                throw new ApiException(
                    errorCode: 'CV_PARSE_FAILED',
                    message: 'Không trích xuất được văn bản từ file PDF (có thể là file ảnh scan).',
                    statusCode: 400
                );
            }

            return $this->redactPii($rawText);
        } catch (ApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ApiException(
                errorCode: 'CV_PARSE_FAILED',
                message: 'Lỗi khi phân tích cú pháp tệp PDF: '.$e->getMessage(),
                statusCode: 400
            );
        } finally {
            // Xoá file gốc ngay sau khi trích xuất để bảo mật dữ liệu ứng viên
            if ($realPath && is_file($realPath)) {
                @unlink($realPath);
            }
        }
    }

    /**
     * Ẩn thông tin định danh cá nhân (Email và Số điện thoại) trước khi lưu DB hoặc gửi cho LLM.
     */
    public function redactPii(string $text): string
    {
        // Ẩn địa chỉ email
        $redacted = (string) preg_replace(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
            '[EMAIL_REDACTED]',
            $text
        );

        // Ẩn số điện thoại Việt Nam và quốc tế (ví dụ: +84 912 345 678, 0987-654-321, 0901234567)
        $redacted = (string) preg_replace(
            '/(?<!\d)(?:\+?84|0)(?:[\s.\-]?\d){8,10}(?!\d)/u',
            '[PHONE_REDACTED]',
            $redacted
        );

        return trim($redacted);
    }
}
