<?php

// SPDX-License-Identifier: Apache-2.0
// Điểm vào HTTP chính cho ứng dụng Web Laravel

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Kiểm tra chế độ bảo trì nếu có
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Nạp Composer autoloader
require __DIR__.'/../vendor/autoload.php';

// Khởi chạy Laravel và xử lý HTTP Request
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
