<?php

// SPDX-License-Identifier: Apache-2.0
// Cấu hình cơ bản của ứng dụng Laravel (SkillForge AI — mặc định ngôn ngữ tiếng Việt)

return [
    'name' => env('APP_NAME', 'SkillForge AI'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', env('WEB_ORIGIN', 'http://localhost:3000')),
    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => env('APP_LOCALE', 'vi'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => 'vi_VN',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    ],
];
