<?php

// SPDX-License-Identifier: Apache-2.0
// Định nghĩa các lệnh console bổ sung

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:acc', function () {
    $this->info('SkillForge AI v1.0.0 — Open-Source Career Intelligence & Skill Gap Analyzer (Laravel 11 + Tailwind CSS + pgvector + Ollama)');
})->purpose('Hiển thị thông tin phiên bản hệ thống SkillForge AI');
