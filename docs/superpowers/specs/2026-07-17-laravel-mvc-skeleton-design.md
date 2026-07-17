# Laravel MVC Skeleton Design

## Mục tiêu

Khởi tạo bộ khung cho đồ án mã nguồn mở “Website học tiếng Anh” theo kế hoạch trong Google Sheet. Kết quả phải giúp nhóm bắt đầu phát triển và chia việc ngay, nhưng chưa triển khai nghiệp vụ CRUD, xác thực, phân quyền, quiz hoặc thống kê.

## Phạm vi

- Tạo một ứng dụng Laravel theo cấu trúc MVC chuẩn.
- Cung cấp môi trường Docker Compose gồm Nginx, PHP-FPM, MySQL và Redis.
- Tạo khung dữ liệu và class nền cho các miền đã có trong kế hoạch.
- Tạo route, giao diện và kiểm thử smoke ở mức đủ chứng minh skeleton hoạt động.
- Ghi hướng dẫn cài đặt và kế hoạch chia module/phụ thuộc.

Không thuộc phạm vi hiện tại: nghiệp vụ hoàn chỉnh, giao diện chi tiết, tích hợp upload thật, queue job, API hoàn chỉnh, production deployment và bộ test nghiệp vụ.

## Kiến trúc

Ứng dụng giữ nguyên cấu trúc Laravel mặc định để thành viên dễ tra cứu tài liệu và tiếp tục phát triển. Không thêm kiến trúc module tùy biến, Repository interface hoặc Service class rỗng.

- `app/Models`: các thực thể Eloquent nền.
- `app/Http/Controllers`: controller web, API và admin ở mức khung.
- `app/Http/Requests`: vị trí cho validation request của từng module.
- `app/Policies`: vị trí cho luật phân quyền theo tài nguyên.
- `app/Http/Resources`: vị trí cho JSON resource.
- `database/migrations`: schema ban đầu và khóa ngoại.
- `database/seeders`: dữ liệu mẫu tối thiểu, gồm tài khoản quản trị nếu cơ chế xác thực nền cho phép.
- `resources/views`: master layout Bootstrap và trang chào/dashboard placeholder.
- `routes/web.php`, `routes/api.php`: nhóm route public, learner, admin và API ở mức placeholder.

## Miền dữ liệu

Khung bao phủ các thực thể trong kế hoạch: User, Role, Course, Level, Topic, Lesson, Vocabulary, Quiz, Question, Answer, Attempt, Progress và Bookmark. Migration chỉ chứa các trường định danh, quan hệ và trường trạng thái cần thiết để biểu diễn cấu trúc; không nhúng logic chấm điểm hoặc quy tắc tiến độ.

Các quan hệ chính:

- Role có nhiều User.
- Level và Topic phân loại Course/Vocabulary theo nhu cầu schema tối giản.
- Course có nhiều Lesson; Lesson có thể có Vocabulary và Quiz.
- Quiz có nhiều Question; Question có nhiều Answer.
- User có nhiều Attempt, Progress và Bookmark.

Tên bảng, khóa ngoại và index tuân theo quy ước Laravel. Migration phải rollback được.

## Luồng chạy và giao diện

Nginx chuyển request vào PHP-FPM và thư mục `public/`. Laravel dùng MySQL làm cơ sở dữ liệu, Redis được cấu hình sẵn cho cache/queue nhưng chưa có job nghiệp vụ.

Trang `/` hiển thị layout Bootstrap và trạng thái skeleton. Route `/health` trả kết quả đơn giản để kiểm tra ứng dụng. Route admin và API chỉ khai báo điểm vào an toàn; không giả lập nghiệp vụ chưa tồn tại.

## Xử lý lỗi và cấu hình

- `.env.example` chứa biến cấu hình Docker nhưng không chứa bí mật thật.
- Container chờ dịch vụ phụ thuộc bằng healthcheck của Compose.
- Laravel giữ cơ chế exception/logging mặc định; không tạo custom exception khi chưa có nghiệp vụ.
- README nêu rõ lỗi thường gặp về port, quyền ghi `storage`, key ứng dụng và kết nối database.

## Kiểm thử và tiêu chí hoàn thành

Một smoke test xác nhận trang chính hoặc health endpoint trả phản hồi thành công. Các kiểm tra hoàn thành:

1. Docker Compose build và khởi động được trong môi trường có Docker.
2. Laravel boot được với `.env.example` đã cấu hình.
3. Migration chạy lên và rollback được.
4. Smoke test chạy thành công.
5. README đủ để thành viên mới cài đặt.
6. `docs/PROJECT_PLAN.md` ánh xạ các module trong Google Sheet sang phần việc, phụ thuộc và sản phẩm bàn giao.

Nếu máy thực thi thiếu PHP, Composer hoặc Docker, bộ khung vẫn phải được kiểm tra tĩnh; README ghi lệnh xác minh để nhóm chạy trên máy đã cài công cụ.

## Nguyên tắc triển khai

- Ưu tiên generator và cấu trúc mặc định của Laravel.
- Không thêm package ngoài nếu Laravel, PHP hoặc Bootstrap CDN đã đáp ứng.
- Không tạo class rỗng chỉ để “dùng sau”; mỗi file khung phải có vai trò hiện tại trong schema, route, render hoặc kiểm thử.
- Giữ diff nhỏ, dễ chia commit và dễ giao lại cho từng thành viên.
