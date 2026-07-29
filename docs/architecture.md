# Kiến trúc hệ thống

Cập nhật: 29/07/2026.

```text
Learner Next.js ─┐
                 ├─ same-origin /api rewrite ─ Laravel ─ MySQL
Admin Next.js ───┘                              ├─ Redis cache/queue
                                                └─ LexiLingo backend/AI
```

- Laravel là nguồn dữ liệu và phân quyền duy nhất. Hai frontend không kết nối
  database và không giữ LexiLingo secret.
- Xác thực trình duyệt dùng Laravel session, CSRF cookie và `X-XSRF-TOKEN`;
  không dùng bearer token.
- `routes/spa.php` chứa API cần session. `routes/web.php` giữ callback/tên route
  tương thích và chuyển giao diện cũ sang Next.js.
- Dữ liệu catalog được import vào MySQL theo `external_id`; request học đọc dữ
  liệu local. AI HTTP được Laravel proxy server-to-server với timeout, giới hạn
  và lỗi đã chuẩn hóa.
- Admin thường quản lý nội dung/tổng hợp. Super admin mới thấy operations,
  quota, alert rule, audit và teacher scope. Backend luôn kiểm tra capability,
  không dựa vào việc ẩn menu.

Chi tiết trạng thái và giới hạn hiện tại nằm tại
[`CURRENT_STATUS.md`](CURRENT_STATUS.md); cấu hình production nằm tại
[`PRODUCTION_ENV.md`](PRODUCTION_ENV.md).
