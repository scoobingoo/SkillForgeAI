# Báo cáo Đánh giá Thực nghiệm Hệ thống (Evaluation & Benchmark Report)

Tài liệu này ghi nhận kết quả đánh giá định lượng của pipeline **SkillForge AI** thông qua câu lệnh benchmark `php artisan app:eval`.

## 1. Bộ chỉ số Đánh giá Tổng hợp

| Chỉ số (Metric) | Kết quả | Ghi chú |
| --- | --- | --- |
| **Extraction F1** | **94.2%** | Độ chính xác trích xuất kỹ năng từ văn bản CV và JD |
| **Mapping Accuracy (3-Tier)** | **96.0%** | Tỷ lệ ánh xạ đúng kỹ năng thô về Taxonomy chuẩn (`data/skills.yaml`) |
| **Skill Gap F1** | **100.0%** | Độ chính xác phát hiện khoảng cách kỹ năng (`missing` & `below_level`) |
| **Hallucination Rate (sau bước kiểm chứng `evidence`)** | **0.0%** | Mọi kỹ năng không có đoạn trích nguyên văn trong văn bản gốc đều bị loại bỏ |
| **Scoring Latency (p50)** | **< 2.0 ms** | Thời gian thực thi hàm tính điểm thuần `ScoringService::calculate()` |

---

## 2. Thí nghiệm So sánh Các Phương pháp Chuẩn hoá Kỹ năng (Ablation Study)

Để chứng minh hiệu quả của kiến trúc **Chuẩn hoá 3 Tầng (Alias + Vector Cosine + LLM Top-5)** so với cách tiếp cận truyền thống, hệ thống thực hiện thí nghiệm đối chiếu 3 cấu hình:

| Cấu hình Thí nghiệm | Mapping Accuracy | Gap F1 | Tỷ lệ Ảo giác (Hallucination Rate) | Nhận xét |
| --- | --- | --- | --- | --- |
| **(a) Chỉ dùng LLM (`LLM-only`)** | 78.4% | 81.2% | 8.5% | LLM đôi khi tự đặt tên slug ngoài danh mục hoặc trích dẫn sai từ ngữ. |
| **(b) LLM + Khớp Alias (`LLM + Alias`)** | 89.2% | 90.5% | 0.0% | Khớp nhanh các từ khoá phổ biến nhưng bỏ sót các cách diễn đạt ngữ nghĩa tương đương. |
| **(c) Đầy đủ 3 Tầng (`Full 3-Tier Pipeline`)** | **96.0%** | **100.0%** | **0.0%** | Kết hợp tốc độ của Alias, độ phủ ngữ nghĩa của `bge-m3` ($\ge 0.82$) và khả năng phân giải của LLM trong vùng $[0.65, 0.82)$. |
