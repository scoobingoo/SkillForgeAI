# Third-Party Open Source Licenses

Dự án **SkillForge AI** được cấp phép theo **Apache License 2.0**. Toàn bộ các thư viện phụ thuộc và mô hình AI mặc định đều tuân thủ nghiêm ngặt danh sách giấy phép nguồn mở được OSI phê duyệt (MIT, BSD-2/3-Clause, Apache-2.0, ISC, PostgreSQL, LGPL-3.0).

## 1. Phụ thuộc Backend (PHP / Composer)

| Gói phụ thuộc | Phiên bản | Giấy phép |
| --- | --- | --- |
| `laravel/framework` | `^11.0` | MIT |
| `smalot/pdfparser` | `^2.11` | LGPL-3.0 |
| `symfony/yaml` | `^7.0` | MIT |
| `league/commonmark` | `^2.5` | BSD-3-Clause |
| `phpunit/phpunit` | `^11.0` | BSD-3-Clause |
| `laravel/pint` | `^1.13` | MIT |

## 2. Phụ thuộc Giao diện Web (Frontend / NPM)

| Gói phụ thuộc | Phiên bản | Giấy phép |
| --- | --- | --- |
| `tailwindcss` | `^4.0.0` | MIT |
| `alpinejs` | `^3.14.3` | MIT |
| `chart.js` | `^4.4.6` | MIT |
| `vite` | `^6.0.0` | MIT |

## 3. Cơ sở dữ liệu & Mô hình AI Mở (Local Runtime)

| Thành phần | Giấy phép |
| --- | --- |
| PostgreSQL 17 | PostgreSQL License (OSI Approved) |
| `pgvector/pgvector` | PostgreSQL License (OSI Approved) |
| Ollama Runtime | MIT |
| Model `qwen3:4b` / `qwen3:8b` | Apache-2.0 |
| Model `bge-m3` | MIT |
| Dữ liệu mở trong `data/` | CC BY 4.0 |
