<?php

// SPDX-License-Identifier: Apache-2.0
// Định tuyến REST API (/api/v1/*) cho hệ thống SkillForge AI

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\JobStatusController;
use App\Http\Controllers\Api\JobTargetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

// Kiểm tra trạng thái DB, Ollama và model đã tải xong chưa
Route::get('/health', [HealthController::class, 'show'])->name('api.health');

// Theo dõi tiến trình xử lý job bất đồng bộ
Route::get('/jobs/{id}', [JobStatusController::class, 'show'])->name('api.jobs.show');

// Hồ sơ CV ứng viên
Route::post('/profiles', [ProfileController::class, 'store'])->name('api.profiles.store');
Route::get('/profiles/{id}', [ProfileController::class, 'show'])->name('api.profiles.show');
Route::patch('/profiles/{id}/skills', [ProfileController::class, 'updateSkills'])->name('api.profiles.updateSkills');
Route::delete('/profiles/{id}', [ProfileController::class, 'destroy'])->name('api.profiles.destroy');

// Danh sách vị trí mẫu & mục tiêu tuyển dụng (JD)
Route::get('/roles', [JobTargetController::class, 'roles'])->name('api.roles.index');
Route::post('/job-targets', [JobTargetController::class, 'store'])->name('api.jobTargets.store');

// Báo cáo Skill Gap & Lộ trình học tập RAG
Route::post('/reports', [ReportController::class, 'store'])->name('api.reports.store');
Route::get('/reports/{id}', [ReportController::class, 'show'])->name('api.reports.show');
Route::post('/reports/{id}/roadmap', [ReportController::class, 'generateRoadmap'])->name('api.reports.roadmap');
Route::patch('/roadmaps/{id}/progress', [ReportController::class, 'updateProgress'])->name('api.roadmaps.progress');

// Phỏng vấn thử thích ứng (Mock Interview)
Route::post('/interviews', [InterviewController::class, 'store'])->name('api.interviews.store');
Route::get('/interviews/{id}', [InterviewController::class, 'show'])->name('api.interviews.show');
Route::post('/interviews/{id}/answers', [InterviewController::class, 'submitAnswer'])->name('api.interviews.answers');
