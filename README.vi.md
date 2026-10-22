# ⚡ SkillForge AI — Nền tảng Phân tích Khoảng cách Kỹ năng & Cố vấn Sự nghiệp AI (Tài liệu Tiếng Việt)

[![Giấy phép Mã nguồn: Apache-2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](./LICENSE)
[![Giấy phép Dữ liệu: CC BY 4.0](https://img.shields.io/badge/Data_License-CC_BY_4.0-emerald.svg)](./data/LICENSE)

**SkillForge AI** là ứng dụng Web mã nguồn mở được phát triển bằng **Laravel 11 (PHP 8.3) + Blade + Tailwind CSS v4**, kết hợp cơ sở dữ liệu vector **PostgreSQL 17 (`pgvector`)** và mô hình ngôn ngữ lớn chạy cục bộ **Ollama (`qwen3:4b` + `bge-m3`)**. Hệ thống giúp sinh viên và lập trình viên tự đánh giá năng lực qua CV so với yêu cầu tuyển dụng (JD) kèm trích dẫn bằng chứng nguyên văn, lập lộ trình học tập Hybrid RAG theo tuần và luyện phỏng vấn kỹ thuật thích ứng.

---

## 1. Khởi chạy toàn trình bằng Docker Compose

Chỉ cần sao chép tệp môi trường và khởi chạy Docker Compose:

```bash
cp .env.example .env && docker compose up -d
```

Sau đó mở trình duyệt tại **`http://localhost:3000`**.

Toàn bộ 5 dịch vụ trong `docker-compose.yml` sẽ tự động phối hợp khởi tạo:
1. `postgres`: Cơ sở dữ liệu `pgvector/pgvector:pg17` tích hợp sẵn các extension `vector`, `unaccent`, `pgcrypto`.
2. `ollama`: Máy chủ suy luận AI cục bộ.
3. `ollama-init`: Tự động tải 2 mô hình `qwen3:4b` (suy luận/chat) và `bge-m3` (embedding 1024 chiều).
4. `web`: Ứng dụng Web Laravel + Tailwind CSS tại cổng `3000`, tự động chạy migration, seed Taxonomy và nạp học liệu RAG khi khởi động.
5. `worker`: Tiến trình xử lý hàng đợi bất đồng bộ (Queue Worker) cho các tác vụ phân tích CV, JD và tạo báo cáo.

---

## 2. Cấu hình Kết nối AI Linh hoạt (OpenAI-Compatible)

Mặc định hệ thống hoạt động hoàn toàn cục bộ và miễn phí với Ollama. Nếu muốn kết nối tới bất kỳ máy chủ suy luận tương thích chuẩn OpenAI nào khác (vLLM, LM Studio, OpenRouter, OpenAI...), bạn chỉ cần điều chỉnh trong `.env`:

```env
LLM_PROVIDER=openai_compatible
LLM_BASE_URL=http://ollama:11434/v1
LLM_API_KEY=ollama
LLM_MODEL=qwen3:4b
EMBED_MODEL=bge-m3
```

---

## 3. Tính năng Giao diện & Trải nghiệm Người dùng

- **Trải nghiệm 1-Click Demo:** Cho phép chọn nhanh 3 hồ sơ CV mẫu và 5 vị trí chuẩn (Roles) hoặc dán JD tuyển dụng thực tế để kiểm nghiệm toàn bộ luồng phân tích ngay lập tức.
- **Báo cáo Skill Gap trực quan:** Hiển thị biểu đồ Radar theo nhóm kỹ năng, bộ lọc khoảng cách kỹ năng tương tác trực tiếp (Bắt buộc / Ưu tiên / Thiếu hoàn toàn / Chưa đủ mức), hộp thoại đối chiếu bằng chứng nguyên văn (`evidence`), hỗ trợ **Xuất JSON** và **In / Lưu báo cáo PDF**.
- **Lộ trình Học tập Hybrid RAG:** Tự động cập nhật thanh tiến độ phần trăm hoàn thành tổng thể ngay khi đánh dấu từng học liệu, hỗ trợ tái tạo lộ trình theo thời gian thực qua luồng **Server-Sent Events (SSE)**.
- **Phỏng vấn Thử Thích ứng:** Thanh theo dõi tiến trình câu hỏi (Question Stepper), hỗ trợ phím tắt `Ctrl + Enter` và bảng chấm điểm chi tiết theo 3 tiêu chí Rubric (`Correctness`, `Depth`, `Clarity`).
