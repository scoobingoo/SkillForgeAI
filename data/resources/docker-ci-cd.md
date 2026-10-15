---
slug: docker-and-ci-cd-fundamentals
title: "Đóng gói Ứng dụng với Multi-stage Docker & Tự động hoá CI/CD GitHub Actions"
skills: ["docker", "ci-cd", "linux", "nginx", "kubernetes"]
level: intermediate
language: vi
url: "https://docs.docker.com/build/building/multi-stage/"
license: "Apache-2.0"
est_hours: 3.5
---

## Viết Multi-stage Dockerfile Nhẹ và Bảo mật

Một Dockerfile chuẩn sản phẩm nguồn mở cần tách riêng giai đoạn biên dịch tài nguyên tĩnh (Node.js/Vite) và cài đặt phụ thuộc (Composer) khỏi image thực thi cuối cùng (`php:8.3-cli-alpine`). Luôn tạo và chuyển sang người dùng không phải root (`USER appuser`) để giảm thiểu bề mặt tấn công bảo mật.

## Điều phối Đa dịch vụ bằng Docker Compose và Healthcheck

Sử dụng `docker-compose.yml` với cấu hình `healthcheck` rõ ràng cho PostgreSQL (`pg_isready`) và Ollama giúp các dịch vụ phụ thuộc (`web`, `worker`, `ollama-init`) khởi động đúng thứ tự mà không gặp lỗi tranh chấp kết nối.

## Thiết lập Pipeline CI/CD Tự động với GitHub Actions

Mọi Pull Request cần tự động chạy qua các bước: cài đặt phụ thuộc, kiểm tra chuẩn mã nguồn (Linter), kiểm tra kiểu dữ liệu và chạy toàn bộ bộ kiểm thử đơn vị / tích hợp với `FakeLlmProvider` trước khi được phép hợp nhất vào nhánh chính.
