# Security Audit & Verification Report

**Người thực hiện**: Yuu-25-uuY (Thư)  
**Ngày hoàn thành**: 2026-07-28  
**Trạng thái**: Đã xác minh (Verified)  
**Dự án**: Website học tiếng Anh

Báo cáo này cung cấp thông tin kiểm tra và bằng chứng kiểm thử về bảo mật cho hệ thống trước khi release production theo yêu cầu của **Issue #14**.

---

## 1. SQL Injection (SQLi)
- **Cơ chế bảo vệ**: 
  - Toàn bộ câu lệnh SQL truy vấn và ghi nhận dữ liệu trong Laravel đều sử dụng Eloquent ORM hoặc Query Builder với cơ chế Parameter Binding tự động.
  - Tuyệt đối không nội suy dữ liệu chưa qua xử lý trực tiếp từ Request vào các câu lệnh SQL.
- **Bằng chứng kiểm tra**:
  - Đã rà soát thủ công các từ khóa như `selectRaw`, `whereRaw`, `orderByRaw`, `unprepared`, và `statement` trên toàn codebase.
  - Tài liệu chi tiết đã lưu tại [auth-sql-injection-audit.md](file:///c:/laragon/www/DoAn/docs/security/auth-sql-injection-audit.md).
- **Kết quả**: Không phát hiện lỗ hổng SQL Injection nào.

---

## 2. Cross-Site Scripting (XSS)
- **Cơ chế bảo vệ**:
  - Trên API: Laravel trả dữ liệu dạng JSON thô. API client (Next.js) thực hiện render. Trong Next.js, React mặc định tự động escape tất cả dữ liệu text được render vào DOM để tránh XSS.
  - Trên Blade (fallback views): Sử dụng cú pháp `{{ $variable }}` giúp Laravel tự động escape HTML khi hiển thị. Các ký tự `<` thành `&lt;`, `>` thành `&gt;`, v.v.
  - Input validation: Sử dụng các rule validate nghiêm ngặt (như `string`, `max:255`, email validate) để loại bỏ payload không hợp lệ trước khi lưu.
- **Kết quả**: Dữ liệu hiển thị được bảo vệ an toàn chống lại XSS phản xạ (Reflected XSS) và lưu trữ (Stored XSS).

---

## 3. CSRF & CORS (Cross-Origin Resource Sharing)
- **Cơ chế bảo vệ**:
  - **CSRF**: Laravel sử dụng cookie bảo mật `XSRF-TOKEN` và middleware `ValidateCsrfToken` cho các SPA/Web session routes bảo vệ khỏi việc giả mạo request từ site khác.
  - **CORS**: Cấu hình CORS chặt chẽ để chỉ cho phép nguồn từ các domain được chỉ định bởi biến môi trường:
    - `FRONTEND_URL` (cho học viên Next.js Vercel app)
    - `ADMIN_FRONTEND_URL` (cho admin Next.js Vercel app)
- **Kết quả**: Các origin không được khai báo trước đều bị từ chối truy cập tài nguyên (CORS block).

---

## 4. Rate Limiting (Giới hạn lượt gọi API)
- **Cơ chế bảo vệ**:
  - Các API nhạy cảm và dễ bị tấn công brute-force hoặc spam được bảo vệ bởi middleware `throttle` của Laravel.
- **Thống kê giới hạn lượt gọi API**:
  - **Đăng ký tài khoản** (`/auth/register`): Tối đa 5 lượt/phút (`throttle:5,1`).
  - **Đăng nhập** (`/auth/login`): Tối đa 5 lượt/phút (`throttle:5,1`).
  - **Gửi lại email xác thực** (`/auth/email/resend`): Tối đa 3 lượt/phút (`throttle:3,1`).
  - **Quên mật khẩu** (`/auth/password/forgot`): Tối đa 3 lượt/phút (`throttle:3,1`).
  - **Dịch thuật AI** (`/ai/translate`): Tối đa 20 lượt/phút (`throttle:20,1`).
  - **Kiểm tra phát âm AI** (`/ai/pronunciation`): Tối đa 10 lượt/phút (`throttle:10,1`).
  - **Chuyển giọng nói thành văn bản STT** (`/ai/speech-to-text`): Tối đa 10 lượt/phút (`throttle:10,1`).
  - **Chuyển văn bản thành giọng nói TTS** (`/ai/text-to-speech`): Tối đa 20 lượt/phút (`throttle:20,1`).
- **Bằng chứng kiểm thử**:
  - Đã viết unit test tự động trong `SecurityAndObservabilityTest.php` mô phỏng việc spam API đăng ký tài khoản và xác nhận hệ thống trả về mã lỗi HTTP 429 (`Too Many Requests`) chính xác sau 5 lần request liên tiếp.
- **Kết quả**: Hệ thống chống spam và brute-force hiệu quả.

---

## 5. Quyền tài nguyên (Resource Authorization) & Role Guards
- **Cơ chế bảo vệ**:
  - Học viên không thể truy cập các route quản trị của admin nhờ middleware `role:admin`.
  - Phân quyền theo bản ghi (IDOR prevention): Sử dụng Policies của Laravel (như `UserPolicy`, `QuizPolicy`...) để kiểm tra quyền sở hữu hoặc quyền hạn trước khi cho phép xem/sửa/xóa tài nguyên.
- **Bằng chứng kiểm thử**:
  - Đã có test case đầy đủ trong `AdminCrudApiTest.php` xác minh:
    - Khách (Guest) truy cập API admin bị trả lỗi HTTP 401 (`Unauthorized`).
    - Học viên (Learner) truy cập API admin bị trả lỗi HTTP 403 (`Forbidden`).
    - Chỉ tài khoản có role `admin` mới thực hiện được CRUD thành công.
- **Kết quả**: Phân quyền tài nguyên được kiểm soát chặt chẽ từ tầng middleware đến route.

---

## 6. Observability (Stuctured Logging & Tracing)
- **Cơ chế bảo vệ**:
  - Đã triển khai `LogContextMiddleware` để tự động chia sẻ context metadata cho Monolog trên mọi dòng log phát sinh trong cùng một request.
  - Context bao gồm:
    - `request_id`: Mã request dạng UUID giúp trace toàn bộ luồng xử lý trên server.
    - `user_id`: ID người dùng thực hiện (nếu đã đăng nhập).
    - `ip`: Địa chỉ IP của client.
    - `method` & `url`: Phương thức HTTP và URL đang truy cập.
  - Header phản hồi (`Response Header`): Trả về `X-Request-ID` cho trình duyệt để hỗ trợ người dùng báo lỗi kèm theo mã request, giúp admin tra cứu log chính xác.
- **Bằng chứng kiểm thử**:
  - Đã có test case xác minh middleware tự tạo ra UUID và gắn vào HTTP response header, cũng như gán đúng `user_id` vào context log khi người dùng đã đăng nhập.
- **Kết quả**: Đạt yêu cầu giám sát chất lượng và không lưu trữ thông tin nhạy cảm (như mật khẩu hay token) vào context.
