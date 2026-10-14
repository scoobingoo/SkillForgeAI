---
slug: automated-testing-phpunit-and-tdd
title: "Kiểm thử Tự động Phần mềm: Unit Test, Integration Test & TDD"
skills: ["unit-testing", "e2e-testing", "php", "laravel"]
level: intermediate
language: vi
url: "https://phpunit.de/documentation.html"
license: "BSD-3-Clause"
est_hours: 3.0
---

## Thiết kế Hàm Thuần (Pure Functions) Để Đạt Độ Bao Phủ Kiểm thử Cao

Các thuật toán tính điểm quan trọng như `ScoringService` cần được viết dưới dạng hàm thuần tuý (không phụ thuộc trực tiếp vào Database hay HTTP). Nhờ đó, bạn có thể viết hàng chục kịch bản kiểm thử biên (edge cases: CV trống, khớp kỹ năng con được 0.5 điểm, trọng số required = 2 vs nice_to_have = 1) chạy trong vài mili-giây với độ phủ trên 90%.

## Kiểm thử Tích hợp với Fake Double cho Dịch vụ AI

Trong môi trường CI/CD, việc gọi mô hình LLM thật vừa chậm vừa không tất định. Hãy sử dụng mẫu thiết kế Strategy / Adapter (`FakeLlmProvider`) để kiểm thử toàn bộ luồng trích xuất, đối chiếu bằng chứng `evidence` và cơ chế tự động thử lại (retry) khi LLM trả về JSON sai định dạng.
