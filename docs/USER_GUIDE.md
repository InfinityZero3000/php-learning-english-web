# Hướng dẫn sử dụng

Cập nhật: 29/07/2026. Tài liệu này mô tả các luồng hiện có; việc cài đặt local
nằm trong [`../README.md`](../README.md).

## Learner

1. Đăng ký hoặc đăng nhập tại learner frontend.
2. Chọn khóa học ở **Courses**, enroll rồi bắt đầu session từ course hoặc
   **Today**.
3. Hoàn thành activity trong session; lỗi AI/voice không chặn việc lưu kết quả
   học bằng văn bản.
4. Ôn từ đến hạn tại **FSRS Review/Flashcards**, làm vocabulary quiz và xem
   thống kê tại **Progress**.
5. **Listening** cần provider/caption được cấu hình. **Assignments** chỉ có dữ
   liệu khi learner được teacher phân công.

## Teacher

Tài khoản role `teacher` dùng **Teacher** để xem learner được phân công, tiến
độ/evidence, tạo hoặc cập nhật assignment, ghi intervention note và xử lý alert.
Teacher không có quyền xem learner ngoài scope.

## Admin và Super Admin

- Admin đăng nhập qua Google admin entry đã whitelist rồi quản lý course,
  lesson, level, topic, vocabulary, deck, quiz, import, user và báo cáo tổng hợp.
- Super admin có thêm Roles & Teacher Scope, Operations và Audit Trail.
- Import chạy server-to-server theo checkpoint. Không reset production hoặc
  thay đổi quota/role nếu chưa xác nhận rollback và quyền phù hợp.

## Lỗi thường gặp

- `401`: session hết hạn hoặc chưa đăng nhập.
- `403`: đúng session nhưng sai role/capability hoặc Google admin không được
  phép.
- `419`: CSRF cookie/header không đồng bộ; tải lại frontend và thử lại.
- `503 feature_disabled`: integration chưa bật/cấu hình; chức năng học nền vẫn
  tiếp tục nếu UI hiển thị degraded state.
- AI/import/listening lỗi upstream: kiểm tra biến môi trường và health trước;
  không đưa secret vào ảnh chụp hoặc log hỗ trợ.

Các gap đã biết và trạng thái test mới nhất nằm tại
[`CURRENT_STATUS.md`](CURRENT_STATUS.md).
