# SPDX-License-Identifier: Apache-2.0
# Multi-stage Dockerfile cho ứng dụng Web Laravel + Tailwind CSS (chạy bằng non-root user)

# Giai đoạn 1: Build tài nguyên tĩnh Frontend (HTML/Tailwind CSS/Vite)
FROM node:22-alpine AS frontend-builder
WORKDIR /app
COPY package.json vite.config.js ./
RUN npm install
COPY resources ./resources
COPY public ./public
RUN npm run build

# Giai đoạn 2: Cài đặt thư viện PHP qua Composer (cố định mục tiêu PHP 8.3 khớp với runtime)
FROM composer:2.8 AS vendor-builder
WORKDIR /app
COPY composer.json ./
RUN composer config platform.php 8.3.35 \
    && composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Giai đoạn 3: Image thực thi PHP 8.3 CLI/Web Alpine nhẹ và an toàn
FROM php:8.3-cli-alpine AS runtime

# Cài đặt các gói hệ thống và PHP extensions cần thiết (pdo_pgsql, pcntl, mbstring, zip)
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    oniguruma-dev \
    curl \
    bash \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip pcntl

# Tạo tài khoản non-root để vận hành container an toàn theo tiêu chuẩn bảo mật sản phẩm
RUN addgroup -g 10001 -S appgroup && adduser -u 10001 -S appuser -G appgroup

WORKDIR /var/www/html

# Sao chép mã nguồn, vendor và bản build Vite
COPY --chown=appuser:appgroup . .
COPY --from=vendor-builder --chown=appuser:appgroup /app/vendor ./vendor
COPY --from=frontend-builder --chown=appuser:appgroup /app/public/build ./public/build

# Đảm bảo quyền ghi cho thư mục storage và bootstrap/cache, xóa cache gói dev từ máy host
RUN mkdir -p storage/app/private storage/app/public \
    storage/framework/cache storage/framework/sessions \
    storage/framework/testing storage/framework/views \
    storage/logs bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && chown -R appuser:appgroup storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

USER appuser

EXPOSE 3000

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=3000"]
