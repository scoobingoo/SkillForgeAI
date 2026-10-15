---
slug: laravel-architecture-and-queues
title: "Kiến trúc Laravel 11 Chuyên sâu: Service Container, Eloquent & Queue Workers"
skills: ["laravel", "php", "queue-workers", "rest-api"]
level: intermediate
language: vi
url: "https://laravel.com/docs/11.x/queues"
license: "CC-BY-4.0"
est_hours: 4.5
---

## Service Container và Dependency Injection trong Laravel

Laravel sử dụng Service Container mạnh mẽ để quản lý phụ thuộc giữa các lớp (Dependency Injection). Khi thiết kế ứng dụng quy mô lớn, bạn nên tách biệt logic nghiệp vụ vào các lớp Service độc lập (ví dụ `ScoringService`, `SkillNormalizerService`) và ràng buộc qua Interface (`LlmProviderInterface`) trong `AppServiceProvider`. Cách tiếp cận này giúp dễ dàng hoán đổi sang `FakeLlmProvider` khi chạy kiểm thử tự động mà không gọi model thật.

## Tối ưu hoá Eloquent ORM và Phòng chống lỗi N+1 Query

Khi truy vấn các bảng có quan hệ một-nhiều như `profiles -> profile_skills` hoặc `gap_reports -> interview_sessions`, luôn sử dụng Eager Loading với phương thức `with()` để nạp trước dữ liệu liên quan trong một câu truy vấn duy nhất. Ngoài ra, sử dụng khoá chính UUID v7 (`HasUuids`) giúp đảm bảo tính tuần tự theo thời gian và tăng tốc độ ghi chỉ mục B-Tree trên PostgreSQL.

## Xử lý Bất đồng bộ với Laravel Database Queue

Đối với các tác vụ tốn thời gian như trích xuất văn bản PDF và gọi mô hình ngôn ngữ lớn (Ollama), tuyệt đối không xử lý đồng bộ trong HTTP Request. Hãy đẩy công việc vào hàng đợi bằng `ProcessProfileJob::dispatch()` chạy trên bảng `jobs` của PostgreSQL và cung cấp endpoint theo dõi tiến trình theo thời gian thực.
