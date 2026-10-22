# Chính sách Bảo mật (Security Policy)

## Các phiên bản được hỗ trợ

| Phiên bản | Hỗ trợ cập nhật bảo mật |
| --- | --- |
| `1.0.x` | ✅ Có |

## Các biện pháp bảo mật tích hợp sẵn trong SkillForge AI

1. **Kiểm tra tệp tải lên nghiêm ngặt:** Kiểm tra trực tiếp chuỗi byte định danh `%PDF-` (magic bytes), giới hạn dung lượng `MAX_UPLOAD_MB` (mặc định 5 MB) và xoá tệp PDF gốc ngay lập tức sau khi trích xuất văn bản.
2. **Bảo vệ dữ liệu cá nhân (PII Redaction):** Tự động ẩn địa chỉ email (`[EMAIL_REDACTED]`) và số điện thoại (`[PHONE_REDACTED]`) trước khi lưu vào cơ sở dữ liệu hoặc gửi tới LLM. Tuyệt đối không ghi log nội dung CV.
3. **Chống Prompt Injection:** Nội dung CV và JD được cô lập trong cặp thẻ `<cv>...</cv>` và `<jd>...</jd>` kèm chỉ thị hệ thống coi toàn bộ nội dung bên trong là dữ liệu không đáng tin cậy.
4. **Thực thi Container an toàn:** Image Docker chạy dưới quyền người dùng không phải root (`appuser`, UID `10001`).

## Báo cáo lỗ hổng bảo mật

Nếu bạn phát hiện lỗ hổng bảo mật, vui lòng **không** mở Issue công khai. Hãy gửi báo cáo qua tính năng **GitHub Private Vulnerability Reporting** của kho mã nguồn. Chúng tôi sẽ phản hồi trong vòng 48 giờ.
