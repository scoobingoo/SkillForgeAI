<?php

// SPDX-License-Identifier: Apache-2.0
// Cấu hình khởi tạo ứng dụng Laravel 11, định tuyến Web/API và bộ lọc ngoại lệ toàn cục (GlobalExceptionFilter)

use App\Exceptions\ApiException;
use App\Http\Middleware\RequestIdAndLocaleMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Áp dụng middleware RequestId & đa ngôn ngữ cho cả web và api
        $middleware->append(RequestIdAndLocaleMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Chuẩn hoá toàn bộ phản hồi lỗi cho các route API (/api/v1/*) theo chuẩn JSON đồng nhất
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $e->toResponse();
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Dữ liệu đầu vào không hợp lệ.',
                        'details' => $e->errors(),
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Không tìm thấy tài nguyên yêu cầu.',
                    ],
                ], 404);
            }
        });
    })->create();
