# Hướng dẫn Đóng góp (Contributing Guide)

Cảm ơn bạn đã quan tâm và đóng góp cho dự án mã nguồn mở **SkillForge AI**!

## 1. Quy trình làm việc & Quy ước nhánh

- Nhánh tính năng: `feat/<ten-tinh-nang>`
- Nhánh sửa lỗi: `fix/<ten-loi>`
- Nhánh tài liệu: `docs/<noi-dung>`
- Nhánh dữ liệu mở: `data/<noi-dung>`
- Mọi commit tuân thủ chuẩn **Conventional Commits** (ví dụ: `feat: add hybrid search RRF scoring`, `data: add cloud native skills`).

## 2. Tiêu chuẩn kỹ thuật khi gửi Pull Request

1. **Giấy phép OSI:** Chỉ sử dụng các thư viện phụ thuộc có giấy phép nguồn mở được OSI phê duyệt (Apache-2.0, MIT, BSD-2/3-Clause, ISC, PostgreSQL, LGPL-3.0).
2. **Bảo mật & Quyền riêng tư:** Không bao giờ ghi log nội dung CV của người dùng. Luôn duy trì cơ chế ẩn thông tin định danh (`[EMAIL_REDACTED]`, `[PHONE_REDACTED]`).
3. **Đối chiếu bằng chứng xác thực (`evidence`):** Mọi kết quả trích xuất kỹ năng hoặc khoảng cách kỹ năng đều phải đi qua hàm kiểm chứng `SkillNormalizerService::verifyEvidenceInSource()`.
4. **Kiểm thử tự động:** Chạy `composer test` và `composer lint` đảm bảo 100% bài kiểm thử vượt qua với `FakeLlmProvider`.
