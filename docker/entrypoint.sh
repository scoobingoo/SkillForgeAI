#!/usr/bin/env bash
# SPDX-License-Identifier: Apache-2.0
# Script khởi động container: tự động chạy migration, seed dữ liệu mở và nạp tài liệu RAG nếu chạy web server

set -e

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp .env.example .env
fi

# Xóa cache package discovery cũ (nếu được sinh từ môi trường dev bên ngoài có chứa gói require-dev)
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

# Nếu là tiến trình web chính (không phải queue worker), tự động chạy migration + seed
if [ "$1" = "php" ] && [ "$2" = "artisan" ] && [ "$3" = "serve" ]; then
    echo "[SkillForge AI] Đang chạy database migrations tự động..."
    php artisan migrate --force || true

    echo "[SkillForge AI] Đang kiểm tra và nạp danh mục kỹ năng / vai trò (seed)..."
    php artisan db:seed --force || true

    echo "[SkillForge AI] Đang kiểm tra và nạp tài liệu học tập (RAG ingest)..."
    php artisan app:ingest || true
fi

exec "$@"
