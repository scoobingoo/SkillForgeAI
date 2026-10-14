---
slug: postgresql-pgvector-and-indexing
title: "Làm chủ PostgreSQL 17, Tối ưu Truy vấn SQL & Tìm kiếm Vector với pgvector"
skills: ["postgresql", "sql", "database-design", "vector-search"]
level: intermediate
language: vi
url: "https://github.com/pgvector/pgvector"
license: "PostgreSQL"
est_hours: 4.0
---

## Thiết kế Lược đồ Quan hệ và Chỉ mục B-Tree / GIN

Trong PostgreSQL 17, việc lựa chọn đúng loại chỉ mục quyết định hiệu năng truy vấn. Sử dụng chỉ mục B-Tree cho các cột khoá ngoại và định danh `slug`, sử dụng chỉ mục GIN (`Generalized Inverted Index`) cho các cột kiểu `jsonb` (như mảng kỹ năng) và cột `tsvector` phục vụ tìm kiếm toàn văn (Full-Text Search) kết hợp tiện ích mở rộng `unaccent` cho tiếng Việt.

## Tìm kiếm Tương đồng Ngữ nghĩa với pgvector và HNSW Index

Tiện ích mở rộng `pgvector` cho phép lưu trữ vector nhúng (ví dụ 1024 chiều từ mô hình `bge-m3`) ngay trong bảng dữ liệu. Để tăng tốc truy vấn láng giềng gần nhất theo khoảng cách Cosine (`vector_cosine_ops`), hãy khởi tạo chỉ mục HNSW (`Hierarchical Navigable Small World`), giúp truy xuất hàng chục nghìn phân đoạn tài liệu trong vài mili-giây.

## Kết hợp Hybrid Search với Reciprocal Rank Fusion (RRF)

Tìm kiếm vector nắm bắt tốt ngữ nghĩa khái niệm nhưng có thể bỏ sót tên riêng hoặc từ khoá kỹ thuật chính xác. Bằng cách kết hợp Top-20 kết quả từ Vector Search và Top-20 kết quả từ Full-Text Search thông qua công thức Reciprocal Rank Fusion ($k = 60$), hệ thống RAG đạt độ chính xác vượt trội cho bài toán gợi ý lộ trình học tập.
