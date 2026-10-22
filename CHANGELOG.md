# Changelog

Mọi thay đổi đáng chú ý của dự án **SkillForge AI** đều được ghi nhận tại tệp này theo chuẩn [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) và [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-22

### Added
- Ra mắt phiên bản chính thức **SkillForge AI v1.0.0** trên nền tảng **Laravel 11 (PHP 8.3) + Blade + Tailwind CSS v4**.
- Tích hợp cơ sở dữ liệu **PostgreSQL 17 (`pgvector`, `unaccent`, `pgcrypto`)** với 12 bảng chuẩn khoá chính UUID v7 và cơ chế xoá dữ liệu liên hoàn (Cascade Delete).
- Xây dựng kiến trúc `LlmProviderInterface` độc lập với `OpenAiCompatibleProvider` (tích hợp **Ollama** `qwen3:4b` & `bge-m3`) và `FakeLlmProvider` phục vụ kiểm thử tự động.
- Triển khai pipeline phân tích **Skill Gap**: kiểm tra chữ ký tệp `%PDF-`, trích xuất văn bản bằng `smalot/pdfparser`, ẩn thông tin cá nhân (Email/SĐT), kiểm chứng đoạn trích nguyên văn (`evidence`), chuẩn hoá kỹ năng 3 tầng (Alias $\rightarrow$ Vector Cosine $\ge 0.82$ $\rightarrow$ LLM Top-5) và dịch vụ tính điểm thuần `ScoringService`.
- Triển khai hệ thống **Hybrid RAG (`php artisan app:ingest`)** kết hợp Vector Search + Full-Text Search với thuật toán Reciprocal Rank Fusion ($k=60$) và tự động phân bổ giờ học mỗi tuần (`hoursPerWeek`).
- Triển khai mô-đun **Phỏng vấn kỹ thuật thích ứng (Adaptive Mock Interview)** tự động điều chỉnh độ khó theo điểm câu trước và chấm điểm theo Rubric ở nhiệt độ $0$.
- Nâng cấp toàn diện giao diện người dùng: hỗ trợ kéo thả PDF trực tiếp, bộ lọc khoảng cách kỹ năng tức thì, xuất báo cáo JSON/PDF, thanh tiến độ học tập tổng thể trên lộ trình RAG và thanh bước tiến trình câu hỏi phỏng vấn.
