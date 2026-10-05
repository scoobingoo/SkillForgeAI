<?php

// SPDX-License-Identifier: Apache-2.0
// Middleware gán Request-ID phục vụ truy vết log (không log nội dung CV) và thiết lập ngôn ngữ giao diện (vi/en)

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdAndLocaleMiddleware
{
    /**
     * Xử lý request: gắn X-Request-Id, thiết lập ngôn ngữ (mặc định tiếng Việt) và header bảo mật cơ bản.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sinh hoặc lấy Request ID từ header
        $requestId = $request->header('X-Request-Id', (string) Str::uuid());
        $request->attributes->set('request_id', $requestId);

        // Đưa requestId vào ngữ cảnh log nhưng tuyệt đối KHÔNG log nội dung CV (ràng buộc mục 1.8)
        Log::withContext([
            'requestId' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        // Thiết lập ngôn ngữ (ưu tiên query ?lang=, sau đó session/cookie, mặc định 'vi')
        $locale = $request->query('lang')
            ?? ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? $request->cookie('acc_locale')
            ?? config('app.locale', 'vi');

        if (in_array($locale, ['vi', 'en'], true)) {
            App::setLocale($locale);
            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        // Gắn các security headers (tương đương Helmet) và X-Request-Id
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
