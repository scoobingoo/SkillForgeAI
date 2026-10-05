<?php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

// SPDX-License-Identifier: Apache-2.0
// Cấu hình hệ thống ghi nhận nhật ký (JSON Structured Logging trên luồng stderr và file)

return [
    'default' => env('LOG_CHANNEL', 'stderr'),
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'formatter' => JsonFormatter::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'replace_placeholders' => true,
        ],
    ],
];
